-- Migration: Nâng cấp Loại Phòng (ảnh nhiều tấm, thư viện tiện nghi, thông số, đặc quyền, chính sách)
-- Idempotent: có thể chạy lại nhiều lần mà không gây lỗi (MariaDB 10.4+ / MySQL).
-- Không xóa hay đổi tên cột/bảng cũ.

-- 1. Bảng thư viện tiện nghi (tien_nghi)
CREATE TABLE IF NOT EXISTS tien_nghi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ten VARCHAR(150) NOT NULL,
    nhom VARCHAR(100) NOT NULL,
    icon VARCHAR(32) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tien_nghi_ten (ten),
    INDEX idx_tien_nghi_nhom (nhom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed danh sách tiện nghi mặc định theo 5 nhóm
INSERT IGNORE INTO tien_nghi (ten, nhom, icon) VALUES
    -- Phòng ngủ & Thư giãn
    ('Điều hòa', 'Phòng ngủ & Thư giãn', '❄️'),
    ('Quạt', 'Phòng ngủ & Thư giãn', '🌀'),
    ('Bàn làm việc', 'Phòng ngủ & Thư giãn', '💼'),
    ('Móc treo quần áo', 'Phòng ngủ & Thư giãn', '🧥'),
    ('Giường đơn êm ái', 'Phòng ngủ & Thư giãn', '🛏️'),
    ('Giường đôi King Size', 'Phòng ngủ & Thư giãn', '👑'),
    ('Rèm cản sáng tự động', 'Phòng ngủ & Thư giãn', '🪟'),
    ('Ban công riêng', 'Phòng ngủ & Thư giãn', '🌿'),
    -- Phòng tắm & Chăm sóc cá nhân
    ('Vòi sen nóng lạnh', 'Phòng tắm & Chăm sóc cá nhân', '🚿'),
    ('Khăn tắm & xà phòng', 'Phòng tắm & Chăm sóc cá nhân', '🧼'),
    ('Gương phòng tắm', 'Phòng tắm & Chăm sóc cá nhân', '🪞'),
    ('Máy sấy tóc', 'Phòng tắm & Chăm sóc cá nhân', '💨'),
    ('Bồn tắm nằm', 'Phòng tắm & Chăm sóc cá nhân', '🛁'),
    ('Áo choàng tắm', 'Phòng tắm & Chăm sóc cá nhân', '👘'),
    -- Công nghệ & Giải trí
    ('WiFi miễn phí', 'Công nghệ & Giải trí', '📶'),
    ('Ổ cắm sạc tiện lợi', 'Công nghệ & Giải trí', '🔌'),
    ('Smart TV màn hình phẳng', 'Công nghệ & Giải trí', '📺'),
    ('Loa Bluetooth', 'Công nghệ & Giải trí', '🔊'),
    ('Máy chơi game PS5', 'Công nghệ & Giải trí', '🎮'),
    -- Ẩm thực & Thức uống
    ('Nước uống đóng chai', 'Ẩm thực & Thức uống', '💧'),
    ('Ly uống nước cá nhân', 'Ẩm thực & Thức uống', '🥛'),
    ('Minibar', 'Ẩm thực & Thức uống', '🍾'),
    ('Máy pha cà phê / Trà', 'Ẩm thực & Thức uống', '☕'),
    ('Ấm đun siêu tốc', 'Ẩm thực & Thức uống', '🫖'),
    -- Tiện ích & Dịch vụ an toàn
    ('Dọn phòng hàng ngày', 'Tiện ích & Dịch vụ an toàn', '🧹'),
    ('Hỗ trợ đặt đồ ăn & thuê xe máy', 'Tiện ích & Dịch vụ an toàn', '🛵'),
    ('Két sắt an toàn', 'Tiện ích & Dịch vụ an toàn', '🔐'),
    ('Khóa thẻ từ thông minh', 'Tiện ích & Dịch vụ an toàn', '💳'),
    ('Lễ tân hỗ trợ 24/7', 'Tiện ích & Dịch vụ an toàn', '🛎️');

-- 2. Bảng nối loai_phong_tien_nghi
CREATE TABLE IF NOT EXISTS loai_phong_tien_nghi (
    ma_loai_phong VARCHAR(50) NOT NULL,
    tien_nghi_id INT NOT NULL,
    PRIMARY KEY (ma_loai_phong, tien_nghi_id),
    CONSTRAINT fk_lptn_loaiphong FOREIGN KEY (ma_loai_phong)
        REFERENCES LoaiPhong(MaLoaiPhong) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lptn_tiennghi FOREIGN KEY (tien_nghi_id)
        REFERENCES tien_nghi(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng ảnh loại phòng (loai_phong_anh)
CREATE TABLE IF NOT EXISTS loai_phong_anh (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ma_loai_phong VARCHAR(50) NOT NULL,
    duong_dan VARCHAR(255) NOT NULL,
    la_anh_dai_dien TINYINT(1) NOT NULL DEFAULT 0,
    thu_tu INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lpa_ma_loai_phong (ma_loai_phong, la_anh_dai_dien, thu_tu),
    CONSTRAINT fk_lpa_loaiphong FOREIGN KEY (ma_loai_phong)
        REFERENCES LoaiPhong(MaLoaiPhong) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Mở rộng bảng LoaiPhong (giữ nguyên cột cũ, tất cả cột mới cho phép NULL)
ALTER TABLE LoaiPhong
    ADD COLUMN IF NOT EXISTS tom_tat VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS dien_tich VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS tang VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS bo_tri_giuong VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS huong_view VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS tien_nghi_phong_tam VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS quy_dinh_hut_thuoc VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS diem_nhan TEXT NULL,
    ADD COLUMN IF NOT EXISTS dac_quyen TEXT NULL,
    ADD COLUMN IF NOT EXISTS gio_nhan_phong VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS gio_tra_phong VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS chinh_sach_huy TEXT NULL,
    ADD COLUMN IF NOT EXISTS chinh_sach_tre_em TEXT NULL,
    ADD COLUMN IF NOT EXISTS phuong_thuc_coc_thanh_toan TEXT NULL;

