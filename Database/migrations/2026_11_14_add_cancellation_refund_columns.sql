-- Migration: Bổ sung đầy đủ cột theo dõi hủy phòng & hoàn tiền cho PhieuNhanPhong, ChiTietPhieuNhanPhong, ThanhToan
-- Lưu ý: Với MySQL 8.0+ (không hỗ trợ ADD COLUMN IF NOT EXISTS), hãy chạy script PHP:
--   php Database/migrate_cancellation_refund.php
-- Hoặc chạy thủ công từng câu ALTER bên dưới nếu cột chưa tồn tại.

ALTER TABLE PhieuNhanPhong
    ADD COLUMN ThoiGianHuy DATETIME NULL AFTER LyDoHuy,
    ADD COLUMN TyLeHoan DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER ThoiGianHuy,
    ADD COLUMN SoTienHoan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TyLeHoan;

ALTER TABLE ChiTietPhieuNhanPhong
    ADD COLUMN ThoiGianHuy DATETIME NULL AFTER LyDoHuy,
    ADD COLUMN TyLeHoan DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER ThoiGianHuy,
    ADD COLUMN PhiHuy DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TyLeHoan,
    ADD COLUMN SoTienHoan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER PhiHuy;

ALTER TABLE ThanhToan
    ADD COLUMN SoTienHoan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER TrangThaiThanhToan,
    ADD COLUMN NganHangHoan VARCHAR(100) NULL DEFAULT NULL AFTER SoTienHoan,
    ADD COLUMN STKHoan VARCHAR(50) NULL DEFAULT NULL AFTER NganHangHoan,
    ADD COLUMN TenChuTKHoan VARCHAR(150) NULL DEFAULT NULL AFTER STKHoan,
    ADD COLUMN LyDoHoan TEXT NULL AFTER TenChuTKHoan,
    ADD COLUMN ThoiGianHoan DATETIME NULL DEFAULT NULL AFTER LyDoHoan;
