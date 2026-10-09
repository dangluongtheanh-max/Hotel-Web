-- =========================================================
-- DATABASE: HOTEL BOOKING
-- =========================================================

DROP DATABASE IF EXISTS hotel_booking;
CREATE DATABASE hotel_booking
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
    SoNguoiToiThieu INT NOT NULL DEFAULT 0,
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

CREATE TABLE ChiTietDichVu (
    MaCTDV VARCHAR(50) PRIMARY KEY,
    MaDV VARCHAR(50) NOT NULL,
    TenCTDV VARCHAR(150) NOT NULL,
    DonGia DECIMAL(15,2) NOT NULL,
    SoLuongTon INT NOT NULL DEFAULT 0,
    FOREIGN KEY (MaDV) REFERENCES DichVu(MaDV)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (DonGia >= 0),
    CHECK (SoLuongTon >= 0)
) ENGINE=InnoDB;

CREATE TABLE CTSuDungDichVu (
    MaCTSDDV INT AUTO_INCREMENT PRIMARY KEY,
    MaCTDV VARCHAR(50) NOT NULL,
    MaPhieu VARCHAR(50) NOT NULL,
    MaPhong VARCHAR(50) NOT NULL,
    DonGia DECIMAL(15,2) NOT NULL,
    MoTa TEXT,
    SoLuong INT NOT NULL DEFAULT 1,
    TrangThai VARCHAR(50) NOT NULL,
    FOREIGN KEY (MaCTDV) REFERENCES ChiTietDichVu(MaCTDV)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (MaPhieu, MaPhong) REFERENCES ChiTietPhieuNhanPhong(MaPhieu, MaPhong)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (DonGia >= 0),
    CHECK (SoLuong > 0)
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
    MaCTDV VARCHAR(50),
    SoDem INT NOT NULL DEFAULT 0,
    SoLuong INT NOT NULL DEFAULT 1,
    DonGia DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (MaHD, MaPhieu) REFERENCES HoaDon(MaHD, MaPhieu)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (MaPhieu, MaPhong) REFERENCES ChiTietPhieuNhanPhong(MaPhieu, MaPhong)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (MaCTDV) REFERENCES ChiTietDichVu(MaCTDV)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CHECK (DonGia >= 0),
    CHECK (
        (MaPhong IS NOT NULL AND MaCTDV IS NULL AND SoDem > 0)
        OR (MaPhong IS NULL AND MaCTDV IS NOT NULL AND SoLuong > 0)
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


-- =========================================================
-- KIEM TRA
-- =========================================================

SHOW TABLES;


-- =========================================================
-- DATA SEED (DA BO SUNG 26 DICH VU THEO PHUONG AN A)
-- =========================================================

USE hotel_booking;

-- =========================================================
-- DATA MẪU CHO DATABASE HOTEL_BOOKING
-- (Đã được chuẩn hóa để khớp với hotel_booking.sql)
-- =========================================================

-- 1. TaiKhoan
INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro) VALUES
('TK001', 'admin', '123456', 'admin@hotelbooking.com', 'HoatDong', 'Admin'),
('TK002', 'letan01', '123456', 'hoa.letan@gmail.com', 'HoatDong', 'LeTan'),
('TK003', 'khach01', '123456', 'an@gmail.com', 'HoatDong', 'KhachHang'),
('TK004', 'letan02', '123456', 'lan.letan@gmail.com', 'HoatDong', 'LeTan');

-- 2. Admin
INSERT INTO Admin (MaAdmin, HoTen, SDT, Email, MaTK) VALUES
('AD001', 'Nguyen Van Admin', '0901000001', 'admin@hotelbooking.com', 'TK001');

-- 3. LeTan
INSERT INTO LeTan (MaLeTan, HoTen, SDT, Email, MaTK) VALUES
('LT001', 'Pham Thi Hoa', '0922000001', 'hoa.letan@gmail.com', 'TK002'),
('LT002', 'Nguyen Thi Lan', '0922000002', 'lan.letan@gmail.com', 'TK004');

-- 4. KhachHang
INSERT INTO KhachHang (MaKH, HoTen, SDT, Email, MaTK) VALUES
('KH001', 'Nguyen Van An', '0911000001', 'an@gmail.com', 'TK003'),
('KH002', 'Tran Thi Binh', '0911000002', 'binh@gmail.com', NULL),
('KH003', 'Le Minh Chau', '0911000003', 'chau@gmail.com', NULL);

-- 5. LoaiPhong
INSERT INTO LoaiPhong (MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi) VALUES
('LP001', 'Phong Standard', 500000, 2, 'Phong tieu chuan', 'WiFi, TV, Dieu hoa'),
('LP002', 'Phong Deluxe', 800000, 3, 'Phong cao cap', 'WiFi, TV, Dieu hoa, Tu lanh'),
('LP003', 'Phong Suite', 1200000, 4, 'Phong hang sang', 'WiFi, TV, Dieu hoa, Tu lanh, Bon tam');

-- 6. Phong
INSERT INTO Phong (MaPhong, TrangThaiPhong, MaLoaiPhong) VALUES
('P001', 'Trong', 'LP001'),
('P002', 'DangSuDung', 'LP002'),
('P003', 'DaDat', 'LP003'),
('P004', 'Trong', 'LP001');

-- 7. KhuyenMai
INSERT INTO KhuyenMai (MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, NgayBatDau, NgayKetThuc, TrangThai) VALUES
('KM001', 'Khuyen mai khach hang moi', 'PhanTram', 10, 0, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM002', 'Khuyen mai luu tru dai ngay', 'PhanTram', 15, 5, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM003', 'Khuyen mai mua he da ket thuc', 'SoTien', 200000, 2, '2025-06-01', '2025-08-31', 'NgungHoatDong'),
('KM_FLASH1212', 'Sieu Sale Flash 12/12 (Admin set rieng ngay 12/12)', 'PhanTram', 30, 1, '2026-12-12', '2026-12-12', 'HoatDong'),
('KM_WEEKEND', 'Uu dai nghi duong cuoi tuan', 'SoTien', 150000, 2, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM_VIP500', 'Voucher Tri An Khach VIP (>=3 dem)', 'SoTien', 500000, 3, '2026-01-01', '2026-12-31', 'HoatDong');

-- 8. PhieuNhanPhong
INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM) VALUES
('PP001', '2026-09-01', 'TrucTuyen', 'DaTraPhong', 'KH001', NULL, NULL),
('PP002', '2026-10-01', 'TrucTiep', 'DangLuuTru', 'KH002', 'LT001', NULL),
('PP003', '2026-10-01', 'TrucTiep', 'DaDat', 'KH003', 'LT002', 'KM002');

-- 9. ChiTietPhieuNhanPhong
INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai) VALUES
('PP001', 'P001', '2026-09-20 14:00:00', '2026-09-22 12:00:00', 2, 'DaTraPhong'),
('PP001', 'P004', '2026-09-20 14:00:00', '2026-09-22 12:00:00', 1, 'DaTraPhong'),
('PP002', 'P002', '2026-10-04 14:00:00', '2026-10-06 12:00:00', 2, 'DangLuuTru'),
('PP003', 'P003', '2026-10-10 14:00:00', '2026-10-20 12:00:00', 3, 'DaDat');

