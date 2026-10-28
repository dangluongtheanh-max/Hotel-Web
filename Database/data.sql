USE hotel_booking;

-- =========================================================
-- DATA MẪU CHO DATABASE HOTEL_BOOKING
-- (Đã được chuẩn hóa để khớp với hotel_booking.sql)
-- =========================================================

-- 1. TaiKhoan (Mật khẩu dạng ten123 đã được hash Bcrypt)
INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro) VALUES
('TK001', 'theanh', '$2y$10$NfvTVUi9IRN3acBEcqjlpO16XJDZzZu30RBqoFPlMYGCrIIKmBn1a', 'dangluongtheanh@gmail.com', 'HoatDong', 'Admin'),
('TK002', 'phan', '$2y$10$i2mnh2gjMaYq3ir4dBJyfeqnjBKiStz/pz6EKvFqyjd700KflsRVK', 'phan.letan@hotelbooking.com', 'HoatDong', 'LeTan'),
('TK003', 'vinh', '$2y$10$ThNy0LlZhdzhV4pa3VQLiu7w1VPaw5O4ae/TuscrIY.p35bw0cS0.', 'vinh.letan@hotelbooking.com', 'HoatDong', 'LeTan'),
('TK004', 'high', '$2y$10$DoQDUDa5pKu/oKlZACBlQOC6vhtIownj7avwJbiFkLExqqQ3YubtO', 'high@gmail.com', 'HoatDong', 'KhachHang'),
('TK005', 'hoanganh', '$2y$10$vkERzAcPoqD9C8JMSkN2ruPtFp/m4QSKzX8J2pW.bJU2/x52/Bv42', 'hoanganh@gmail.com', 'HoatDong', 'KhachHang'),
('TK006', 'tindat', '$2y$10$LlWFkx6DcflJHOqbroFEUerMJLyNBIPShOPOfgZl6bsvGnrOE9fmm', 'tindat@gmail.com', 'HoatDong', 'KhachHang'),
('TK007', 'thanhnhan', '$2y$10$84FeSMGso.7a/6zI24md/O/SWIuJZAUsJ2JLQDj05oB3wmeoNDOTm', 'thanhnhan@gmail.com', 'HoatDong', 'KhachHang');

-- 2. Admin
INSERT INTO Admin (MaAdmin, HoTen, SDT, MaTK) VALUES
('AD001', 'Đặng Lương Thế Anh', '0938182210', 'TK001');

-- 3. LeTan
INSERT INTO LeTan (MaLeTan, HoTen, SDT, MaTK) VALUES
('LT001', 'Nguyễn Thị Phấn', '0922000001', 'TK002'),
('LT002', 'Võ Đức Hoàng Vinh', '0922000002', 'TK003');

-- 4. KhachHang
INSERT INTO KhachHang (MaKH, HoTen, SDT, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, MaTK) VALUES
('KH001', 'Hồ Anh High', '0911000001', '1998-05-10', 'Việt Nam', 'CCCD', '079198000001', 'TK004'),
('KH002', 'Nguyễn Hoàng Anh', '0911000002', '1997-08-15', 'Việt Nam', 'CCCD', '079197000002', 'TK005'),
('KH003', 'Tín Đạt', '0911000003', '1999-12-20', 'Việt Nam', 'CCCD', '079199000003', 'TK006'),
('KH004', 'Thành Nhân', '0911000004', '1996-03-25', 'Việt Nam', 'CCCD', '079196000004', 'TK007');

-- 5. LoaiPhong
INSERT INTO LoaiPhong (MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi, TinhTrang) VALUES
('LP001', 'Phong Standard', 500000, 2, 'Phong tieu chuan', 'Wi-Fi, TV, điều hòa', 'ConPhong'),
('LP002', 'Phong Deluxe', 800000, 3, 'Phong cao cap', 'Wi-Fi, TV, điều hòa, tủ lạnh', 'ConPhong'),
('LP003', 'Phong Suite', 1200000, 4, 'Phong hang sang', 'Wi-Fi, TV, điều hòa, tủ lạnh, bồn tắm', 'ConPhong'),
('LP004', 'Phong Economy', 300000, 1, 'Phòng tiết kiệm tiện ích cơ bản, phù hợp cho khách đi một mình', 'Wi-Fi, quạt hoặc điều hòa, giường đơn, bàn nhỏ', 'ConPhong'),
('LP005', 'Phong Family', 1500000, 5, 'Phòng gia đình tiện nghi, ấm cúng dành cho gia đình và nhóm bạn', 'Wi-Fi, Smart TV, điều hòa, tủ lạnh, 2 giường đôi, bàn ăn nhỏ', 'ConPhong'),
('LP006', 'Phong Executive Entertainment', 2000000, 6, 'Phòng giải trí đỉnh cao với máy PS5, Smart TV lớn và sofa sang trọng', 'Wi-Fi tốc độ cao, Smart TV lớn, PS5, sofa giải trí, điều hòa, tủ lạnh, 2 giường đôi', 'ConPhong'),
('LP007', 'Phong Presidential Grand Suite', 2800000, 8, 'Phòng Tổng Thống thượng đỉnh ngắm hoàng hôn với bàn bida, minibar và ban công riêng', 'Wi-Fi tốc độ cao, Smart TV, PS5, bàn bida, phòng khách riêng, 3 giường đôi, tủ lạnh lớn, minibar, bồn tắm, ban công view ngắm hoàng hôn', 'ConPhong');

