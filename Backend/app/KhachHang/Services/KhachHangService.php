<?php

namespace App\KhachHang\Services;

use App\KhachHang\Repositories\KhachHangRepository;
use App\KhuyenMai\Services\KhuyenMaiService;

/**
 * Service (Business Logic) cho module KhachHang & Đặt Phòng Trực Tuyến.
 */
class KhachHangService
{
    private KhachHangRepository $repository;
    private KhuyenMaiService $kmService;

    public function __construct(?KhachHangRepository $repository = null, ?KhuyenMaiService $kmService = null)
    {
        $this->repository = $repository ?? new KhachHangRepository();
        $this->kmService = $kmService ?? new KhuyenMaiService();
    }

    /**
     * Xem danh sách các phòng và loại phòng trong khách sạn.
     */
    public function xemDSPhong(array $params = []): array
    {
        $rooms = $this->repository->getAllRoomsWithTypes();
        return [
            'success' => true,
            'total' => count($rooms),
            'data' => $rooms
        ];
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

        // 1. Kiểm tra phòng
        $room = $this->repository->findRoomById($maPhong);
        if (!$room) {
            return [
                'success' => false,
                'error' => "Phòng '{$maPhong}' không tồn tại trong hệ thống."
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

        // 3. Tính toán thời gian nhận & trả phòng
        $thoiGianNhan = $ngayNhan . ' 14:00:00';
        $thoiGianTra = date('Y-m-d 12:00:00', strtotime($ngayNhan . " + {$soDem} days"));

        // 4. Tính tiền phòng & Áp dụng khuyến mãi
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

        // 5. Sinh mã tự động & Lưu dữ liệu vào CSDL
        $maPhieu = $this->repository->generateNextMaPhieu();
        $maHD = $this->repository->generateNextMaHD();
        $maTT = $this->repository->generateNextMaTT();

        // Lưu PhieuNhanPhong
        $this->repository->createPhieuNhanPhong([
            'MaPhieu' => $maPhieu,
            'NgayLap' => date('Y-m-d'),
            'TrangThai' => 'DaDat',
            'MaKH' => $maKH,
            'MaKM' => !empty($thongTinKM['MaKM']) ? $thongTinKM['MaKM'] : null
        ]);

        // Lưu ChiTietPhieuNhanPhong
        $this->repository->createChiTietPhieu([
            'MaPhieu' => $maPhieu,
            'MaPhong' => $maPhong,
            'ThoiGianNhanPhong' => $thoiGianNhan,
            'ThoiGianTraPhong' => $thoiGianTra,
            'SoNguoi' => $soNguoi,
            'TrangThai' => 'DaDat'
        ]);

        // Cập nhật trạng thái phòng
        $this->repository->updateRoomStatus($maPhong, 'DaDat');

        // Lưu HoaDon
        $this->repository->createHoaDon([
            'MaHD' => $maHD,
            'MaPhieu' => $maPhieu,
            'TongTienSauVAT' => $tongTienSauVAT
        ]);

        // Lưu CTHD
        $this->repository->createCTHD([
            'MaHD' => $maHD,
            'MaPhieu' => $maPhieu,
            'MaPhong' => $maPhong,
            'SoDem' => $soDem,
            'SoLuong' => 1,
            'DonGia' => $giaPhong
        ]);

        // Tạo sẵn giao dịch ThanhToan
        $this->repository->createThanhToan([
            'MaThanhToan' => $maTT,
            'SoTien' => $tongTienSauVAT,
            'ThoiGianThanhToan' => date('Y-m-d H:i:s'),
            'HinhThuc' => 'ChuyenKhoan',
            'TrangThaiThanhToan' => 'ChoThanhToan',
            'MaHD' => $maHD
        ]);

        // Link VietQR Napas 247 MBBank
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
