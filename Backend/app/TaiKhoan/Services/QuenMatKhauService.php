<?php

declare(strict_types=1);

namespace App\TaiKhoan\Services;

use App\TaiKhoan\Repositories\QuenMatKhauRepository;
use Database\Database;
use Shared\Mailer;

/**
 * Service (business logic) cho chức năng Quên Mật Khẩu module TaiKhoan.
 */
class QuenMatKhauService
{
    private QuenMatKhauRepository $repository;

    public function __construct(?QuenMatKhauRepository $repository = null)
    {
        $this->repository = $repository ?? new QuenMatKhauRepository(new Database());
    }

    public function quenMatKhau(array $params = []): array
    {
        $action = trim($params['action'] ?? $params['hanhDong'] ?? '');

        // Tự động suy luận action nếu client không truyền rõ trường action
        if ($action === '') {
            if (isset($params['reset_token']) || isset($params['resetToken']) || isset($params['matKhauMoi'])) {
                $action = 'datlai_matkhau';
            } elseif (isset($params['otp'])) {
                $action = 'xacminh_otp';
            } else {
                $action = 'gui_otp';
            }
        }

        switch ($action) {
            case 'gui_otp':
                return $this->xuLyGuiOTP($params);

            case 'xacminh_otp':
                return $this->xuLyXacMinhOTP($params);

            case 'datlai_matkhau':
                return $this->xuLyDatLaiMatKhau($params);

            default:
                throw new \Exception("Hành động không hợp lệ.");
        }
    }

    /**
     * Bước 1 & 2: Kiểm tra email, sinh OTP 6 số, giới hạn cooldown và gửi qua Gmail SMTP
     */
    private function xuLyGuiOTP(array $params): array
    {
        $email = trim($params['email'] ?? '');

        if ($email === '') {
            throw new \Exception("Vui lòng nhập địa chỉ email.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Địa chỉ email không đúng định dạng.");
        }

        // Thông báo an toàn chung (chống rà quét tài khoản)
        $thongBaoAnToan = [
            'success' => true,
            'message' => 'Nếu email của bạn hợp lệ và tồn tại trong hệ thống, mã xác nhận OTP đã được gửi đến hộp thư của bạn.'
        ];

        // 1. Kiểm tra tài khoản trong DB
        $taiKhoan = $this->repository->findByEmail($email);

        // Chỉ gửi OTP cho tài khoản Khách hàng đang Hoạt động
        if ($taiKhoan === null) {
            return $thongBaoAnToan;
        }

        if ($taiKhoan->getVaiTro() !== 'KhachHang') {
            return $thongBaoAnToan;
        }

        $trangThai = $taiKhoan->getTrangThaiTaiKhoan();
        if ($trangThai !== 'HoatDong' && $trangThai !== 'Hoạt động') {
            return $thongBaoAnToan;
        }

        // 2. Kiểm tra cooldown chống spam (tối thiểu 60 giây giữa 2 lần gửi)
        $lastSent = $this->repository->getLastSentTime($email);
        if ($lastSent) {
            $diffSeconds = time() - strtotime($lastSent);
            if ($diffSeconds < 60) {
                $wait = 60 - $diffSeconds;
                throw new \Exception("Vui lòng đợi thêm {$wait} giây trước khi yêu cầu gửi lại mã OTP.");
            }
        }

        // 3. Sinh OTP 6 chữ số an toàn mật mã (giữ số 0 ở đầu nếu có)
        $otp = sprintf('%06d', random_int(0, 999999));
        $otpHash = hash('sha256', $otp);
        $hetHanLuc = date('Y-m-d H:i:s', time() + 5 * 60); // 5 phút

        // 4. Vô hiệu hóa mọi mã OTP cũ của email này
        $this->repository->invalidateOldOTPs($email);

        // 5. Lưu hash OTP vào bảng TaiKhoanOTP
        $this->repository->createOTPRequest($email, $taiKhoan->getMaTK(), $otpHash, $hetHanLuc);

        // 6. Lấy tên khách hàng và gửi email qua Gmail SMTP
        $hoTen = $this->repository->findKhachHangNameByEmail($email);
        \Shared\Mailer::sendOTP($email, $otp, 5, $hoTen);

        return [
            'success' => true,
            'message' => 'Mã xác nhận OTP gồm 6 chữ số đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư đến (hoặc thư rác).'
        ];
    }

    /**
     * Bước 3 & 4: Nhập mã OTP, kiểm tra số lần thử và cấp ResetToken ngắn hạn
     */
    private function xuLyXacMinhOTP(array $params): array
    {
        $email = trim($params['email'] ?? '');
        $otp   = trim($params['otp'] ?? '');

        if ($email === '') {
            throw new \Exception("Thiếu địa chỉ email.");
        }

        if ($otp === '') {
            throw new \Exception("Vui lòng nhập mã OTP gồm 6 chữ số.");
        }

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            throw new \Exception("Mã OTP phải gồm đúng 6 chữ số.");
        }

