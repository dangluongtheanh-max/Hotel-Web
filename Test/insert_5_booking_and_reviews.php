<?php
/**
 * Script chèn 5 bộ dữ liệu quy trình hoàn chỉnh: Đặt phòng -> Nhận phòng -> Trả phòng -> Đánh giá
 */
require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();
require_once __DIR__ . '/../Backend/Database/Database.php';

$db = Database\Database::getConnection();

echo "Bắt đầu tạo 5 quy trình đặt phòng, trả phòng và đánh giá hoàn chỉnh...\n\n";

try {
    $db->beginTransaction();

    // 1. PHIẾU NHẬN PHÒNG (PP2001 -> PP2005)
    $stmtPhieu = $db->prepare("
        INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            TrangThai = VALUES(TrangThai),
            NgayLap = VALUES(NgayLap)
    ");

    $phieus = [
        ['PP2001', '2026-10-12', 'TrucTuyen', 'DaTraPhong', 'KH002', null, null],
        ['PP2002', '2026-10-15', 'TrucTuyen', 'DaTraPhong', 'KH001', null, null],
        ['PP2003', '2026-10-18', 'TrucTuyen', 'DaTraPhong', 'KH004', null, null],
        ['PP2004', '2026-10-19', 'TrucTuyen', 'DaTraPhong', 'KH002', null, null],
        ['PP2005', '2026-10-20', 'TrucTuyen', 'DaTraPhong', 'KH001', null, null],
    ];

    foreach ($phieus as $p) {
        $stmtPhieu->execute($p);
    }
    echo "✔ 1. Đã tạo 5 PhieuNhanPhong (PP2001 -> PP2005) với trạng thái 'DaTraPhong'\n";

    // 2. CHI TIẾT PHIẾU NHẬN PHÒNG
    $stmtCT = $db->prepare("
        INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            ThoiGianNhanPhong = VALUES(ThoiGianNhanPhong),
            ThoiGianTraPhong = VALUES(ThoiGianTraPhong),
            TrangThai = VALUES(TrangThai)
    ");

    $chiTiets = [
        ['PP2001', 'P203', '2026-10-14 14:00:00', '2026-10-16 11:30:00', 2, 'DaTraPhong'], // Deluxe (LP002)
        ['PP2002', 'P303', '2026-10-17 14:00:00', '2026-10-19 12:00:00', 2, 'DaTraPhong'], // Suite (LP003)
        ['PP2003', 'P205', '2026-10-19 14:00:00', '2026-10-21 11:00:00', 2, 'DaTraPhong'], // Deluxe (LP002)
        ['PP2004', 'P103', '2026-10-20 14:00:00', '2026-10-21 10:00:00', 1, 'DaTraPhong'], // Standard (LP001)
        ['PP2005', 'P305', '2026-10-21 14:00:00', '2026-10-22 12:00:00', 2, 'DaTraPhong'], // Suite (LP003)
    ];

    foreach ($chiTiets as $ct) {
        $stmtCT->execute($ct);
    }
    echo "✔ 2. Đã tạo ChiTietPhieuNhanPhong cho 5 phòng (P203, P303, P205, P103, P305)\n";

    // 3. NGƯỜI LƯU TRÚ
    $stmtNLT = $db->prepare("
        INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE HoTen = VALUES(HoTen)
    ");

    $nlts = [
        ['NLT2001', 'Đặng Lương Thế Anh', '1998-05-12', 'Việt Nam', 'CCCD', '079201001234', 'Khách chính', 'PP2001', 'P203'],
        ['NLT2002', 'Nguyễn Văn An', '1990-08-20', 'Việt Nam', 'CCCD', '001202000001', 'Khách VIP', 'PP2002', 'P303'],
        ['NLT2003', 'Trần Thị Phấn', '1995-11-10', 'Việt Nam', 'CCCD', '079202004567', 'Khách công tác', 'PP2003', 'P205'],
        ['NLT2004', 'Đặng Lương Thế Anh', '1998-05-12', 'Việt Nam', 'CCCD', '079201001234', 'Khách đơn', 'PP2004', 'P103'],
        ['NLT2005', 'Nguyễn Văn An', '1990-08-20', 'Việt Nam', 'CCCD', '001202000001', 'Nghỉ dưỡng', 'PP2005', 'P305'],
    ];

    foreach ($nlts as $n) {
        $stmtNLT->execute($n);
    }
    echo "✔ 3. Đã ghi nhận thông tin Người Lưu Trú (NLT2001 -> NLT2005)\n";

    // 4. HÓA ĐƠN
    $stmtHD = $db->prepare("
        INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE TongTienSauVAT = VALUES(TongTienSauVAT)
    ");

    $hds = [
        ['HD2001', 'PP2001', 1728000], // 2 đêm P203 (800k x 2 + 8% VAT)
        ['HD2002', 'PP2002', 2592000], // 2 đêm P303 (1.200k x 2 + 8% VAT)
        ['HD2003', 'PP2003', 1728000], // 2 đêm P205 (800k x 2 + 8% VAT)
        ['HD2004', 'PP2004', 540000],  // 1 đêm P103 (500k x 1 + 8% VAT)
        ['HD2005', 'PP2005', 1296000], // 1 đêm P305 (1.200k x 1 + 8% VAT)
    ];

    foreach ($hds as $h) {
        $stmtHD->execute($h);
    }
    echo "✔ 4. Đã lập 5 Hóa đơn (HD2001 -> HD2005)\n";

    // 5. CTHD TIỀN PHÒNG
    $stmtCTHD = $db->prepare("
        INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia)
        VALUES (?, ?, ?, NULL, ?, 1, ?)
        ON DUPLICATE KEY UPDATE DonGia = VALUES(DonGia)
    ");

    $cthds = [
        ['HD2001', 'PP2001', 'P203', 2, 800000],
        ['HD2002', 'PP2002', 'P303', 2, 1200000],
        ['HD2003', 'PP2003', 'P205', 2, 800000],
        ['HD2004', 'PP2004', 'P103', 1, 500000],
        ['HD2005', 'PP2005', 'P305', 1, 1200000],
    ];

    foreach ($cthds as $c) {
        $stmtCTHD->execute($c);
    }
    echo "✔ 5. Đã tạo Chi Tiết Hóa Đơn (CTHD)\n";

    // 6. THANH TOÁN (TrangThaiThanhToan = 'HoanTat')
    $stmtTT = $db->prepare("
        INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE TrangThaiThanhToan = VALUES(TrangThaiThanhToan)
    ");

    $tts = [
        ['TT2001', 1728000, '2026-10-16 11:35:00', 'ChuyenKhoan', 'HoanTat', 'HD2001'],
        ['TT2002', 2592000, '2026-10-19 11:45:00', 'ChuyenKhoan', 'HoanTat', 'HD2002'],
        ['TT2003', 1728000, '2026-10-21 11:15:00', 'ChuyenKhoan', 'HoanTat', 'HD2003'],
        ['TT2004', 540000,  '2026-10-21 10:10:00', 'TienMat',     'HoanTat', 'HD2004'],
        ['TT2005', 1296000, '2026-10-22 11:50:00', 'ChuyenKhoan', 'HoanTat', 'HD2005'],
    ];

    foreach ($tts as $t) {
        $stmtTT->execute($t);
    }
    echo "✔ 6. Đã ghi nhận 5 giao dịch Thanh toán hoàn tất (TT2001 -> TT2005)\n";

    // 7. KHÁCH HÀNG CHỦ ĐỘNG GỬI ĐÁNH GIÁ (DanhGia: DG003 -> DG007)
    $stmtDG = $db->prepare("
        INSERT INTO DanhGia (MaDanhGia, SoSao, NoiDung, NgayDanhGia, MaKH, MaPhieu, MaPhong)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            SoSao = VALUES(SoSao),
            NoiDung = VALUES(NoiDung),
            NgayDanhGia = VALUES(NgayDanhGia)
    ");

    $danhgias = [
        [
            'DG003',
            5,
            'Phòng Deluxe P203 ban công nhìn ra thành phố tuyệt đẹp, giường nệm êm ái, tiện nghi cao cấp và nhân viên phục vụ rất chu đáo. Nhất định sẽ quay lại!',
            '2026-10-16',
            'KH002',
            'PP2001',
            'P203'
        ],
        [
            'DG004',
            5,
            'Phòng Suite P303 cực kỳ rộng rãi và đẳng cấp! Bồn tắm sục massage thư giãn tuyệt vời, không gian yên tĩnh và sang trọng, xứng đáng tiêu chuẩn 5 sao.',
            '2026-10-19',
            'KH001',
            'PP2002',
            'P303'
        ],
        [
            'DG005',
            3,
            'Phòng P205 sạch sẽ nhưng mạng WiFi chập chờn, máy lạnh làm mát hơi lâu vào buổi trưa. Khách sạn cần cải thiện đường truyền internet cho khách công tác.',
            '2026-10-21',
            'KH004',
            'PP2003',
            'P205'
        ],
        [
            'DG006',
            2,
            'Vòi sen phòng P103 bị rỉ nước và thoát nước sàn hơi chậm. Cách âm chưa tốt, nghe tiếng hành lang khá ồn vào sáng sớm. Cần bảo trì lại trang thiết bị.',
            '2026-10-21',
            'KH002',
            'PP2004',
            'P103'
        ],
        [
            'DG007',
            4,
            null, // Đánh giá 4 sao không nhận xét chữ (kiểm tra quy tắc UC21.3 mục 9.5)
            '2026-10-22',
            'KH001',
            'PP2005',
            'P305'
        ],
    ];

    foreach ($danhgias as $dg) {
        $stmtDG->execute($dg);
    }
    echo "✔ 7. Đã lưu 5 Đánh giá của khách hàng (DG003 -> DG007: 5★, 5★, 3★, 2★, 4★)\n";

    $db->commit();
    echo "\n🎉 HOÀN THÀNH TẠO 5 BỘ DỮ LIỆU ĐẶT PHÒNG, TRẢ PHÒNG VÀ ĐÁNH GIÁ THÀNH CÔNG!\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ LỖI: " . $e->getMessage() . "\n";
    exit(1);
}
