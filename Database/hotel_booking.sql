-- =========================================================
-- DATABASE: HOTEL BOOKING
-- =========================================================

CREATE DATABASE IF NOT EXISTS hotel_booking
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hotel_booking;

CREATE TABLE TaiKhoan (
    MaTK VARCHAR(50) PRIMARY KEY,
    TenDangNhap VARCHAR(100) NOT NULL UNIQUE,
    MatKhau VARCHAR(255) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    TrangThaiTaiKhoan VARCHAR(50) NOT NULL,
    VaiTro VARCHAR(50) NOT NULL,
    CHECK (VaiTro IN ('Admin', 'LeTan', 'KhachHang'))
) ENGINE=InnoDB;

CREATE TABLE Admin (
    MaAdmin VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20),
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE LeTan (
    MaLeTan VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20) NOT NULL,
    Email VARCHAR(255),
    MaTK VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE KhachHang (
    MaKH VARCHAR(50) PRIMARY KEY,
    HoTen VARCHAR(100) NOT NULL,
    SDT VARCHAR(20) NOT NULL,
    Email VARCHAR(255),
    MaTK VARCHAR(50) UNIQUE,
    FOREIGN KEY (MaTK) REFERENCES TaiKhoan(MaTK)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE LoaiPhong (
    MaLoaiPhong VARCHAR(50) PRIMARY KEY,
    TenLoaiPhong VARCHAR(100) NOT NULL,
    Gia DECIMAL(15,2) NOT NULL,
    SoNguoiToiDa INT NOT NULL,
    MoTa TEXT,
    TienNghi TEXT,
    CHECK (Gia >= 0),
    CHECK (SoNguoiToiDa > 0)
) ENGINE=InnoDB;

CREATE TABLE Phong (
    MaPhong VARCHAR(50) PRIMARY KEY,
    TrangThaiPhong VARCHAR(50) NOT NULL,
    MaLoaiPhong VARCHAR(50) NOT NULL,
    FOREIGN KEY (MaLoaiPhong) REFERENCES LoaiPhong(MaLoaiPhong)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (TrangThaiPhong IN ('Trong', 'DaDat', 'DangSuDung', 'BaoTri'))
) ENGINE=InnoDB;

CREATE TABLE KhuyenMai (
    MaKM VARCHAR(50) PRIMARY KEY,
    TenKM VARCHAR(150) NOT NULL,
    LoaiKM VARCHAR(50) NOT NULL,
    GiaTriGiam DECIMAL(15,2) NOT NULL,
    SoDemToiThieu INT NOT NULL DEFAULT 0,
    NgayBatDau DATE NOT NULL,
    NgayKetThuc DATE NOT NULL,
    TrangThai VARCHAR(50) NOT NULL DEFAULT 'HoatDong',
    CHECK (GiaTriGiam >= 0),
    CHECK (SoDemToiThieu >= 0),
    CHECK (NgayKetThuc >= NgayBatDau)
) ENGINE=InnoDB;

CREATE TABLE PhieuNhanPhong (
    MaPhieu VARCHAR(50) PRIMARY KEY,
    NgayLap DATE NOT NULL,
    KenhDat VARCHAR(20) NOT NULL,
    TrangThai VARCHAR(50) NOT NULL,
    MaKH VARCHAR(50) NOT NULL,
    MaLeTan VARCHAR(50),
    MaKM VARCHAR(50),
    FOREIGN KEY (MaKH) REFERENCES KhachHang(MaKH)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (MaLeTan) REFERENCES LeTan(MaLeTan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (MaKM) REFERENCES KhuyenMai(MaKM)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CHECK (KenhDat IN ('TrucTuyen', 'TrucTiep')),
    CHECK (TrangThai IN ('ChoXacNhan', 'DaDat', 'DaNhanPhong', 'DangLuuTru', 'DaTraPhong', 'DaHuy', 'TuChoi')),
    CHECK (
        (KenhDat = 'TrucTuyen' AND MaLeTan IS NULL)
        OR (KenhDat = 'TrucTiep' AND MaLeTan IS NOT NULL)
    ),
    UNIQUE (MaPhieu, MaKH)
) ENGINE=InnoDB;

CREATE TABLE ChiTietPhieuNhanPhong (
    MaPhieu VARCHAR(50) NOT NULL,
    MaPhong VARCHAR(50) NOT NULL,
    ThoiGianNhanPhong DATETIME NOT NULL,
    ThoiGianTraPhong DATETIME NOT NULL,
    SoNguoi INT NOT NULL,
    TrangThai VARCHAR(50) NOT NULL,
    PRIMARY KEY (MaPhieu, MaPhong),
    FOREIGN KEY (MaPhieu) REFERENCES PhieuNhanPhong(MaPhieu)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (MaPhong) REFERENCES Phong(MaPhong)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (ThoiGianTraPhong > ThoiGianNhanPhong),
    CHECK (SoNguoi > 0),
    CHECK (TrangThai IN ('DaDat', 'DaNhanPhong', 'DangLuuTru', 'DaTraPhong', 'TuChoi'))
) ENGINE=InnoDB;

CREATE TABLE DichVu (
    MaDV VARCHAR(50) PRIMARY KEY,
    TenDV VARCHAR(150) NOT NULL,
    MoTa TEXT,
    DonGia DECIMAL(15,2) NOT NULL,
    TrangThai VARCHAR(50) NOT NULL DEFAULT 'DangCungCap',
    CHECK (DonGia >= 0)
) ENGINE=InnoDB;

CREATE TABLE HoaDon (
    MaHD VARCHAR(50) PRIMARY KEY,
    MaPhieu VARCHAR(50) NOT NULL UNIQUE,
    TongTienSauVAT DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (MaPhieu) REFERENCES PhieuNhanPhong(MaPhieu)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE (MaHD, MaPhieu),
    CHECK (TongTienSauVAT >= 0)
) ENGINE=InnoDB;

CREATE TABLE CTHD (
    MaCTHD INT AUTO_INCREMENT PRIMARY KEY,
    MaHD VARCHAR(50) NOT NULL,
    MaPhieu VARCHAR(50) NOT NULL,
    MaPhong VARCHAR(50),
    MaDV VARCHAR(50),
    SoDem INT NOT NULL DEFAULT 0,
    SoLuong INT NOT NULL DEFAULT 1,
    DonGia DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (MaHD, MaPhieu) REFERENCES HoaDon(MaHD, MaPhieu)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (MaPhieu, MaPhong) REFERENCES ChiTietPhieuNhanPhong(MaPhieu, MaPhong)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (MaDV) REFERENCES DichVu(MaDV)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (DonGia >= 0),
    CHECK (
        (MaPhong IS NOT NULL AND MaDV IS NULL AND SoDem > 0)
        OR (MaPhong IS NULL AND MaDV IS NOT NULL AND SoLuong > 0)
    )
) ENGINE=InnoDB;

CREATE TABLE DanhGia (
    MaDanhGia VARCHAR(50) PRIMARY KEY,
    SoSao INT NOT NULL,
    NoiDung TEXT,
    NgayDanhGia DATE NOT NULL,
    MaKH VARCHAR(50) NOT NULL,
    MaPhieu VARCHAR(50) NOT NULL UNIQUE,
    FOREIGN KEY (MaPhieu, MaKH) REFERENCES PhieuNhanPhong(MaPhieu, MaKH)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (SoSao BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE ThanhToan (
    MaThanhToan VARCHAR(50) PRIMARY KEY,
    SoTien DECIMAL(15,2) NOT NULL,
    ThoiGianThanhToan DATETIME NOT NULL,
    HinhThuc VARCHAR(30) NOT NULL,
    TrangThaiThanhToan VARCHAR(50) NOT NULL,
    MaHD VARCHAR(50) NOT NULL,
    FOREIGN KEY (MaHD) REFERENCES HoaDon(MaHD)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (SoTien > 0),
    CHECK (HinhThuc IN ('ChuyenKhoan', 'TienMat'))
) ENGINE=InnoDB;

SHOW TABLES;
