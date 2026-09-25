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