-- 6. Phong
INSERT INTO Phong (MaPhong, TrangThaiPhong, MaLoaiPhong) VALUES
('P001', 'Trong', 'LP001'),
('P002', 'Trong', 'LP002'),
('P003', 'Trong', 'LP003'),
('P004', 'Trong', 'LP001'),
-- 10 phong trong bo sung cho LP001 (Standard)
('P101', 'Trong', 'LP001'),
('P102', 'Trong', 'LP001'),
('P103', 'Trong', 'LP001'),
('P104', 'Trong', 'LP001'),
('P105', 'Trong', 'LP001'),
('P106', 'Trong', 'LP001'),
('P107', 'Trong', 'LP001'),
('P108', 'Trong', 'LP001'),
('P109', 'Trong', 'LP001'),
('P110', 'Trong', 'LP001'),
-- 10 phong trong bo sung cho LP002 (Deluxe)
('P201', 'Trong', 'LP002'),
('P202', 'Trong', 'LP002'),
('P203', 'Trong', 'LP002'),
('P204', 'Trong', 'LP002'),
('P205', 'Trong', 'LP002'),
('P206', 'Trong', 'LP002'),
('P207', 'Trong', 'LP002'),
('P208', 'Trong', 'LP002'),
('P209', 'Trong', 'LP002'),
('P210', 'Trong', 'LP002'),
-- 10 phong trong bo sung cho LP003 (Suite)
('P301', 'Trong', 'LP003'),
('P302', 'Trong', 'LP003'),
('P303', 'Trong', 'LP003'),
('P304', 'Trong', 'LP003'),
('P305', 'Trong', 'LP003'),
('P306', 'Trong', 'LP003'),
('P307', 'Trong', 'LP003'),
('P308', 'Trong', 'LP003'),
('P309', 'Trong', 'LP003'),
('P310', 'Trong', 'LP003'),
-- 10 phong trong bo sung cho LP004 (Economy)
('P401', 'Trong', 'LP004'),
('P402', 'Trong', 'LP004'),
('P403', 'Trong', 'LP004'),
('P404', 'Trong', 'LP004'),
('P405', 'Trong', 'LP004'),
('P406', 'Trong', 'LP004'),
('P407', 'Trong', 'LP004'),
('P408', 'Trong', 'LP004'),
('P409', 'Trong', 'LP004'),
('P410', 'Trong', 'LP004'),
-- 10 phong trong bo sung cho LP005 (Family)
('P501', 'Trong', 'LP005'),
('P502', 'Trong', 'LP005'),
('P503', 'Trong', 'LP005'),
('P504', 'Trong', 'LP005'),
('P505', 'Trong', 'LP005'),
('P506', 'Trong', 'LP005'),
('P507', 'Trong', 'LP005'),
('P508', 'Trong', 'LP005'),
('P509', 'Trong', 'LP005'),
('P510', 'Trong', 'LP005'),
-- 8 phong trong bo sung cho LP006 (Executive Entertainment)
('P601', 'Trong', 'LP006'),
('P602', 'Trong', 'LP006'),
('P603', 'Trong', 'LP006'),
('P604', 'Trong', 'LP006'),
('P605', 'Trong', 'LP006'),
('P606', 'Trong', 'LP006'),
('P607', 'Trong', 'LP006'),
('P608', 'Trong', 'LP006'),
-- 4 phong trong bo sung cho LP007 (Presidential Grand Suite)
('P701', 'Trong', 'LP007'),
('P702', 'Trong', 'LP007'),
('P703', 'Trong', 'LP007'),
('P704', 'Trong', 'LP007');

