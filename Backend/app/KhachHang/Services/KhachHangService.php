<?php

namespace App\KhachHang\Services;

use App\KhachHang\Repositories\KhachHangRepository;
use App\KhuyenMai\Services\KhuyenMaiService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;

/**
 * Service (Business Logic) cho module KhachHang & Đặt Phòng Trực Tuyến.
 */
class KhachHangService
{
    private KhachHangRepository $repository;
    private KhuyenMaiService $kmService;
    private PhongService $phongService;

    public function __construct(
        ?KhachHangRepository $repository = null,
        ?KhuyenMaiService $kmService = null,
        ?PhongService $phongService = null
    ) {
        $this->repository = $repository ?? new KhachHangRepository();
        $this->kmService = $kmService ?? new KhuyenMaiService();
        $this->phongService = $phongService ?? new PhongService(new PhongRepository());
    }

    /**
     * Xem danh sách các phòng và loại phòng trong khách sạn.
     */
    public function xemDSPhong(array $params = []): array
    {
        return $this->phongService->xemDSPhong($params);
    }

    public function xemDSKhachHang(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $customers = $this->repository->getAllCustomers($keyword);

        return [
            'success' => true,
            'keyword' => $keyword,
            'total' => count($customers),
            'data' => $customers,
        ];
    }

    public function timKiemKhachHang(array $params = []): array
    {
        return $this->xemDSKhachHang($params);
    }

