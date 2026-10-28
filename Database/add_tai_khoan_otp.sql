-- =========================================================
-- BẢNG LƯU TRỮ VÀ XÁC MINH OTP QUÊN MẬT KHẨU
-- =========================================================

USE hotel_booking;

CREATE TABLE IF NOT EXISTS TaiKhoanOTP (
    MaOTP INT AUTO_INCREMENT PRIMARY KEY,
    Email VARCHAR(255) NOT NULL,
    MaTK VARCHAR(50) NULL,
    OTPHash VARCHAR(255) NOT NULL,
    ResetToken VARCHAR(255) NULL,
    SoLanThu INT NOT NULL DEFAULT 0,
    TrangThai VARCHAR(50) NOT NULL DEFAULT 'ChoXacMinh',
    HetHanLuc DATETIME NOT NULL,
    HetHanTokenLuc DATETIME NULL,
    NgayTao DATETIME NOT NULL,
    NgayCapNhat DATETIME NOT NULL,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_email_trangthai (Email, TrangThai),
    INDEX idx_reset_token (ResetToken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
