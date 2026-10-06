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
('KM003', 'Khuyen mai mua he', 'SoTien', 200000, 2, '2026-06-01', '2026-08-31', 'NgungHoatDong');

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

-- 10. DichVu
INSERT INTO DichVu (MaDV, TenDV, MoTa, DonGia, TrangThai) VALUES
('DV001', 'Bua sang', 'Suat an sang tai khach san', 120000, 'DangCungCap'),
('DV002', 'Giat ui', 'Dich vu giat ui theo lan', 50000, 'DangCungCap'),
('DV003', 'Dua don san bay', 'Dich vu dua don mot chieu', 250000, 'DangCungCap');

-- 11. HoaDon
INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES
('HD001', 'PP001', 2519000),
('HD002', 'PP002', 2024000),
('HD003', 'PP003', 11220000);

-- 12. CTHD
INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaDV, SoDem, SoLuong, DonGia) VALUES
('HD001', 'PP001', 'P001', NULL, 2, 1, 500000),
('HD001', 'PP001', 'P004', NULL, 2, 1, 500000),
('HD001', 'PP001', NULL, 'DV001', 0, 2, 120000),
('HD001', 'PP001', NULL, 'DV002', 0, 1, 50000),
('HD002', 'PP002', 'P002', NULL, 2, 1, 800000),
('HD002', 'PP002', NULL, 'DV001', 0, 2, 120000),
('HD003', 'PP003', 'P003', NULL, 10, 1, 1200000);

-- 13. DanhGia
INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu) VALUES
('DG001', 5, 'Phong sach se, nhan vien than thien.', '2026-09-22', 'KH001', 'PP001');

-- 14. ThanhToan
INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) VALUES
('TT001', 2519000, '2026-09-22 12:30:00', 'ChuyenKhoan', 'HoanTat', 'HD001'),
('TT002', 500000, '2026-10-01 10:00:00', 'TienMat', 'HoanTat', 'HD002'),
('TT003', 2000000, '2026-10-01 11:00:00', 'ChuyenKhoan', 'HoanTat', 'HD003');
