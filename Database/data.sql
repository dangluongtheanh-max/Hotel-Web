USE hotel_booking;

-- =========================================================
-- DATA MẪU CHO DATABASE HOTEL_BOOKING
-- Mỗi bảng: 3 dữ liệu
-- Lưu ý:
--   - Hệ thống chỉ có 01 tài khoản Admin duy nhất.
--   - Có 03 chi nhánh: HCM01, HN01, DN01.
--   - MaChiNhanh được cập nhật đồng bộ ở các bảng có FK.
-- =========================================================

-- 1. TaiKhoan
-- TK001 là tài khoản Admin duy nhất
INSERT INTO TaiKhoan
(MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro)
VALUES
('TK001', 'admin', '123456', 'admin@hotelbooking.com', 'HoatDong', 'Admin'),
('TK002', 'letan01', '123456', 'hoa.letan@gmail.com', 'HoatDong', 'LeTan'),
('TK003', 'khach01', '123456', 'an@gmail.com', 'HoatDong', 'KhachHang'),
('TK004', 'letan02', '123456', 'lan.letan@gmail.com', 'HoatDong', 'LeTan');

INSERT INTO Admin (MaAdmin, HoTen, SDT, Email, MaTK)
VALUES ('AD001', 'Nguyen Van Admin', '0901000001', 'admin@hotelbooking.com', 'TK001');

INSERT INTO LeTan (MaLeTan, HoTen, SDT, Email, MaTK)
VALUES
('LT001', 'Pham Thi Hoa', '0922000001', 'hoa.letan@gmail.com', 'TK002'),
('LT002', 'Nguyen Thi Lan', '0922000002', 'lan.letan@gmail.com', 'TK004');

INSERT INTO KhachHang (MaKH, HoTen, SDT, Email, MaTK)
('TK002', 'letan01', '123456', 'letan01@gmail.com', 'HoatDong', 'LeTan'),
('TK003', 'khach01', '123456', 'khach01@gmail.com', 'HoatDong', 'KhachHang');

-- 2. Admin
-- Hệ thống chỉ có 01 Admin
INSERT INTO Admin
(MaAdmin, HoTen, SDT, Email, MaTK)
VALUES
('AD001', 'Nguyen Van Admin', '0901000001', 'admin@hotelbooking.com', 'TK001');

-- 3. ChiNhanh
INSERT INTO ChiNhanh
(MaChiNhanh, TenChiNhanh, KhuVuc, DiaChi, MaAdmin)
VALUES
('HCM01', 'HCM', 'TP. Ho Chi Minh', '123 Nguyen Hue, Quan 1, TP. Ho Chi Minh', 'AD001'),
('HN01', 'HN', 'Ha Noi', '123 Hang Bong, Hoan Kiem, Ha Noi', 'AD001'),
('DN01', 'Da Nang', 'Da Nang', '123 Bach Dang, Hai Chau, Da Nang', 'AD001');

-- 4. KhachHang
INSERT INTO KhachHang
(MaKH, HoTen, SDT, Email, MaTK)
VALUES
('KH001', 'Nguyen Van An', '0911000001', 'an@gmail.com', 'TK003'),
('KH002', 'Tran Thi Binh', '0911000002', 'binh@gmail.com', NULL),
('KH003', 'Le Minh Chau', '0911000003', 'chau@gmail.com', NULL);

-- 5. LeTan
INSERT INTO LeTan
(MaLeTan, HoTen, SDT, Email, MaTK, MaChiNhanh)
VALUES
('LT001', 'Pham Thi Hoa', '0922000001', 'hoa.letan@gmail.com', 'TK002', 'HCM01'),
('LT002', 'Nguyen Thi Lan', '0922000002', 'lan.letan@gmail.com', NULL, 'HN01'),
('LT003', 'Tran Van Nam', '0922000003', 'nam.letan@gmail.com', NULL, 'DN01');