-- 10. DichVu (6 Nhom dich vu tieu chuan khach san)
INSERT INTO DichVu (MaDV, TenDV, MoTa, DonGia, TrangThai) VALUES
('DV01', 'Minibar & Do uong tai phong', 'Do uong va do an nhe trong tu lanh phong nghi', 0, 'DangCungCap'),
('DV02', 'Giat ui & La hoi', 'Dich vu giat say, la hoi, giat kho chuyen nghiep', 0, 'DangCungCap'),
('DV03', 'Am thuc tai phong (Room Service)', 'Cac suat an nong, do an nhanh phuc vu tan phong', 0, 'DangCungCap'),
('DV04', 'Spa & Massage thu gian', 'Dich vu tri lieu, xong hoi, massage thu gian', 0, 'DangCungCap'),
('DV05', 'Van chuyen & Thue phuong tien', 'Xe dua don san bay tron goi, thue xe may, xe dap', 0, 'DangCungCap'),
('DV06', 'Tien ich bo sung & Su kien phong', 'Giuong phu, trang tri phong, phong hop mini', 0, 'DangCungCap');

-- 10.1 ChiTietDichVu (26 Mat hang chi tiet theo 6 nhom)
INSERT INTO ChiTietDichVu (MaCTDV, MaDV, TenCTDV, DonGia, SoLuongTon) VALUES
-- Nhom DV01: Minibar & Do uong tai phong
('CTDV01', 'DV01', 'Nuoc suoi tinh khiet Aquafina 500ml', 20000, 200),
('CTDV02', 'DV01', 'Nuoc ngot Coca-Cola / Pepsi lon 330ml', 30000, 100),
('CTDV03', 'DV01', 'Bia Heineken / Tiger Silver lon 330ml', 45000, 100),
('CTDV04', 'DV01', 'Snack khoai tay Lays lon 110g', 35000, 80),
('CTDV05', 'DV01', 'Hat dieu rang muoi / Macca say 150g', 80000, 50),
('CTDV06', 'DV01', 'Ruou vang do nhap khau Chile 750ml', 450000, 30),