-- 7. KhuyenMai
INSERT INTO KhuyenMai (MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, SoLuong, SoLuongDaDung, NgayBatDau, NgayKetThuc, TrangThai) VALUES
('KM001', 'Khuyen mai khach hang moi', 'PhanTram', 10, 0, 50, 0, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM002', 'Khuyen mai luu tru dai ngay', 'PhanTram', 15, 5, 20, 0, '2026-01-01', '2026-12-31', 'HoatDong'),
('KM003', 'Khuyen mai mua he', 'SoTien', 200000, 2, 30, 0, '2026-06-01', '2026-08-31', 'NgungHoatDong');

-- 8. DichVu
INSERT INTO DichVu (MaDV, TenDV, MoTa, MoTaChiTiet, DonGia, TrangThai) VALUES
('DVDoAn', 'Dat do an', 'Dich vu phuc vu am thuc tai phong', 'Phuc vu cac mon an nong hoi, thuc uong tuoi ngon giao tan phong trong 15 - 20 phut.', 0, 'DangCungCap'),
('DVGiatUi', 'Giat ui', 'Dich vu giat ui theo lan', 'Dich vu giat say lay ngay, tay diem ban chuyen nghiep va ui phang quan ao sach dep.', 0, 'DangCungCap'),
('DVThueXe', 'Thue xe may', 'Dich vu cho thue xe may tu lai', 'Cho thue cac dong xe ga va xe so doi moi, day du mu bao hiem va xang xe.', 150000, 'DangCungCap');

-- 8.1 ChiTietDichVu
INSERT INTO ChiTietDichVu (MaCTDV, MaDV, TenCTDV, MoTa, MoTaChiTiet, DonGia, SoLuongTon) VALUES
('CTDVDoAn01', 'DVDoAn', 'Bo bit tet sot tieu den', 'Bo bit tet Uc cao cap', 'Thit bo Uc mem thom kem sot tieu den Phap, khoai tay chien va salad tuoi ngon.', 120000, 50),
('CTDVDoAn02', 'DVDoAn', 'Com ga Hoi An', 'Com ga dac san vang thom', 'Com ga xe gao tam nau nuoc dung ga, rau ram, hanh tay, dua chua va nuoc sup dam da.', 50000, 100),
('CTDVDoAn03', 'DVDoAn', 'Pho bo Ha Noi dac biet', 'Pho bo truyen thong nong hoi', 'Banh pho tuoi, thit bo tai/nam tuoi ngon va nuoc dung ham xuong ong 12 gio.', 65000, 100),
('CTDVDoAn04', 'DVDoAn', 'Mi xao hai san', 'Mi xao tom muc gion ngon', 'Mi xao voi tom su, muc tuoi, rau cai xanh va sot hao dac trung.', 60000, 100),
('CTDVDoAn05', 'DVDoAn', 'Banh mi kep thit nuong', 'Banh mi Viet Nam truyen thong', 'Banh mi gion rum kep thit nuong xien, pate gan, rau thom va do chua.', 35000, 100),
('CTDVDoAn06', 'DVDoAn', 'Nuoc cam tuoi ep', 'Nuoc ep cam sanh nguyen chat', 'Cam sanh tuoi vat nguyen chat, it da, giai nhiet bo sung vitamin C.', 30000, 100),
('CTDVGiatUi01', 'DVGiatUi', 'Áo thun', 'Giặt sấy và gấp gọn áo thun', 'Giặt sạch vết bẩn, xả thơm và sấy khô gấp gọn.', 15000, 0),
('CTDVGiatUi02', 'DVGiatUi', 'Quần ngắn', 'Giặt sấy quần short, quần ngắn', 'Giặt sạch nhẹ nhàng, bảo vệ chất liệu vải và sấy thơm.', 15000, 0),
('CTDVGiatUi03', 'DVGiatUi', 'Quần dài', 'Giặt ủi quần tây, jean, kaki', 'Giặt sạch sâu, sấy khô và ủi ly phẳng phiu chuẩn khách sạn.', 25000, 0),
('CTDVGiatUi04', 'DVGiatUi', 'Áo sơ mi', 'Giặt hấp và ủi phẳng áo sơ mi', 'Tẩy sạch cổ và tay áo, ủi phẳng phiu và treo móc cẩn thận.', 25000, 0),
('CTDVGiatUi05', 'DVGiatUi', 'Áo khoác', 'Giặt hấp chuyên sâu áo khoác', 'Giặt hấp giữ form dáng áo khoác, khử khuẩn và sấy khô.', 30000, 0),
('CTDVGiatUi06', 'DVGiatUi', 'Khăn', 'Giặt sấy khử khuẩn khăn tắm/mặt', 'Khử trùng nhiệt độ cao, sấy mềm mại và thơm tho.', 10000, 0),
('CTDVGiatUi07', 'DVGiatUi', 'Vớ', 'Giặt sấy đôi tất/vớ', 'Giặt sạch kháng khuẩn và sấy khô theo đôi.', 10000, 0),
('CTDVGiatUi08', 'DVGiatUi', 'Đồ lót', 'Giặt đồ lót kháng khuẩn riêng biệt', 'Quy trình giặt riêng biệt với nước giặt dịu nhẹ, kháng khuẩn an toàn.', 10000, 0),
('CTDVThueXe01', 'DVThueXe', 'Xe tay ga Honda Vision / AirBlade', 'Thue xe tay ga theo ngay', 'Xe doi moi tiet kiem xang, trang bi 2 mu bao hiem va ao mua tien loi.', 150000, 10),
('CTDVThueXe02', 'DVThueXe', 'Xe so Honda Wave / Sirius', 'Thue xe so theo ngay', 'Xe so ben bi, de dieu khien, bao gom 2 mu bao hiem an toan.', 100000, 10);

