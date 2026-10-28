<?php

declare(strict_types=1);

namespace App\KhachHang\Services;

use App\KhachHang\Repositories\KhachHangXuLyRepository;
use App\TaiKhoan\Repositories\TaiKhoanRepository;
use Database\Database;
use Exception;

/**
 * Service thực thi logic nghiệp vụ cho module KhachHang.
 * Bao gồm 2 phần nghiệp vụ: Tra cứu & Validation (Query) và Thao tác đăng ký & Cập nhật hồ sơ (Command).
 */
class KhachHangXuLyService
{
    private KhachHangXuLyRepository $khachHangRepo;
    private ?TaiKhoanRepository $taiKhoanRepo;

    public function __construct(
        KhachHangXuLyRepository $khachHangRepo,
        ?TaiKhoanRepository $taiKhoanRepo = null
    ) {
        $this->khachHangRepo = $khachHangRepo;
        $this->taiKhoanRepo = $taiKhoanRepo;
    }

    private function getTaiKhoanRepo(): TaiKhoanRepository
    {
        if ($this->taiKhoanRepo === null) {
            $this->taiKhoanRepo = new TaiKhoanRepository(new Database());
        }
        return $this->taiKhoanRepo;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & VALIDATION ĐỊNH DẠNG (READ / VALIDATION)
    // =========================================================================

    /**
     * Tra cứu xem thông tin cá nhân khách hàng kèm thống kê đặt phòng và tài khoản.
     */
    public function xemThongTinCaNhan(array $params = []): array
    {
        $identifier = trim($params['maTK'] ?? ($params['maKH'] ?? ($params['MaTK'] ?? ($params['MaKH'] ?? ''))));
        if ($identifier === '') {
            throw new Exception("Vui lòng cung cấp mã tài khoản hoặc mã khách hàng cần xem.", 400);
        }

        $info = $this->khachHangRepo->getThongTinCaNhan($identifier);
        if (!$info) {
            throw new Exception("Không tìm thấy thông tin khách hàng.", 404);
        }

        return [
            'status' => 'success',
            'data' => $info
        ];
    }

    /**
     * Tra cứu khách hàng theo số điện thoại (phục vụ lễ tân tạo phiếu hoặc kiểm tra thành viên).
     */
    public function timTheoSDT(string $sdt): ?array
    {
        $sdt = trim($sdt);
        if ($sdt === '') {
            throw new Exception("Vui lòng cung cấp số điện thoại cần tra cứu.", 400);
        }
        return $this->khachHangRepo->findBySDT($sdt);
    }

    /**
     * Kiểm tra số điện thoại (chỉ 10 chữ số, đúng đầu số di động Việt Nam).
     */
    public function validateSDT(string $sdt, string $prefix = ''): void
    {
        $sdt = trim($sdt);
        $len = strlen($sdt);
        $p = $prefix ? "{$prefix}: " : '';

        if (!preg_match('/^[0-9]+$/', $sdt)) {
            throw new Exception("{$p}Số điện thoại chỉ được chứa các chữ số (0-9).", 400);
        }
        if ($len < 10) {
            $thieu = 10 - $len;
            throw new Exception("{$p}Số điện thoại chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định số điện thoại phải gồm đúng 10 số.", 400);
        }
        if ($len > 10) {
            $thua = $len - 10;
            throw new Exception("{$p}Số điện thoại có {$len} chữ số (thừa {$thua} số). Quy định số điện thoại chỉ gồm đúng 10 số.", 400);
        }
        if (!preg_match('/^(03|05|07|08|09)/', $sdt)) {
            throw new Exception("{$p}Số điện thoại không hợp lệ. Phải bắt đầu bằng các đầu số di động Việt Nam (03, 05, 07, 08, 09).", 400);
        }
    }

    /**
     * Kiểm tra chi tiết định dạng và độ dài các loại giấy tờ tùy thân.
     */
    public function validateSoGiayTo(?string $loaiGiayTo, ?string $soGiayTo, string $prefix = ''): void
    {
        if (empty($soGiayTo)) return;
        $soGiayTo = trim($soGiayTo);
        $len = mb_strlen($soGiayTo, 'UTF-8');
        $p = $prefix ? "{$prefix}: " : '';
        $normalizedType = mb_strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string)$loaiGiayTo));

        if (str_contains($normalizedType, 'CCCD')) {
            if (!preg_match('/^[0-9]+$/', $soGiayTo)) {
                throw new Exception("{$p}Số CCCD chỉ được chứa các chữ số (0-9), không chứa chữ cái hoặc ký tự đặc biệt.", 400);
            }
            if ($len < 12) {
                $thieu = 12 - $len;
                throw new Exception("{$p}Số CCCD chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định CCCD phải gồm đúng 12 chữ số.", 400);
            }
            if ($len > 12) {
                $thua = $len - 12;
                throw new Exception("{$p}Số CCCD có {$len} chữ số (thừa {$thua} số). Quy định CCCD chỉ gồm đúng 12 chữ số.", 400);
            }
        } elseif (str_contains($normalizedType, 'CMND')) {
            if (!preg_match('/^[0-9]+$/', $soGiayTo)) {
                throw new Exception("{$p}Số CMND chỉ được chứa các chữ số (0-9).", 400);
            }
            if ($len < 9) {
                $thieu = 9 - $len;
                throw new Exception("{$p}Số CMND chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định CMND phải gồm đúng 9 chữ số.", 400);
            }
            if ($len > 9) {
                $thua = $len - 9;
                throw new Exception("{$p}Số CMND có {$len} chữ số (thừa {$thua} số). Quy định CMND chỉ gồm đúng 9 chữ số.", 400);
            }
        } elseif (str_contains($normalizedType, 'HOCHIEU') || str_contains($normalizedType, 'PASSPORT')) {
            if (preg_match('/\s/', $soGiayTo) || preg_match('/[^A-Za-z0-9]/', $soGiayTo)) {
                throw new Exception("{$p}Số Hộ chiếu không được chứa khoảng trắng hoặc ký tự đặc biệt.", 400);
            }
            if ($len < 7) {
                $thieu = 7 - $len;
                throw new Exception("{$p}Số Hộ chiếu chỉ có {$len} ký tự (quá ngắn, thiếu tối thiểu {$thieu} ký tự). Quy định Hộ chiếu từ 7 đến 9 ký tự (ví dụ: B1234567, C12345678).", 400);
            }
            if ($len > 9) {
                $thua = $len - 9;
                throw new Exception("{$p}Số Hộ chiếu có {$len} ký tự (quá dài, thừa {$thua} ký tự). Quy định Hộ chiếu tối đa 9 ký tự.", 400);
            }
        } elseif (str_contains($normalizedType, 'GIAYKHAISINH')) {
            if ($len < 6) {
                $thieu = 6 - $len;
                throw new Exception("{$p}Số Giấy khai sinh chỉ có {$len} ký tự (quá ngắn, thiếu {$thieu} ký tự). Độ dài hợp lệ từ 6 đến 15 ký tự.", 400);
            }
            if ($len > 15) {
                $thua = $len - 15;
                throw new Exception("{$p}Số Giấy khai sinh có {$len} ký tự (quá dài, thừa {$thua} ký tự). Độ dài hợp lệ tối đa 15 ký tự.", 400);
            }
        }
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC ĐĂNG KÝ, CẬP NHẬT HỒ SƠ & MẬT KHẨU (WRITE / COMMAND)
    // =========================================================================

    /**
     * Đăng ký tài khoản khách hàng mới.
     *
     * @param array $params Chứa hoTen, sdt, email, tenDangNhap, matKhau,...
     * @return array Kết quả tạo tài khoản
     * @throws Exception Khi dữ liệu không hợp lệ hoặc bị trùng
     */
    public function dangKy(array $params = []): array
    {
        // 1. Nhận tham số đầu vào
        $hoTen       = trim($params['hoTen'] ?? '');
        $sdt         = trim($params['sdt'] ?? '');
        $email       = trim($params['email'] ?? '');
        $tenDangNhap = trim($params['tenDangNhap'] ?? '');
        $matKhau     = $params['matKhau'] ?? '';
        $ngaySinh    = !empty($params['ngaySinh']) ? trim($params['ngaySinh']) : null;
        $quocTich    = !empty($params['quocTich']) ? trim($params['quocTich']) : 'Việt Nam';
        $loaiGiayTo  = !empty($params['loaiGiayTo']) ? trim($params['loaiGiayTo']) : 'CCCD';
        $soGiayTo    = !empty($params['soGiayTo']) ? trim($params['soGiayTo']) : null;

        // 2. Kiểm tra dữ liệu đầu vào (Validation)
        if ($hoTen === '') {
            throw new Exception("Vui lòng nhập họ và tên.");
        }

        if ($sdt === '') {
            throw new Exception("Vui lòng nhập số điện thoại.");
        }
        $this->validateSDT($sdt);

        if ($soGiayTo !== null && $soGiayTo !== '') {
            $this->validateSoGiayTo($loaiGiayTo, $soGiayTo);
        }

        if ($email === '') {
            throw new Exception("Vui lòng nhập email.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email không hợp lệ.");
        }

        if ($tenDangNhap === '') {
            throw new Exception("Vui lòng nhập tên đăng nhập.");
        }

        if (empty($matKhau)) {
            throw new Exception("Vui lòng nhập mật khẩu.");
        }

        $taiKhoanRepo = $this->getTaiKhoanRepo();

        // 3. Kiểm tra tính duy nhất (Unique check trên bảng TaiKhoan)
        if ($taiKhoanRepo->checkTonTaiSDT($sdt)) {
            throw new Exception("Số điện thoại đã tồn tại trong hệ thống.");
        }

        if ($taiKhoanRepo->findByEmail($email) !== null) {
            throw new Exception("Email đã được sử dụng.");
        }

        if ($taiKhoanRepo->findByTenDangNhap($tenDangNhap) !== null) {
            throw new Exception("Tên đăng nhập đã tồn tại.");
        }

        // 4. Mã hóa mật khẩu bằng password_hash
        $matKhauHash = password_hash($matKhau, PASSWORD_DEFAULT);

        // 5. Mở DB Transaction lưu đồng thời vào cả 2 bảng TaiKhoan và KhachHang
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Sinh MaTK và thêm vào bảng TaiKhoan
            $maTK = $taiKhoanRepo->generateMaTK($db);
            $kqTK = $taiKhoanRepo->themTaiKhoan(
                $maTK,
                $tenDangNhap,
                $matKhauHash,
                $email,
                'KhachHang',
                $db
            );
            if (!$kqTK) {
                throw new Exception("Thêm tài khoản thất bại.");
            }

            // Sinh MaKH và thêm vào bảng KhachHang
            $maKH = $this->khachHangRepo->generateMaKH($db);
            $kqKH = $this->khachHangRepo->themKhachHang(
                $maKH,
                $hoTen,
                $sdt,
                $maTK,
                $ngaySinh,
                $quocTich,
                $loaiGiayTo,
                $soGiayTo,
                $db
            );
            if (!$kqKH) {
                throw new Exception("Thêm thông tin khách hàng thất bại.");
            }

            $db->commit();

            return [
                'success' => true,
                'message' => 'Đăng ký tài khoản khách hàng thành công.',
                'data' => [
                    'MaTK'        => $maTK,
                    'MaKH'        => $maKH,
                    'HoTen'       => $hoTen,
                    'SDT'         => $sdt,
                    'NgaySinh'    => $ngaySinh,
                    'QuocTich'    => $quocTich,
                    'LoaiGiayTo'  => $loaiGiayTo,
                    'SoGiayTo'    => $soGiayTo,
                    'Email'       => $email,
                    'TenDangNhap' => $tenDangNhap,
                    'VaiTro'      => 'KhachHang'
                ]
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new Exception("Đăng ký tài khoản thất bại: " . $e->getMessage());
        }
    }

    /**
     * Cập nhật thông tin cá nhân khách hàng.
     */
    public function capNhatThongTinCaNhan(array $params = []): array
    {
        $maTK       = trim($params['maTK'] ?? $params['MaTK'] ?? '');
        $hoTen      = trim($params['hoTen'] ?? $params['HoTen'] ?? '');
        $sdt        = trim($params['sdt'] ?? $params['SDT'] ?? '');
        $email      = trim($params['email'] ?? $params['Email'] ?? '');
        $ngaySinh   = !empty($params['ngaySinh'] ?? $params['NgaySinh'] ?? null)
                        ? trim($params['ngaySinh'] ?? $params['NgaySinh'])
                        : null;
        $quocTich   = !empty($params['quocTich'] ?? $params['QuocTich'] ?? null)
                        ? trim($params['quocTich'] ?? $params['QuocTich'])
                        : 'Việt Nam';
        $loaiGiayTo = !empty($params['loaiGiayTo'] ?? $params['LoaiGiayTo'] ?? null)
                        ? trim($params['loaiGiayTo'] ?? $params['LoaiGiayTo'])
                        : 'CCCD';
        $soGiayTo   = !empty($params['soGiayTo'] ?? $params['SoGiayTo'] ?? null)
                        ? trim($params['soGiayTo'] ?? $params['SoGiayTo'])
                        : null;

        if ($maTK === '') {
            throw new Exception("Mã tài khoản không hợp lệ.", 400);
        }

        if ($hoTen === '') {
            throw new Exception("Vui lòng nhập họ và tên.", 400);
        }
        if (mb_strlen($hoTen, 'UTF-8') < 2) {
            throw new Exception("Họ và tên phải có ít nhất 2 ký tự.", 400);
        }

        if ($email === '') {
            throw new Exception("Vui lòng nhập địa chỉ email.", 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Địa chỉ email không đúng định dạng.", 400);
        }

        if ($sdt !== '') {
            $this->validateSDT($sdt);
            if ($this->khachHangRepo->checkSDTExistsForOther($sdt, $maTK)) {
                throw new Exception("Số điện thoại này đã được sử dụng bởi khách hàng khác.", 400);
            }
        } else {
            $sdt = null;
        }

        if ($soGiayTo !== null && $soGiayTo !== '') {
            $this->validateSoGiayTo($loaiGiayTo, $soGiayTo);
        }

        if ($this->khachHangRepo->checkEmailExistsForOther($email, $maTK)) {
            throw new Exception("Địa chỉ email này đã được sử dụng bởi tài khoản khác.", 400);
        }

        $this->khachHangRepo->capNhatThongTinCaNhan($maTK, $hoTen, $sdt, $email, $ngaySinh, $quocTich, $loaiGiayTo, $soGiayTo);

        $updated = $this->khachHangRepo->getThongTinCaNhan($maTK);

        return [
            'status' => 'success',
            'message' => 'Cập nhật thông tin cá nhân thành công!',
            'data' => $updated
        ];
    }

    /**
     * Đổi mật khẩu tài khoản khách hàng.
     */
    public function doiMatKhau(array $params = []): array
    {
        $maTK = trim($params['maTK'] ?? ($params['MaTK'] ?? ''));
        $matKhauCu = $params['matKhauCu'] ?? ($params['MatKhauCu'] ?? '');
        $matKhauMoi = $params['matKhauMoi'] ?? ($params['MatKhauMoi'] ?? '');
        $xacNhanMatKhau = $params['xacNhanMatKhau'] ?? ($params['XacNhanMatKhau'] ?? '');

        if ($maTK === '') {
            throw new Exception("Mã tài khoản không hợp lệ.", 400);
        }

        if (empty($matKhauCu)) {
            throw new Exception("Vui lòng nhập mật khẩu hiện tại.", 400);
        }

        if (empty($matKhauMoi)) {
            throw new Exception("Vui lòng nhập mật khẩu mới.", 400);
        }

        if (strlen($matKhauMoi) < 6) {
            throw new Exception("Mật khẩu mới phải có ít nhất 6 ký tự.", 400);
        }

        if ($matKhauMoi !== $xacNhanMatKhau) {
            throw new Exception("Xác nhận mật khẩu mới không khớp.", 400);
        }

        if ($matKhauCu === $matKhauMoi) {
            throw new Exception("Mật khẩu mới không được trùng với mật khẩu hiện tại.", 400);
        }

        $this->khachHangRepo->doiMatKhau($maTK, $matKhauCu, $matKhauMoi);

        return [
            'status' => 'success',
            'message' => 'Đổi mật khẩu thành công! Vui lòng ghi nhớ mật khẩu mới.'
        ];
    }
}