-- Nhom DV02: Giat ui & La hoi
('CTDV07', 'DV02', 'Giat say quan ao thuong (theo kg)', 40000, 9999),
('CTDV08', 'DV02', 'La hoi / Ui phang ao somi, quan tay', 30000, 9999),
('CTDV09', 'DV02', 'Giat kho / Giat hap bo vest, dam da hoi', 120000, 9999),
('CTDV10', 'DV02', 'Phu thu giat hoa toc nhan trong 3 gio', 80000, 9999),

-- Nhom DV03: Am thuc tai phong (Room Service)
('CTDV11', 'DV03', 'Bua sang kieu Au (Banh mi, trung, xuc xich, cafe)', 120000, 50),
('CTDV12', 'DV03', 'Bua sang truyen thong (Pho bo dac biet / Bun bo Hue)', 95000, 50),
('CTDV13', 'DV03', 'Set com nieu gia dinh (Com nieu, suon ram, canh cua)', 180000, 40),
('CTDV14', 'DV03', 'Pizza hai san pho mai co vua 20cm', 160000, 30),
('CTDV15', 'DV03', 'Dia trai cay tuoi nhiet doi 4 mua', 85000, 40),

-- Nhom DV04: Spa & Massage thu gian
('CTDV16', 'DV04', 'Xong hoi da muoi Himalaya (45 phut)', 150000, 20),
('CTDV17', 'DV04', 'Massage toan than tinh dau sa chanh (60 phut)', 350000, 15),
('CTDV18', 'DV04', 'Massage tri lieu co vai gay chuyen sau (45 phut)', 250000, 15),
('CTDV19', 'DV04', 'Cham soc da mat thu gian bun khoang (60 phut)', 300000, 10),

-- Nhom DV05: Van chuyen & Thue phuong tien (Phuong an A - tron goi & theo ngay/gio)
('CTDV20', 'DV05', 'Thue xe may tay ga Honda AirBlade (theo ngay)', 150000, 15),
('CTDV21', 'DV05', 'Thue xe dap doi dao pho (theo gio)', 50000, 10),
('CTDV22', 'DV05', 'Xe 4 cho dua don san bay tron goi (1 chieu)', 250000, 10),
('CTDV23', 'DV05', 'Xe 7 cho dua don san bay tron goi (1 chieu)', 350000, 8),

-- Nhom DV06: Tien ich bo sung & Su kien phong
('CTDV24', 'DV06', 'Ke them giuong phu Extra Bed (theo dem)', 200000, 10),
('CTDV25', 'DV06', 'Set trang tri phong sinh nhat / tinh yeu lang man', 350000, 10),
('CTDV26', 'DV06', 'Thue phong hop hoi nghi mini < 15 nguoi (theo gio)', 500000, 5);

-- 10.2 CTSuDungDichVu
INSERT INTO CTSuDungDichVu (MaCTDV, MaPhieu, MaPhong, DonGia, MoTa, SoLuong, TrangThai) VALUES
('CTDV01', 'PP001', 'P001', 120000, 'Khach an buffet', 2, 'HoanThanh'),
('CTDV02', 'PP001', 'P001', 50000, 'Giat 1 ao somi', 1, 'HoanThanh');
-- 11. HoaDon
INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES
('HD001', 'PP001', 2519000),
('HD002', 'PP002', 2024000),
('HD003', 'PP003', 11220000);

-- 12. CTHD
INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES
('HD001', 'PP001', 'P001', NULL, 2, 1, 500000),
('HD001', 'PP001', 'P004', NULL, 2, 1, 500000),
('HD001', 'PP001', NULL, 'CTDV01', 0, 2, 120000),
('HD001', 'PP001', NULL, 'CTDV02', 0, 1, 50000),
('HD002', 'PP002', 'P002', NULL, 2, 1, 800000),
('HD002', 'PP002', NULL, 'CTDV01', 0, 2, 120000),
('HD003', 'PP003', 'P003', NULL, 10, 1, 1200000);

-- 13. DanhGia
INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu) VALUES
('DG001', 5, 'Phong sach se, nhan vien than thien.', '2026-09-22', 'KH001', 'PP001');

-- 14. ThanhToan
INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES
('TT001', 2519000, '2026-09-22 12:30:00', 'ChuyenKhoan', 'HoanTat', 'HD001'),
('TT002', 500000, '2026-10-01 10:00:00', 'TienMat', 'HoanTat', 'HD002'),
('TT003', 2000000, '2026-10-01 11:00:00', 'ChuyenKhoan', 'HoanTat', 'HD003');