        // Lấy bản ghi OTP mới nhất đang chờ xác minh
        $record = $this->repository->getLatestActiveOTP($email);

        if (!$record) {
            // Kiểm tra xem mã này có vừa được xác minh thành công trước đó hay không (tránh lỗi khi người dùng bấm đúp hoặc submit lại)
            $recentlyVerified = $this->repository->getRecentlyVerifiedOTP($email);
            if ($recentlyVerified && hash_equals($recentlyVerified['OTPHash'], hash('sha256', $otp))) {
                return [
                    'success' => true,
                    'message' => 'Mã OTP đã được xác minh thành công. Vui lòng đặt mật khẩu mới.',
                    'data' => [
                        'reset_token' => $recentlyVerified['ResetToken']
                    ]
                ];
            }

            throw new \Exception("Không tìm thấy yêu cầu OTP hợp lệ hoặc mã đã bị hủy. Vui lòng yêu cầu mã mới.");
        }

        // Kiểm tra hết hạn theo đồng hồ cơ sở dữ liệu
        if ($this->repository->isOTPExpired((int)$record['MaOTP'])) {
            throw new \Exception("Mã OTP đã hết hiệu lực (quá 5 phút). Vui lòng yêu cầu gửi lại mã mới.");
        }

        // Kiểm tra số lần thử
        if ((int)$record['SoLanThu'] >= 5) {
            throw new \Exception("Mã OTP đã bị khóa do nhập sai quá 5 lần. Vui lòng yêu cầu mã mới.");
        }

        // So khớp hash an toàn mật mã (chống Timing Attack)
        $inputHash = hash('sha256', $otp);
        if (!hash_equals($record['OTPHash'], $inputHash)) {
            $attempts = $this->repository->incrementAttempts((int)$record['MaOTP']);
            $remaining = max(0, 5 - $attempts);

            if ($remaining === 0) {
                throw new \Exception("Bạn đã nhập sai mã OTP 5 lần. Phiên xác minh đã bị hủy, vui lòng yêu cầu mã mới.");
            }

            throw new \Exception("Mã OTP không chính xác. Bạn còn {$remaining} lần thử.");
        }

        // Khớp OTP thành công: Sinh ResetToken bảo mật (chuỗi hex 64 ký tự) có hạn 10 phút
        $resetToken = bin2hex(random_bytes(32));
        $hetHanTokenLuc = date('Y-m-d H:i:s', time() + 10 * 60);

        $this->repository->markOTPVerified((int)$record['MaOTP'], $resetToken, $hetHanTokenLuc);

        return [
            'success' => true,
            'message' => 'Xác minh mã OTP thành công. Vui lòng đặt mật khẩu mới.',
            'data' => [
                'reset_token' => $resetToken
            ]
        ];
    }

    /**
     * Bước 5: Đặt mật khẩu mới với ResetToken hợp lệ
     */
    private function xuLyDatLaiMatKhau(array $params): array
    {
        $email           = trim($params['email'] ?? '');
        $resetToken      = trim($params['reset_token'] ?? $params['resetToken'] ?? '');
        $matKhauMoi      = $params['matKhauMoi'] ?? $params['mat_khau_moi'] ?? '';
        $xacNhanMatKhau  = $params['xacNhanMatKhau'] ?? $params['xac_nhan_mat_khau'] ?? '';

        if ($email === '') {
            throw new \Exception("Thiếu địa chỉ email.");
        }

        if ($resetToken === '') {
            throw new \Exception("Thiếu token xác minh. Bạn không thể bỏ qua bước xác minh OTP.");
        }

        if (empty($matKhauMoi)) {
            throw new \Exception("Vui lòng nhập mật khẩu mới.");
        }

        if (strlen($matKhauMoi) < 6) {
            throw new \Exception("Mật khẩu mới phải có độ dài tối thiểu từ 6 ký tự trở lên.");
        }

        if ($matKhauMoi !== $xacNhanMatKhau) {
            throw new \Exception("Mật khẩu xác nhận không khớp với mật khẩu mới.");
        }

        // Kiểm tra ResetToken trong CSDL
        $record = $this->repository->findActiveByResetToken($resetToken);

        if (!$record || $record['Email'] !== $email) {
            throw new \Exception("Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng thực hiện lại.");
        }

        // Mã hóa mật khẩu bằng BCRYPT (đồng bộ với đăng ký & đăng nhập)
        $hashedPassword = password_hash($matKhauMoi, PASSWORD_DEFAULT);

        // Cập nhật CSDL và vô hiệu hóa phiên OTP
        $this->repository->resetPasswordWithToken($email, $resetToken, $hashedPassword);

        return [
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công! Bây giờ Quý khách có thể đăng nhập bằng mật khẩu mới.'
        ];
    }
}
