<?php

declare(strict_types=1);

namespace App\TaiKhoan\Repositories;

use App\TaiKhoan\Models\TaiKhoan;
use Database\Database;
use PDO;

/**
 * Repository xử lý dữ liệu cho chức năng Quên Mật Khẩu và TaiKhoanOTP.
 * Chỉ lớp này được phép thao tác trực tiếp với cơ sở dữ liệu qua Database::getConnection().
 */
class QuenMatKhauRepository
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    /**
     * Tìm tài khoản theo email
     */
    public function findByEmail(string $email): ?TaiKhoan
    {
        $sql = "SELECT * FROM TaiKhoan WHERE Email = ?";
        $db = Database::getConnection();

        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);

        $taiKhoan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$taiKhoan) {
            return null;
        }

        return new TaiKhoan(
            $taiKhoan['MaTK'],
            $taiKhoan['TenDangNhap'],
            $taiKhoan['MatKhau'],
            $taiKhoan['Email'],
            $taiKhoan['TrangThaiTaiKhoan'],
            $taiKhoan['VaiTro']
        );
    }

    /**
     * Lấy họ tên khách hàng theo email để gửi email thân thiện
     */
    public function findKhachHangNameByEmail(string $email): string
    {
        $db = Database::getConnection();
        $sql = "SELECT kh.HoTen FROM KhachHang kh 
                JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK 
                WHERE tk.Email = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['HoTen'] ?? '';
    }

    /**
     * Lấy thời điểm gửi OTP gần nhất của email để kiểm tra cooldown
     */
    public function getLastSentTime(string $email): ?string
    {
        $db = Database::getConnection();
        $sql = "SELECT NgayTao FROM TaiKhoanOTP WHERE Email = ? ORDER BY MaOTP DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['NgayTao'] : null;
    }

    /**
     * Vô hiệu hóa các OTP cũ của email
     */
    public function invalidateOldOTPs(string $email): bool
    {
        $db = Database::getConnection();
        $sql = "UPDATE TaiKhoanOTP 
                SET TrangThai = 'DaHuy', NgayCapNhat = NOW() 
                WHERE Email = ? AND TrangThai IN ('ChoXacMinh', 'DaXacMinh')";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$email]);
    }

    /**
     * Tạo bản ghi yêu cầu OTP mới (hết hạn sau 5 phút tính theo đồng hồ DB)
     */
    public function createOTPRequest(string $email, ?string $maTK, string $otpHash, ?string $hetHanLuc = null): bool
    {
        $db = Database::getConnection();
        $sql = "INSERT INTO TaiKhoanOTP (Email, MaTK, OTPHash, SoLanThu, TrangThai, HetHanLuc, NgayTao, NgayCapNhat)
                VALUES (?, ?, ?, 0, 'ChoXacMinh', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$email, $maTK, $otpHash]);
    }

    /**
     * Lấy yêu cầu OTP mới nhất đang chờ xác minh
     */
    public function getLatestActiveOTP(string $email): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM TaiKhoanOTP 
                WHERE Email = ? AND TrangThai = 'ChoXacMinh' 
                ORDER BY MaOTP DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy bản ghi OTP vừa xác minh thành công gần nhất (đang chờ đặt mật khẩu mới, token chưa hết hạn)
     */
    public function getRecentlyVerifiedOTP(string $email): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM TaiKhoanOTP 
                WHERE Email = ? AND TrangThai = 'DaXacMinh' AND HetHanTokenLuc > NOW() 
                ORDER BY MaOTP DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Kiểm tra bản ghi OTP gần nhất có quá hạn so với đồng hồ DB hay không
     */
    public function isOTPExpired(int $maOTP): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT (HetHanLuc < NOW()) AS IsExpired FROM TaiKhoanOTP WHERE MaOTP = ?");
        $stmt->execute([$maOTP]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Tăng số lần thử nhập OTP sai; nếu đạt 5 lần thì chuyển trạng thái 'DaHuy'
     */
    public function incrementAttempts(int $maOTP): int
    {
        $db = Database::getConnection();
        
        // Tăng SoLanThu lên 1
        $sql = "UPDATE TaiKhoanOTP 
                SET SoLanThu = SoLanThu + 1, NgayCapNhat = NOW() 
                WHERE MaOTP = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maOTP]);

        // Lấy lại số lần thử
        $stmtCheck = $db->prepare("SELECT SoLanThu FROM TaiKhoanOTP WHERE MaOTP = ?");
        $stmtCheck->execute([$maOTP]);
        $attempts = (int)$stmtCheck->fetchColumn();

        if ($attempts >= 5) {
            $stmtCancel = $db->prepare("UPDATE TaiKhoanOTP SET TrangThai = 'DaHuy', NgayCapNhat = NOW() WHERE MaOTP = ?");
            $stmtCancel->execute([$maOTP]);
        }

        return $attempts;
    }

    /**
     * Đánh dấu OTP đã xác minh thành công và gán ResetToken (hạn 10 phút tính theo đồng hồ DB)
     */
    public function markOTPVerified(int $maOTP, string $resetToken, ?string $hetHanTokenLuc = null): bool
    {
        $db = Database::getConnection();
        $sql = "UPDATE TaiKhoanOTP 
                SET TrangThai = 'DaXacMinh', ResetToken = ?, HetHanTokenLuc = DATE_ADD(NOW(), INTERVAL 10 MINUTE), NgayCapNhat = NOW() 
                WHERE MaOTP = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$resetToken, $maOTP]);
    }

    /**
     * Tìm bản ghi OTP theo ResetToken (đang ở trạng thái DaXacMinh và chưa hết hạn token)
     */
    public function findActiveByResetToken(string $resetToken): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM TaiKhoanOTP 
                WHERE ResetToken = ? AND TrangThai = 'DaXacMinh' AND HetHanTokenLuc > NOW() 
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$resetToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cập nhật mật khẩu mới và vô hiệu hóa ResetToken trong Transaction
     */
    public function resetPasswordWithToken(string $email, string $resetToken, string $hashedPassword): bool
    {
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // 1. Cập nhật mật khẩu mới cho bảng TaiKhoan
            $stmtTK = $db->prepare("UPDATE TaiKhoan SET MatKhau = ? WHERE Email = ?");
            $resTK = $stmtTK->execute([$hashedPassword, $email]);
            if (!$resTK) {
                throw new \Exception("Cập nhật mật khẩu tài khoản thất bại.");
            }

            // 2. Vô hiệu hóa ResetToken và chuyển trạng thái thành DaSuDung
            $stmtOTP = $db->prepare("UPDATE TaiKhoanOTP 
                                     SET TrangThai = 'DaSuDung', ResetToken = NULL, NgayCapNhat = NOW() 
                                     WHERE ResetToken = ?");
            $resOTP = $stmtOTP->execute([$resetToken]);
            if (!$resOTP) {
                throw new \Exception("Vô hiệu hóa phiên đặt lại mật khẩu thất bại.");
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