-- 6. QuanLy
INSERT INTO QuanLy
(MaQuanLy, HoTen, SDT, Email, MaTK, MaChiNhanh)
VALUES
('QL001', 'Nguyen Van Hung', '0933000001', 'hung.quanly@gmail.com', NULL, 'HCM01'),
('QL002', 'Tran Thi Mai', '0933000002', 'mai.quanly@gmail.com', NULL, 'HN01'),
('QL003', 'Le Quang Minh', '0933000003', 'minh.quanly@gmail.com', NULL, 'DN01');

-- 7. LoaiPhong
INSERT INTO LoaiPhong
(MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi)
VALUES
('LP001', 'Phong Standard', 500000, 2, 'Phong tieu chuan', 'WiFi, TV, Dieu hoa'),
('LP002', 'Phong Deluxe', 800000, 3, 'Phong cao cap', 'WiFi, TV, Dieu hoa, Tu lanh'),
('LP003', 'Phong Suite', 1200000, 4, 'Phong hang sang', 'WiFi, TV, Dieu hoa, Tu lanh, Bon tam');

INSERT INTO Phong (MaPhong, TrangThaiPhong, MaLoaiPhong)
VALUES
('P001', 'Trong', 'LP001'),
('P002', 'DangSuDung', 'LP002'),
('P003', 'DaDat', 'LP003'),
('P004', 'Trong', 'LP001');

INSERT INTO KhuyenMai
(MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, NgayBatDau, NgayKetThuc, TrangThai)
VALUES
('KM001', 'Khuyen mai khach hang moi', 'PhanTram', 10, 0, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM002', 'Khuyen mai luu tru dai ngay', 'PhanTram', 15, 5, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM003', 'Khuyen mai mua he', 'SoTien', 200000, 2, '2026-06-01', '2026-08-31', 'NgungHoatDong');

INSERT INTO PhieuNhanPhong
(MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM)
VALUES
('PP001', '2026-09-01', 'TrucTuyen', 'DaTraPhong', 'KH001', NULL, NULL),
('PP002', '2026-10-01', 'TrucTiep', 'DangLuuTru', 'KH002', 'LT001', NULL),
('PP003', '2026-10-01', 'TrucTiep', 'DaDat', 'KH003', 'LT002', 'KM002');

INSERT INTO ChiTietPhieuNhanPhong
(MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
VALUES
('PP001', 'P001', '2026-09-20 14:00:00', '2026-09-22 12:00:00', 2, 'DaTraPhong'),
('PP001', 'P004', '2026-09-20 14:00:00', '2026-09-22 12:00:00', 1, 'DaTraPhong'),
('PP002', 'P002', '2026-10-04 14:00:00', '2026-10-06 12:00:00', 2, 'DangLuuTru'),
('PP003', 'P003', '2026-10-10 14:00:00', '2026-10-20 12:00:00', 3, 'DaDat');

INSERT INTO DichVu (MaDV, TenDV, MoTa, DonGia, TrangThai)
VALUES
('DV001', 'Bua sang', 'Suat an sang tai khach san', 120000, 'DangCungCap'),
('DV002', 'Giat ui', 'Dich vu giat ui theo lan', 50000, 'DangCungCap'),
('DV003', 'Dua don san bay', 'Dich vu dua don mot chieu', 250000, 'DangCungCap');

INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT)
VALUES
('HD001', 'PP001', 2519000),
('HD002', 'PP002', 2024000),
('HD003', 'PP003', 11220000);

INSERT INTO CTHD
(MaHD, MaPhieu, MaPhong, MaDV, SoDem, SoLuong, DonGia)
VALUES
('HD001', 'PP001', 'P001', NULL, 2, 1, 500000),
('HD001', 'PP001', 'P004', NULL, 2, 1, 500000),
('HD001', 'PP001', NULL, 'DV001', 0, 2, 120000),
('HD001', 'PP001', NULL, 'DV002', 0, 1, 50000),
('HD002', 'PP002', 'P002', NULL, 2, 1, 800000),
('HD002', 'PP002', NULL, 'DV001', 0, 2, 120000),
('HD003', 'PP003', 'P003', NULL, 10, 1, 1200000);

INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu)
VALUES
('DG001', 5, 'Phong sach se, nhan vien than thien.', '2026-09-22', 'KH001', 'PP001');

INSERT INTO ThanhToan
(MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
VALUES
('TT001', 2519000, '2026-09-22 12:30:00', 'ChuyenKhoan', 'DaThanhToan', 'HD001'),
('TT002', 500000, '2026-10-01 10:00:00', 'TienMat', 'DaThanhToan', 'HD002'),
('TT003', 2000000, '2026-10-01 11:00:00', 'ChuyenKhoan', 'DaThanhToan', 'HD003');
-- 8. Phong
-- Khóa chính kép: (MaPhong, MaChiNhanh)
INSERT INTO Phong
(MaPhong, TrangThaiPhong, MaLoaiPhong, MaChiNhanh)
VALUES
('P001', 'Trong', 'LP001', 'HCM01'),
('P002', 'Da dat', 'LP002', 'HCM01'),
('P003', 'Trong', 'LP003', 'HN01');

-- 9. KhuyenMai
INSERT INTO KhuyenMai
(MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu)
VALUES
('KM001', 'Khuyen mai khach hang moi', 'KhachHangMoi', 10, 0),
('KM002', 'Khuyen mai 5 dem', 'TheoSoDem', 15, 5),
('KM003', 'Khuyen mai 10 dem', 'TheoSoDem', 25, 10);

-- 10. PhieuNhanPhong
INSERT INTO PhieuNhanPhong
(MaPhieu, NgayLap, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai, MaKH, MaPhong, MaLeTan, MaKM, MaChiNhanh)
VALUES
('PP001', '2026-09-20', '2026-09-20 14:00:00', '2026-09-22 12:00:00', 2, 'Da nhan phong', 'KH001', 'P001', 'LT001', NULL, 'HCM01'),
('PP002', '2026-09-21', '2026-09-21 14:00:00', '2026-09-26 12:00:00', 2, 'Da nhan phong', 'KH002', 'P002', 'LT001', 'KM002', 'HCM01'),
('PP003', '2026-09-22', '2026-09-22 14:00:00', '2026-10-02 12:00:00', 3, 'Da nhan phong', 'KH003', 'P003', 'LT002', 'KM003', 'HN01');

-- 11. DanhGia
INSERT INTO DanhGia
(MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu)
VALUES
('DG001', 5, 'Phong sach se, nhan vien than thien.', '2026-09-22', 'KH001', 'PP001'),
('DG002', 4, 'Dich vu tot, phong thoang mat.', '2026-09-26', 'KH002', 'PP002'),
('DG003', 5, 'Khong gian dep, rat hai long.', '2026-10-02', 'KH003', 'PP003');

-- 12. HoaDon
INSERT INTO HoaDon
(MaHD, MaKH, TongTienSauVAT)
VALUES
('HD001', 'KH001', 1000000),
('HD002', 'KH002', 3400000),
('HD003', 'KH003', 9000000);

-- 13. CTHD
-- Phong sử dụng khóa chính kép (MaPhong, MaChiNhanh)
INSERT INTO CTHD
(MaCTHD, MaHD, MaChiNhanh, MaPhong, SoDem, DonGia)
VALUES
(1, 'HD001', 'HCM01', 'P001', 2, 500000),
(2, 'HD002', 'HCM01', 'P002', 5, 800000),
(3, 'HD003', 'HN01', 'P003', 10, 1200000);

-- 14. ThanhToan
INSERT INTO ThanhToan
(MaThanhToan, SoTien, ThoiGianThanhToan, TrangThaiThanhToan, MaHD)
VALUES
('TT001', 1000000, '2026-09-22 12:30:00', 'Da thanh toan', 'HD001'),
('TT002', 3400000, '2026-09-26 12:30:00', 'Da thanh toan', 'HD002'),
('TT003', 9000000, '2026-10-02 12:30:00', 'Da thanh toan', 'HD003');