    public function xemCTKhachHang(array $params = []): array
    {
        $id = trim((string)($params['MaKH'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return $this->managementFailure('Vui lòng cung cấp mã khách hàng (MaKH).');
        }

        $customer = $this->repository->findCustomerForManagement($id);
        if ($customer === null) {
            return $this->managementFailure("Không tìm thấy khách hàng '{$id}'.", 404);
        }
        unset($customer['VaiTro']);

        return ['success' => true, 'data' => $customer];
    }

    public function capNhatThongTinKhachHang(array $params = []): array
    {
        $id = trim((string)($params['MaKH'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return $this->managementFailure('Vui lòng cung cấp mã khách hàng (MaKH).');
        }

        $current = $this->repository->findCustomerForManagement($id);
        if ($current === null) {
            return $this->managementFailure("Không tìm thấy khách hàng '{$id}'.", 404);
        }

        $updates = array_intersect_key($params, array_flip(['HoTen', 'SDT', 'Email']));
        if ($updates === []) {
            return $this->managementFailure('Vui lòng cung cấp thông tin cần cập nhật.');
        }

        foreach ($updates as $field => $value) {
            if (!is_string($value) || trim($value) === '') {
                return $this->managementFailure("{$field} không được để trống.");
            }
            $updates[$field] = trim($value);
        }

        $hoTen = $updates['HoTen'] ?? $current['HoTen'];
        $sdt = $updates['SDT'] ?? $current['SDT'];
        $email = $updates['Email'] ?? $current['Email'];
        if (strlen($hoTen) > 100 || strlen($sdt) > 20 || ($email !== null && strlen($email) > 255)) {
            return $this->managementFailure('Một hoặc nhiều trường vượt quá độ dài cho phép.');
        }
        if (array_key_exists('Email', $updates) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->managementFailure('Email không hợp lệ.');
        }
        if (
            array_key_exists('Email', $updates)
            && $email !== $current['Email']
            && $this->repository->customerEmailExists($email, $id)
        ) {
            return $this->managementFailure('Email đã được sử dụng.', 409);
        }

        if (!$this->repository->updateCustomer($id, $updates)) {
            return $this->managementFailure('Không thể cập nhật thông tin khách hàng.', 500);
        }

        $customer = $this->repository->findCustomerForManagement($id);
        if ($customer !== null) {
            unset($customer['VaiTro']);
        }

        return [
            'success' => true,
            'message' => 'Cập nhật thông tin khách hàng thành công.',
            'data' => $customer,
        ];
    }

    public function khoaTaiKhoanKhachHang(array $params = []): array
    {
        return $this->setCustomerAccountStatus($params, 'BiKhoa', 'Khóa tài khoản khách hàng thành công.');
    }

    public function moTaiKhoanKhachHang(array $params = []): array
    {
        return $this->setCustomerAccountStatus($params, 'HoatDong', 'Mở khóa tài khoản khách hàng thành công.');
    }

    private function setCustomerAccountStatus(array $params, string $status, string $message): array
    {
        $id = trim((string)($params['MaKH'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return $this->managementFailure('Vui lòng cung cấp mã khách hàng (MaKH).');
        }

        $customer = $this->repository->findCustomerForManagement($id);
        if ($customer === null) {
            return $this->managementFailure("Không tìm thấy khách hàng '{$id}'.", 404);
        }
        if ($customer['MaTK'] === null || $customer['VaiTro'] !== 'KhachHang') {
            return $this->managementFailure('Khách hàng này chưa có tài khoản đăng nhập để khóa hoặc mở.', 409);
        }
        if (!$this->repository->updateCustomerAccountStatus($id, $status)) {
            return $this->managementFailure('Không thể cập nhật trạng thái tài khoản khách hàng.', 500);
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => $this->repository->findCustomerForManagement($id),
        ];
    }

    private function managementFailure(string $message, int $statusCode = 400): array
    {
        return ['success' => false, 'error' => $message, 'statusCode' => $statusCode];
    }

    /**
     * Khách hàng đặt phòng trực tuyến & Lưu dữ liệu bền vững vào CSDL.
     */
    public function datPhong(array $params): array
    {
        $hoTen = trim($params['HoTen'] ?? '');
        $sdt = trim($params['SDT'] ?? '');
        $email = trim($params['Email'] ?? '');
        $maPhong = trim($params['MaPhong'] ?? '');
        $soDem = max(1, (int)($params['SoDem'] ?? 1));
        $soNguoi = max(1, (int)($params['SoNguoi'] ?? 2));
        $ngayNhan = !empty($params['NgayNhanPhong']) ? $params['NgayNhanPhong'] : date('Y-m-d');
        $maKM = trim($params['MaKM'] ?? '');

        if (empty($hoTen) || empty($sdt) || empty($maPhong)) {
            return [
                'success' => false,
                'error' => 'Vui lòng điền đầy đủ Họ tên, Số điện thoại và chọn Phòng cần đặt.'
            ];
        }

        // Use the BE3 room service as the source of truth for room details and availability.
        $availability = $this->phongService->kiemTraPhongTrong(['MaPhong' => $maPhong]);
        if (!($availability['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $availability['error'] ?? 'Không thể kiểm tra tình trạng phòng.',
                'statusCode' => $availability['statusCode'] ?? 500,
            ];
        }
        if (!($availability['available'] ?? false)) {
            return [
                'success' => false,
                'error' => "Phòng '{$maPhong}' hiện không còn trống.",
                'statusCode' => 409,
            ];
        }

        $room = $availability['data'];
        if ($soNguoi > (int)$room['SoNguoiToiDa']) {
            return [
                'success' => false,
                'error' => "Loại phòng này chỉ cho phép tối đa {$room['SoNguoiToiDa']} người.",
                'statusCode' => 400,
            ];
        }

        $thoiGianNhan = $ngayNhan . ' 14:00:00';
        $thoiGianTra = date('Y-m-d 12:00:00', strtotime($ngayNhan . " + {$soDem} days"));

        // The conditional status update prevents concurrent BE1/BE3 requests from booking the same room.
        try {
            $this->repository->beginBookingTransaction();
            if (!$this->repository->reserveRoomIfAvailable($maPhong)) {
                $this->repository->rollBackBookingTransaction();
                return [
                    'success' => false,
                    'error' => "Phòng '{$maPhong}' vừa được đặt bởi một yêu cầu khác.",
                    'statusCode' => 409,
                ];
            }

            // 2. Tìm hoặc Tạo mới Khách Hàng
            $customer = $this->repository->findCustomerByPhone($sdt);
            if ($customer) {
                $maKH = $customer['MaKH'];
            } else {
                $maKH = $this->repository->createCustomer([
                    'HoTen' => $hoTen,
                    'SDT' => $sdt,
                    'Email' => $email,
                ]);
            }

            // 3. Tính tiền phòng & Áp dụng khuyến mãi
            $giaPhong = (float)$room['Gia'];
            $tienPhongGoc = $giaPhong * $soDem;
            $tienGiamKM = 0.0;
            $thongTinKM = null;

            if (!empty($maKM)) {
                $checkKM = $this->kmService->apDungKhuyenMai([
                    'MaKM' => $maKM,
                    'TienPhong' => $tienPhongGoc,
                    'SoDem' => $soDem,
                    'SoNguoi' => $soNguoi,
                    'NgayApDung' => $ngayNhan
                ]);
                if ($checkKM['success'] && isset($checkKM['data']['TienGiam'])) {
                    $tienGiamKM = (float)$checkKM['data']['TienGiam'];
                    $thongTinKM = $checkKM['data'];
                }
            }

            $tienPhongSauGiam = max(0, $tienPhongGoc - $tienGiamKM);
            $thueVAT = round($tienPhongSauGiam * 0.1);
            $tongTienSauVAT = $tienPhongSauGiam + $thueVAT;

            // 4. Sinh mã tự động & Lưu dữ liệu vào CSDL
            $maPhieu = $this->repository->generateNextMaPhieu();
            $maHD = $this->repository->generateNextMaHD();
            $maTT = $this->repository->generateNextMaTT();

            $this->repository->createPhieuNhanPhong([
                'MaPhieu' => $maPhieu,
                'NgayLap' => date('Y-m-d'),
                'TrangThai' => 'DaDat',
                'MaKH' => $maKH,
                'MaKM' => !empty($thongTinKM['MaKM']) ? $thongTinKM['MaKM'] : null
            ]);

            $this->repository->createChiTietPhieu([
                'MaPhieu' => $maPhieu,
                'MaPhong' => $maPhong,
                'ThoiGianNhanPhong' => $thoiGianNhan,
                'ThoiGianTraPhong' => $thoiGianTra,
                'SoNguoi' => $soNguoi,
                'TrangThai' => 'DaDat'
            ]);

            $this->repository->createHoaDon([
                'MaHD' => $maHD,
                'MaPhieu' => $maPhieu,
                'TongTienSauVAT' => $tongTienSauVAT
            ]);

            $this->repository->createCTHD([
                'MaHD' => $maHD,
                'MaPhieu' => $maPhieu,
                'MaPhong' => $maPhong,
                'SoDem' => $soDem,
                'SoLuong' => 1,
                'DonGia' => $giaPhong
            ]);

            $this->repository->createThanhToan([
                'MaThanhToan' => $maTT,
                'SoTien' => $tongTienSauVAT,
                'ThoiGianThanhToan' => date('Y-m-d H:i:s'),
                'HinhThuc' => 'ChuyenKhoan',
                'TrangThaiThanhToan' => 'ChoThanhToan',
                'MaHD' => $maHD
            ]);

            $this->repository->commitBookingTransaction();
        } catch (\Throwable $exception) {
            $this->repository->rollBackBookingTransaction();
            if (!$exception instanceof \PDOException) {
                throw $exception;
            }
            error_log('Online room booking failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'error' => 'Không thể hoàn tất đặt phòng; mọi thay đổi đã được hoàn tác.',
                'statusCode' => 500,
            ];
        }

        $vietQrUrl = "https://img.vietqr.io/image/MB-0589822346-compact2.png"
            . "?amount=" . (int)$tongTienSauVAT
            . "&addInfo=" . urlencode($maHD)
            . "&accountName=" . urlencode("NGUYEN HOANG ANH");

        return [
            'success' => true,
            'message' => "🎉 Đặt phòng thành công! Dữ liệu đã được lưu vào hệ thống.",
            'data' => [
                'MaPhieu' => $maPhieu,
                'MaHD' => $maHD,
                'MaThanhToan' => $maTT,
                'KhachHang' => [
                    'MaKH' => $maKH,
                    'HoTen' => $hoTen,
                    'SDT' => $sdt,
                    'Email' => $email
                ],
                'Phong' => [
                    'MaPhong' => $maPhong,
                    'LoaiPhong' => $room['TenLoaiPhong'],
                    'DonGia' => $giaPhong,
                    'SoDem' => $soDem,
                    'ThoiGianNhanPhong' => $thoiGianNhan,
                    'ThoiGianTraPhong' => $thoiGianTra
                ],
                'ChiPhi' => [
                    'TienPhongGoc' => $tienPhongGoc,
                    'TienGiamKM' => $tienGiamKM,
                    'TienPhongSauGiam' => $tienPhongSauGiam,
                    'ThueVAT' => $thueVAT,
                    'TongTienSauVAT' => $tongTienSauVAT
                ],
                'ThanhToan' => [
                    'NganHang' => 'MBBank (Ngân hàng Quân Đội)',
                    'SoTaiKhoan' => '0589822346',
                    'ChuTaiKhoan' => 'NGUYEN HOANG ANH',
                    'NoiDung' => $maHD,
                    'VietQrUrl' => $vietQrUrl
                ]
            ]
        ];
    }

    /**
     * Tra cứu thông tin đặt phòng & hóa đơn theo số điện thoại hoặc mã phiếu.
     */
    public function traCuu(string $keyword): array
    {
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return ['success' => false, 'error' => 'Vui lòng cung cấp số điện thoại hoặc mã phiếu để tra cứu.'];
        }

        $list = $this->repository->findBookingsByPhoneOrKeyword($keyword);
        return [
            'success' => true,
            'total' => count($list),
            'data' => $list
        ];
    }
}
