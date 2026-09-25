-- =========================================================
-- DATABASE: HOTEL BOOKING
-- File: Database/hotel_booking.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS hotel_booking
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE hotel_booking;


-- =========================================================
-- 1. TAI KHOAN
-- =========================================================

CREATE TABLE TaiKhoan (
    MaTK VARCHAR(50) PRIMARY KEY,
    TenDangNhap VARCHAR(100) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    TrangThaiTaiKhoan VARCHAR(50) NOT NULL,
    VaiTro VARCHAR(50) NOT NULL
) ENGINE=InnoDB;


-- =========================================================
-- 2. ADMIN
-- =========================================================

CREATE TABLE Admin (
    MaAdmin VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20),
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,

    FOREIGN KEY (MaTK)
        REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 3. CHI NHANH
-- =========================================================

CREATE TABLE ChiNhanh (
    MaChiNhanh VARCHAR(50) PRIMARY KEY,
    TenChiNhanh VARCHAR(150) NOT NULL,
    KhuVuc VARCHAR(100),
    DiaChi VARCHAR(255),
    MaAdmin VARCHAR(50) NOT NULL UNIQUE,

    FOREIGN KEY (MaAdmin)
        REFERENCES Admin(MaAdmin)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 4. KHACH HANG
-- =========================================================

CREATE TABLE KhachHang (
    MaKH VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20),
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,

    FOREIGN KEY (MaTK)
        REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 5. LE TAN
-- =========================================================

CREATE TABLE LeTan (
    MaLeTan VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20),
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,
    MaChiNhanh VARCHAR(50) NOT NULL,

    FOREIGN KEY (MaTK)
        REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaChiNhanh)
        REFERENCES ChiNhanh(MaChiNhanh)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 6. QUAN LY
-- =========================================================

CREATE TABLE QuanLy (
    MaQuanLy VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20),
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,
    MaChiNhanh VARCHAR(50) NOT NULL,

    FOREIGN KEY (MaTK)
        REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaChiNhanh)
        REFERENCES ChiNhanh(MaChiNhanh)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 7. LOAI PHONG
-- =========================================================

CREATE TABLE LoaiPhong (
    MaLoaiPhong VARCHAR(50) PRIMARY KEY,
    TenLoaiPhong VARCHAR(100) NOT NULL,
    Gia DECIMAL(15,2) NOT NULL,
    SoNguoiToiDa INT NOT NULL,
    MoTa TEXT,
    TienNghi TEXT
) ENGINE=InnoDB;


-- =========================================================
-- 8. PHONG
-- =========================================================

CREATE TABLE Phong (
    MaPhong VARCHAR(50) NOT NULL,
    TrangThaiPhong VARCHAR(50) NOT NULL,
    MaLoaiPhong VARCHAR(50) NOT NULL,
    MaChiNhanh VARCHAR(50) NOT NULL,

    PRIMARY KEY (MaPhong, MaChiNhanh),

    FOREIGN KEY (MaLoaiPhong)
        REFERENCES LoaiPhong(MaLoaiPhong)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaChiNhanh)
        REFERENCES ChiNhanh(MaChiNhanh)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 9. KHUYEN MAI
-- =========================================================

CREATE TABLE KhuyenMai (
    MaKM VARCHAR(50) PRIMARY KEY,
    TenKM VARCHAR(150) NOT NULL,
    LoaiKM VARCHAR(50) NOT NULL,
    GiaTriGiam DECIMAL(15,2) NOT NULL,
    SoDemToiThieu INT NOT NULL
) ENGINE=InnoDB;


-- =========================================================
-- 10. PHIEU NHAN PHONG
-- =========================================================

CREATE TABLE PhieuNhanPhong (
    MaPhieu VARCHAR(50) PRIMARY KEY,
    NgayLap DATE NOT NULL,
    ThoiGianNhanPhong DATETIME NOT NULL,
    ThoiGianTraPhong DATETIME NOT NULL,
    SoNguoi INT NOT NULL,
    TrangThai VARCHAR(50) NOT NULL,

    MaKH VARCHAR(50) NOT NULL,
    MaPhong VARCHAR(50) NOT NULL,
    MaLeTan VARCHAR(50),
    MaKM VARCHAR(50),
    MaChiNhanh VARCHAR(50) NOT NULL,

    FOREIGN KEY (MaKH)
        REFERENCES KhachHang(MaKH)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaPhong, MaChiNhanh)
        REFERENCES Phong(MaPhong, MaChiNhanh)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaLeTan)
        REFERENCES LeTan(MaLeTan)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    FOREIGN KEY (MaKM)
        REFERENCES KhuyenMai(MaKM)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- =========================================================
-- 11. DANH GIA
-- =========================================================

CREATE TABLE DanhGia (
    MaDanhGia VARCHAR(50) PRIMARY KEY,
    SoSao INT NOT NULL,
    NoiDung TEXT,
    NgayDanhGia DATE NOT NULL,
    MaKH VARCHAR(50) NOT NULL,
    MaPhieu VARCHAR(50) NOT NULL,

    FOREIGN KEY (MaKH)
        REFERENCES KhachHang(MaKH)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (MaPhieu)
        REFERENCES PhieuNhanPhong(MaPhieu)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 12. HOA DON
-- =========================================================

CREATE TABLE HoaDon (
    MaHD VARCHAR(50) PRIMARY KEY,
    MaKH VARCHAR(50) NOT NULL,
    TongTienSauVAT DECIMAL(15,2) NOT NULL,

    FOREIGN KEY (MaKH)
        REFERENCES KhachHang(MaKH)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 13. CHI TIET HOA DON
-- =========================================================

CREATE TABLE CTHD (
    MaCTHD INT AUTO_INCREMENT PRIMARY KEY,
    MaHD VARCHAR(50) NOT NULL,
    MaChiNhanh VARCHAR(50) NOT NULL,
    MaPhong VARCHAR(50) NOT NULL,
    SoDem INT NOT NULL,
    DonGia DECIMAL(15,2) NOT NULL,

    FOREIGN KEY (MaHD)
        REFERENCES HoaDon(MaHD)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (MaPhong, MaChiNhanh)
        REFERENCES Phong(MaPhong, MaChiNhanh)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- 14. THANH TOAN
-- =========================================================

CREATE TABLE ThanhToan (
    MaThanhToan VARCHAR(50) PRIMARY KEY,
    SoTien DECIMAL(15,2) NOT NULL,
    ThoiGianThanhToan DATETIME NOT NULL,
    TrangThaiThanhToan VARCHAR(50) NOT NULL,
    MaHD VARCHAR(50) NOT NULL,

    FOREIGN KEY (MaHD)
        REFERENCES HoaDon(MaHD)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- =========================================================
-- KIEM TRA
-- =========================================================

SHOW TABLES;
