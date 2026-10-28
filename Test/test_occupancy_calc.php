<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

require_once __DIR__ . '/../Backend/Database/Database.php';
$db = \Database\Database::getConnection();

function testThongKeCongSuatPhong($tuNgay, $denNgay, $db) {
    if (empty($tuNgay) || empty($denNgay)) {
        throw new InvalidArgumentException("Vui lòng chọn ngày bắt đầu và ngày kết thúc!");
    }
    if ($tuNgay > $denNgay) {
        throw new InvalidArgumentException("Ngày bắt đầu không được lớn hơn ngày kết thúc!");
    }

    $daysInPeriod = (int)((strtotime($denNgay) - strtotime($tuNgay)) / 86400) + 1;
    $dEndPlus1 = date('Y-m-d', strtotime($denNgay . ' +1 day'));

    // 1. Lấy danh sách loại phòng và tổng số phòng của từng loại
    $stmtLP = $db->query("
        SELECT lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang,
               COUNT(p.MaPhong) as TongSoPhong
        FROM loaiphong lp
        LEFT JOIN phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
        GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang
        ORDER BY lp.MaLoaiPhong ASC
    ");
    $loaiPhongs = $stmtLP->fetchAll(PDO::FETCH_ASSOC);

    // 2. Lấy các booking hợp lệ (DangLuuTru, DaTraPhong) có giao với khoảng thời gian
    $sqlBookings = "
        SELECT ctpnp.MaPhieu, ctpnp.MaPhong, p.MaLoaiPhong,
               DATE(ctpnp.ThoiGianNhanPhong) as CheckIn,
               DATE(ctpnp.ThoiGianTraPhong) as CheckOut
        FROM chitietphieunhanphong ctpnp
        JOIN phong p ON ctpnp.MaPhong = p.MaPhong
        JOIN phieunhanphong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
        WHERE ctpnp.TrangThai IN ('DangLuuTru', 'DaTraPhong')
          AND pnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
          AND DATE(ctpnp.ThoiGianNhanPhong) <= ?
          AND (
              DATE(ctpnp.ThoiGianTraPhong) >= ?
              OR (DATE(ctpnp.ThoiGianTraPhong) = DATE(ctpnp.ThoiGianNhanPhong) AND DATE(ctpnp.ThoiGianNhanPhong) >= ?)
          )
    ";
    $stmtBk = $db->prepare($sqlBookings);
    $stmtBk->execute([$denNgay, $tuNgay, $tuNgay]);
    $bookings = $stmtBk->fetchAll(PDO::FETCH_ASSOC);

    // Tính số đêm thực tế theo từng loại phòng
    $demSuDungTheoLoai = [];
    foreach ($loaiPhongs as $lp) {
        $demSuDungTheoLoai[$lp['MaLoaiPhong']] = 0;
    }

    foreach ($bookings as $b) {
        $cIn = $b['CheckIn'];
        $cOut = $b['CheckOut'];
        $effCOut = ($cOut <= $cIn) ? date('Y-m-d', strtotime($cIn . ' +1 day')) : $cOut;

        $overlapStart = max($tuNgay, $cIn);
        $overlapEnd = min($dEndPlus1, $effCOut);

        $diff = (int)((strtotime($overlapEnd) - strtotime($overlapStart)) / 86400);
        if ($diff > 0) {
            $maLP = $b['MaLoaiPhong'];
            if (isset($demSuDungTheoLoai[$maLP])) {
                $demSuDungTheoLoai[$maLP] += $diff;
            }
        }
    }

    // 3. Tổng hợp kết quả
    $tongSoPhongToanKS = 0;
    $tongDemCoTheBanToanKS = 0;
    $tongDemThucTeToanKS = 0;

    $danhSach = [];
    foreach ($loaiPhongs as $lp) {
        $soPhong = (int)$lp['TongSoPhong'];
        $soDemCoTheBan = $soPhong * $daysInPeriod;
        $soDemThucTe = $demSuDungTheoLoai[$lp['MaLoaiPhong']] ?? 0;

        $tongSoPhongToanKS += $soPhong;
        $tongDemCoTheBanToanKS += $soDemCoTheBan;
        $tongDemThucTeToanKS += $soDemThucTe;

        $congSuat = null;
        if ($soDemCoTheBan > 0) {
            $congSuat = round(($soDemThucTe / $soDemCoTheBan) * 100, 2);
        }

        $danhSach[] = [
            'ma_loai_phong' => $lp['MaLoaiPhong'],
            'ten_loai_phong' => $lp['TenLoaiPhong'],
            'tong_so_phong' => $soPhong,
            'so_dem_co_the_ban' => $soDemCoTheBan,
            'so_dem_su_dung' => $soDemThucTe,
            'cong_suat_phong' => $congSuat, // null nếu không áp dụng (N/A)
            'tinh_trang' => $lp['TinhTrang']
        ];
    }

    $congSuatToanKS = null;
    if ($tongDemCoTheBanToanKS > 0) {
        $congSuatToanKS = round(($tongDemThucTeToanKS / $tongDemCoTheBanToanKS) * 100, 2);
    }

    return [
        'ky_thong_ke' => [
            'from_date' => $tuNgay,
            'to_date' => $denNgay,
            'so_ngay_trong_ky' => $daysInPeriod
        ],
        'tong_quan' => [
            'tong_so_phong' => $tongSoPhongToanKS,
            'tong_dem_co_the_ban' => $tongDemCoTheBanToanKS,
            'tong_dem_thuc_te' => $tongDemThucTeToanKS,
            'cong_suat_trung_binh' => $congSuatToanKS,
        ],
        'danh_sach' => $danhSach
    ];
}

$res = testThongKeCongSuatPhong('2026-10-01', '2026-11-30', $db);
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
