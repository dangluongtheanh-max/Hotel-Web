<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository phụ trách toàn bộ truy vấn Báo Cáo & Thống Kê (BE4).
 */
class AdminThongKeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * THỐNG KÊ DOANH THU (BE4)
     * ========================================================================= */

    /**
     * Thống kê doanh thu chi tiết theo khoảng thời gian và phân loại nguồn thu.
     */
    public function thongKeDoanhThu(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31 23:59:59');

        // 1. Tổng tiền thu từ các giao dịch thanh toán thành công
        $sqlTongThu = "SELECT 
                        COUNT(*) AS TongGiaoDich,
                        COALESCE(SUM(SoTien), 0) AS TongDoanhThuThucTe,
                        COALESCE(SUM(CASE WHEN HinhThuc = 'ChuyenKhoan' THEN SoTien ELSE 0 END), 0) AS DoanhThuChuyenKhoan,
                        COALESCE(SUM(CASE WHEN HinhThuc = 'TienMat' THEN SoTien ELSE 0 END), 0) AS DoanhThuTienMat
                       FROM ThanhToan
                       WHERE TrangThaiThanhToan = 'HoanTat'
                         AND ThoiGianThanhToan >= :fromDate
                         AND ThoiGianThanhToan <= :toDate";
        $stmt = $this->db->prepare($sqlTongThu);
        $stmt->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $tongQuanDoanhThu = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 2. Cơ cấu doanh thu: Tiền Phòng vs Tiền Dịch Vụ từ bảng CTHD (Dùng EXISTS để tránh nhân đôi CTHD khi Hóa Đơn có nhiều lần thanh toán)
        $sqlCoCau = "SELECT 
                        COALESCE(SUM(CASE WHEN cthd.MaPhong IS NOT NULL AND cthd.MaCTDV IS NULL THEN (cthd.SoDem * cthd.DonGia) ELSE 0 END), 0) AS DoanhThuPhong,
                        COALESCE(SUM(CASE WHEN cthd.MaCTDV IS NOT NULL THEN (cthd.SoLuong * cthd.DonGia) ELSE 0 END), 0) AS DoanhThuDichVu
                     FROM CTHD cthd
                     JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
                     WHERE EXISTS (
                         SELECT 1 FROM ThanhToan tt 
                         WHERE tt.MaHD = hd.MaHD 
                           AND tt.TrangThaiThanhToan = 'HoanTat'
                           AND tt.ThoiGianThanhToan >= :fromDate
                           AND tt.ThoiGianThanhToan <= :toDate
                     )";
        $stmtCoCau = $this->db->prepare($sqlCoCau);
        $stmtCoCau->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $coCau = $stmtCoCau->fetch(PDO::FETCH_ASSOC) ?: ['DoanhThuPhong' => 0, 'DoanhThuDichVu' => 0];

        // 3. Chuỗi dữ liệu doanh thu theo ngày / tháng để vẽ biểu đồ
        $sqlTimeline = "SELECT 
                            DATE(ThoiGianThanhToan) AS Ngay,
                            COUNT(*) AS SoGiaoDich,
                            COALESCE(SUM(SoTien), 0) AS DoanhThu
                        FROM ThanhToan
                        WHERE TrangThaiThanhToan = 'HoanTat'
                          AND ThoiGianThanhToan >= :fromDate
                          AND ThoiGianThanhToan <= :toDate
                        GROUP BY DATE(ThoiGianThanhToan)
                        ORDER BY Ngay ASC";
        $stmtTimeline = $this->db->prepare($sqlTimeline);
        $stmtTimeline->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $timeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);

        // 4. Danh sách hóa đơn và thanh toán gần nhất
        $sqlGiaoDich = "SELECT 
                            tt.MaThanhToan, tt.MaHD, tt.SoTien, tt.HinhThuc, 
                            tt.ThoiGianThanhToan, tt.TrangThaiThanhToan,
                            kh.HoTen AS TenKhachHang, kh.SDT
                        FROM ThanhToan tt
                        JOIN HoaDon hd ON tt.MaHD = hd.MaHD
                        JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                        JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                        ORDER BY tt.ThoiGianThanhToan DESC
                        LIMIT 10";
        $stmtGD = $this->db->query($sqlGiaoDich);
        $dsGiaoDich = $stmtGD->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_quan' => [
                'tong_doanh_thu' => (float)($tongQuanDoanhThu['TongDoanhThuThucTe'] ?? 0),
                'tong_giao_dich' => (int)($tongQuanDoanhThu['TongGiaoDich'] ?? 0),
                'tong_tien_hoan' => 0.0,
                'doanh_thu_chuyen_khoan' => (float)($tongQuanDoanhThu['DoanhThuChuyenKhoan'] ?? 0),
                'doanh_thu_tien_mat' => (float)($tongQuanDoanhThu['DoanhThuTienMat'] ?? 0),
                'doanh_thu_phong' => (float)($coCau['DoanhThuPhong'] ?? 0),
                'doanh_thu_dich_vu' => (float)($coCau['DoanhThuDichVu'] ?? 0),
            ],
            'bieu_do_doanh_thu' => $timeline,
            'giao_dich_gan_nhat' => $dsGiaoDich,
        ];
    }

    /**
     * Thống kê doanh thu theo từng Loại Phòng (Tab 1: Thống Kê Doanh Thu Loại Phòng).
     * Trả về tổng doanh thu phòng, tổng số đêm sử dụng và bảng chi tiết theo từng loại phòng.
     * Quy tắc:
     * - Chỉ ghi nhận doanh thu từ các hóa đơn đã thanh toán thành công (ThanhToan.TrangThaiThanhToan = 'HoanTat').
     * - Dùng điều kiện EXISTS để loại trừ triệt để nguy cơ tính trùng khi một hóa đơn có nhiều giao dịch thanh toán.
     * - Doanh thu = SUM(CTHD.SoDem * CTHD.DonGia) theo giá thực tế ghi trên hóa đơn.
     */
    public function thongKeDoanhThuLoaiPhong(array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : (!empty($filters['tu_ngay']) ? $filters['tu_ngay'] : date('Y-01-01'));
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : (!empty($filters['den_ngay']) ? $filters['den_ngay'] : date('Y-12-31'));

        if (strlen($fromDate) === 10) $fromDate .= ' 00:00:00';
        if (strlen($toDate) === 10) $toDate .= ' 23:59:59';

        $sql = "
            SELECT 
                lp.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia AS GiaNiemYet,
                COALESCE(SUM(cthd.SoDem), 0) AS SoDemSuDung,
                COALESCE(SUM(cthd.SoDem * cthd.DonGia), 0) AS DoanhThu
            FROM LoaiPhong lp
            LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
            LEFT JOIN (
                SELECT cthd.MaPhong, cthd.SoDem, cthd.DonGia
                FROM CTHD cthd
                JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
                WHERE cthd.MaPhong IS NOT NULL 
                  AND cthd.MaCTDV IS NULL
                  AND EXISTS (
                      SELECT 1 FROM ThanhToan tt 
                      WHERE tt.MaHD = hd.MaHD 
                        AND tt.TrangThaiThanhToan = 'HoanTat'
                        AND tt.ThoiGianThanhToan >= :fromDate
                        AND tt.ThoiGianThanhToan <= :toDate
                  )
            ) cthd ON p.MaPhong = cthd.MaPhong
            GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia
            ORDER BY DoanhThu DESC, SoDemSuDung DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuPhong = 0.0;
        $tongSoDem = 0;

        foreach ($rows as $r) {
            $tongDoanhThuPhong += (float)$r['DoanhThu'];
            $tongSoDem += (int)$r['SoDemSuDung'];
        }

        $danhSach = [];
        foreach ($rows as $r) {
            $doanhThu = (float)$r['DoanhThu'];
            $tyTrong = ($tongDoanhThuPhong > 0) ? round(($doanhThu / $tongDoanhThuPhong) * 100, 2) : 0.0;
            $danhSach[] = [
                'ma_loai_phong' => $r['MaLoaiPhong'],
                'ten_loai_phong' => $r['TenLoaiPhong'],
                'gia_niem_yet' => (float)$r['GiaNiemYet'],
                'so_dem_su_dung' => (int)$r['SoDemSuDung'],
                'doanh_thu' => $doanhThu,
                'ty_trong' => $tyTrong
            ];
        }

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_doanh_thu_phong' => $tongDoanhThuPhong,
            'tong_so_dem' => $tongSoDem,
            'danh_sach' => $danhSach
        ];
    }

    /**
     * Thống kê doanh thu theo từng Dịch Vụ (Tab 2: Thống Kê Doanh Thu Dịch Vụ).
     * Trả về tổng doanh thu dịch vụ, tổng lượt sử dụng, tổng số lượng và bảng chi tiết theo loại dịch vụ.
     * Quy tắc:
     * - Chỉ ghi nhận doanh thu từ các khoản dịch vụ của hóa đơn đã thanh toán thành công (ThanhToan.TrangThaiThanhToan = 'HoanTat').
     * - Dùng EXISTS chống tính trùng lặp thanh toán nhiều lần.
     * - Phân định rõ: Số lượt sử dụng (số lần gọi dịch vụ) vs Số lượng cung cấp (tổng đơn vị món ăn/xe/quần áo).
     * - Doanh thu = SUM(CTHD.SoLuong * CTHD.DonGia).
     */
    public function thongKeDoanhThuDichVu(array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : (!empty($filters['tu_ngay']) ? $filters['tu_ngay'] : date('Y-01-01'));
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : (!empty($filters['den_ngay']) ? $filters['den_ngay'] : date('Y-12-31'));

        if (strlen($fromDate) === 10) $fromDate .= ' 00:00:00';
        if (strlen($toDate) === 10) $toDate .= ' 23:59:59';

        $sql = "
            SELECT 
                dv.MaDV,
                dv.TenDV,
                COALESCE(COUNT(cthd.MaCTHD), 0) AS SoLuotSuDung,
                COALESCE(SUM(cthd.SoLuong), 0) AS TongSoLuong,
                COALESCE(SUM(cthd.SoLuong * cthd.DonGia), 0) AS DoanhThu
            FROM DichVu dv
            LEFT JOIN ChiTietDichVu ctdv ON dv.MaDV = ctdv.MaDV
            LEFT JOIN (
                SELECT cthd.MaCTHD, cthd.MaCTDV, cthd.SoLuong, cthd.DonGia
                FROM CTHD cthd
                JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
                WHERE cthd.MaCTDV IS NOT NULL
                  AND EXISTS (
                      SELECT 1 FROM ThanhToan tt 
                      WHERE tt.MaHD = hd.MaHD 
                        AND tt.TrangThaiThanhToan = 'HoanTat'
                        AND tt.ThoiGianThanhToan >= :fromDate
                        AND tt.ThoiGianThanhToan <= :toDate
                  )
            ) cthd ON ctdv.MaCTDV = cthd.MaCTDV
            GROUP BY dv.MaDV, dv.TenDV
            ORDER BY DoanhThu DESC, SoLuotSuDung DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuDichVu = 0.0;
        $tongSoLuot = 0;
        $tongSoLuong = 0;

        foreach ($rows as $r) {
            $tongDoanhThuDichVu += (float)$r['DoanhThu'];
            $tongSoLuot += (int)$r['SoLuotSuDung'];
            $tongSoLuong += (int)$r['TongSoLuong'];
        }

        $danhSach = [];
        foreach ($rows as $r) {
            $doanhThu = (float)$r['DoanhThu'];
            $tyTrong = ($tongDoanhThuDichVu > 0) ? round(($doanhThu / $tongDoanhThuDichVu) * 100, 2) : 0.0;
            $danhSach[] = [
                'ma_dv' => $r['MaDV'],
                'ten_dv' => $r['TenDV'],
                'so_luot_su_dung' => (int)$r['SoLuotSuDung'],
                'tong_so_luong' => (int)$r['TongSoLuong'],
                'doanh_thu' => $doanhThu,
                'ty_trong' => $tyTrong
            ];
        }

        // Lấy thêm chi tiết từng món / gói con (item-level breakdown)
        $sqlItems = "
            SELECT 
                ctdv.MaCTDV,
                ctdv.TenCTDV,
                dv.MaDV,
                dv.TenDV,
                COUNT(cthd.MaCTHD) AS SoLuotSuDung,
                COALESCE(SUM(cthd.SoLuong), 0) AS TongSoLuong,
                COALESCE(SUM(cthd.SoLuong * cthd.DonGia), 0) AS DoanhThu
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            JOIN CTHD cthd ON ctdv.MaCTDV = cthd.MaCTDV
            JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
            WHERE EXISTS (
                SELECT 1 FROM ThanhToan tt 
                WHERE tt.MaHD = hd.MaHD 
                  AND tt.TrangThaiThanhToan = 'HoanTat'
                  AND tt.ThoiGianThanhToan >= :fromDate
                  AND tt.ThoiGianThanhToan <= :toDate
            )
            GROUP BY ctdv.MaCTDV, ctdv.TenCTDV, dv.MaDV, dv.TenDV
            ORDER BY DoanhThu DESC
        ";
        $stmtItems = $this->db->prepare($sqlItems);
        $stmtItems->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $rawItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $chiTietCacMon = [];
        foreach ($rawItems as $it) {
            $doanhThuIt = (float)$it['DoanhThu'];
            $tyTrongIt = ($tongDoanhThuDichVu > 0) ? round(($doanhThuIt / $tongDoanhThuDichVu) * 100, 2) : 0.0;
            $chiTietCacMon[] = [
                'ma_ctdv' => $it['MaCTDV'],
                'ten_ctdv' => $it['TenCTDV'],
                'ma_dv' => $it['MaDV'],
                'ten_dv' => $it['TenDV'],
                'so_luot_su_dung' => (int)$it['SoLuotSuDung'],
                'tong_so_luong' => (int)$it['TongSoLuong'],
                'doanh_thu' => $doanhThuIt,
                'ty_trong' => $tyTrongIt
            ];
        }

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_doanh_thu_dich_vu' => $tongDoanhThuDichVu,
            'tong_so_luot' => $tongSoLuot,
            'tong_so_luong' => $tongSoLuong,
            'danh_sach' => $danhSach,
            'chi_tiet_mon' => $chiTietCacMon
        ];
    }

    /**
     * Thống kê công suất phòng theo đặc tả usecase UC21.2.
     * Công thức: Công suất phòng = (Số đêm phòng thực tế sử dụng / Số đêm phòng có thể bán) * 100%
     * - Số đêm phòng có thể bán = Tổng số phòng * Số ngày trong kỳ lọc.
     * - Số đêm phòng thực tế sử dụng = Tổng số đêm khách đã thực tế lưu trú (DangLuuTru, DaTraPhong) có giao trong kỳ.
     * - Trường hợp 0 đêm phòng có thể bán: trả về null ("Không áp dụng" - N/A).
     * - Hỗ trợ lọc theo loại phòng nếu $filters['ma_loai_phong'] được truyền vào.
     */
    public function thongKeCongSuatPhong(array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : (!empty($filters['tu_ngay']) ? $filters['tu_ngay'] : date('Y-m-01'));
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : (!empty($filters['den_ngay']) ? $filters['den_ngay'] : date('Y-m-d'));
        $filterMaLoaiPhong = !empty($filters['ma_loai_phong']) ? trim($filters['ma_loai_phong']) : null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        if ($fromDateClean > $toDateClean) {
            throw new \InvalidArgumentException("Ngày bắt đầu ({$fromDateClean}) không được lớn hơn ngày kết thúc ({$toDateClean})!");
        }

        $daysInPeriod = (int)((strtotime($toDateClean) - strtotime($fromDateClean)) / 86400) + 1;
        $dEndPlus1 = date('Y-m-d', strtotime($toDateClean . ' +1 day'));

        // 1. Lấy danh sách loại phòng và tổng số phòng của từng loại
        $sqlLP = "
            SELECT lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang,
                   COUNT(p.MaPhong) as TongSoPhong
            FROM LoaiPhong lp
            LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
            GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang
            ORDER BY lp.MaLoaiPhong ASC
        ";
        $loaiPhongs = $this->db->query($sqlLP)->fetchAll(PDO::FETCH_ASSOC);

        // 2. Lấy các lượt đặt phòng thực tế (DangLuuTru, DaTraPhong) có giao với khoảng thời gian
        $sqlBookings = "
            SELECT ctpnp.MaPhieu, ctpnp.MaPhong, p.MaLoaiPhong,
                   DATE(ctpnp.ThoiGianNhanPhong) as CheckIn,
                   DATE(ctpnp.ThoiGianTraPhong) as CheckOut
            FROM ChiTietPhieuNhanPhong ctpnp
            JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
            JOIN PhieuNhanPhong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
            WHERE ctpnp.TrangThai IN ('DangLuuTru', 'DaTraPhong')
              AND pnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
              AND DATE(ctpnp.ThoiGianNhanPhong) <= ?
              AND (
                  DATE(ctpnp.ThoiGianTraPhong) >= ?
                  OR (DATE(ctpnp.ThoiGianTraPhong) = DATE(ctpnp.ThoiGianNhanPhong) AND DATE(ctpnp.ThoiGianNhanPhong) >= ?)
              )
        ";
        $stmtBk = $this->db->prepare($sqlBookings);
        $stmtBk->execute([$toDateClean, $fromDateClean, $fromDateClean]);
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

            $overlapStart = max($fromDateClean, $cIn);
            $overlapEnd = min($dEndPlus1, $effCOut);

            $diff = (int)((strtotime($overlapEnd) - strtotime($overlapStart)) / 86400);
            if ($diff > 0) {
                $maLP = $b['MaLoaiPhong'];
                if (isset($demSuDungTheoLoai[$maLP])) {
                    $demSuDungTheoLoai[$maLP] += $diff;
                }
            }
        }

        // 3. Tổng hợp số liệu theo loại phòng và toàn khách sạn
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
                'cong_suat_phong' => $congSuat, // null nếu N/A
                'gia_niem_yet' => (float)$lp['Gia'],
                'tinh_trang' => $lp['TinhTrang']
            ];
        }

        $congSuatToanKS = null;
        if ($tongDemCoTheBanToanKS > 0) {
            $congSuatToanKS = round(($tongDemThucTeToanKS / $tongDemCoTheBanToanKS) * 100, 2);
        }

        // Lọc danh sách theo loại phòng nếu được yêu cầu
        $danhSachKetQua = $danhSach;
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $danhSachKetQua = array_values(array_filter($danhSach, function($it) use ($filterMaLoaiPhong) {
                return $it['ma_loai_phong'] === $filterMaLoaiPhong;
            }));
        }

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'so_ngay_trong_ky' => $daysInPeriod,
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all'
            ],
            'tong_quan' => [
                'tong_so_phong' => $tongSoPhongToanKS,
                'tong_dem_co_the_ban' => $tongDemCoTheBanToanKS,
                'tong_dem_thuc_te' => $tongDemThucTeToanKS,
                'cong_suat_trung_binh' => $congSuatToanKS,
            ],
            'danh_sach' => $danhSachKetQua
        ];
    }

    /* =========================================================================
     * THỐNG KÊ ĐÁNH GIÁ (UC21.3)
     * ========================================================================= */

    /**
     * Thống kê đánh giá theo loại phòng (UC21.3).
     */
    public function thongKeDanhGiaTheoLoaiPhong(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $filterMaLoaiPhong = $filters['ma_loai_phong'] ?? null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $sql = "SELECT 
                    lp.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    lp.Gia,
                    lp.TinhTrang,
                    COUNT(d.MaDanhGia) AS so_luot_danh_gia,
                    COALESCE(SUM(d.SoSao), 0) AS tong_so_sao,
                    ROUND(AVG(d.SoSao), 2) AS diem_trung_binh,
                    COALESCE(SUM(CASE WHEN d.SoSao = 1 THEN 1 ELSE 0 END), 0) AS so_luot_1_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 2 THEN 1 ELSE 0 END), 0) AS so_luot_2_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 3 THEN 1 ELSE 0 END), 0) AS so_luot_3_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 4 THEN 1 ELSE 0 END), 0) AS so_luot_4_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 5 THEN 1 ELSE 0 END), 0) AS so_luot_5_sao
                FROM LoaiPhong lp
                LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
                LEFT JOIN DanhGia d ON p.MaPhong = d.MaPhong 
                    AND d.NgayDanhGia >= :fromDate 
                    AND d.NgayDanhGia <= :toDate
                    AND d.SoSao BETWEEN 1 AND 5
                GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang
                ORDER BY so_luot_danh_gia DESC, diem_trung_binh DESC, lp.MaLoaiPhong ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongLuotToanKS = 0;
        $tongSoSaoToanKS = 0;
        $soLoaiPhongCoDanhGia = 0;
        $soLuot5SaoToanKS = 0;

        $danhSach = [];
        foreach ($rows as $row) {
            $n = (int)$row['so_luot_danh_gia'];
            $s = (int)$row['tong_so_sao'];
            $s1 = (int)$row['so_luot_1_sao'];
            $s2 = (int)$row['so_luot_2_sao'];
            $s3 = (int)$row['so_luot_3_sao'];
            $s4 = (int)$row['so_luot_4_sao'];
            $s5 = (int)$row['so_luot_5_sao'];

            $tongLuotToanKS += $n;
            $tongSoSaoToanKS += $s;
            if ($n > 0) {
                $soLoaiPhongCoDanhGia++;
            }
            $soLuot5SaoToanKS += $s5;

            $diemTB = $n > 0 ? round($s / $n, 2) : null;
            $tyLe45 = $n > 0 ? round((($s4 + $s5) / $n) * 100, 2) : null;

            $danhSach[] = [
                'ma_loai_phong' => $row['MaLoaiPhong'],
                'ten_loai_phong' => $row['TenLoaiPhong'],
                'gia_niem_yet' => (float)$row['Gia'],
                'tinh_trang' => $row['TinhTrang'],
                'so_luot_danh_gia' => $n,
                'tong_so_sao' => $s,
                'diem_trung_binh' => $diemTB, // null nếu N = 0
                'so_luot_1_sao' => $s1,
                'so_luot_2_sao' => $s2,
                'so_luot_3_sao' => $s3,
                'so_luot_4_sao' => $s4,
                'so_luot_5_sao' => $s5,
                'ty_le_4_5_sao' => $tyLe45
            ];
        }

        // Lọc theo loại phòng cụ thể nếu có
        $danhSachKetQua = $danhSach;
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $danhSachKetQua = array_values(array_filter($danhSach, function($it) use ($filterMaLoaiPhong) {
                return $it['ma_loai_phong'] === $filterMaLoaiPhong;
            }));
        }

        $diemTBToanKS = $tongLuotToanKS > 0 ? round($tongSoSaoToanKS / $tongLuotToanKS, 2) : null;

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'che_do' => 'room_type',
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all'
            ],
            'tong_quan' => [
                'tong_luot_danh_gia' => $tongLuotToanKS,
                'diem_trung_binh' => $diemTBToanKS,
                'so_loai_phong_co_danh_gia' => $soLoaiPhongCoDanhGia,
                'so_luot_5_sao' => $soLuot5SaoToanKS,
                'tong_so_loai_phong' => count($danhSachKetQua)
            ],
            'danh_sach' => $danhSachKetQua
        ];
    }

    /**
     * Thống kê đánh giá theo từng phòng (UC21.3).
     */
    public function thongKeDanhGiaTheoPhong(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $filterMaLoaiPhong = $filters['ma_loai_phong'] ?? null;
        $keyword = trim($filters['keyword'] ?? '');

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $params = [
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ];

        $whereClause = "";
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $whereClause .= " AND lp.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $filterMaLoaiPhong;
        }

        if ($keyword !== '') {
            $whereClause .= " AND (p.MaPhong LIKE :kw OR lp.TenLoaiPhong LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }

        $sql = "SELECT 
                    p.MaPhong,
                    p.TrangThaiPhong,
                    lp.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    COUNT(d.MaDanhGia) AS so_luot_danh_gia,
                    COALESCE(SUM(d.SoSao), 0) AS tong_so_sao,
                    ROUND(AVG(d.SoSao), 2) AS diem_trung_binh,
                    COALESCE(SUM(CASE WHEN d.SoSao = 1 THEN 1 ELSE 0 END), 0) AS so_luot_1_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 2 THEN 1 ELSE 0 END), 0) AS so_luot_2_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 3 THEN 1 ELSE 0 END), 0) AS so_luot_3_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 4 THEN 1 ELSE 0 END), 0) AS so_luot_4_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 5 THEN 1 ELSE 0 END), 0) AS so_luot_5_sao
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN DanhGia d ON p.MaPhong = d.MaPhong 
                    AND d.NgayDanhGia >= :fromDate 
                    AND d.NgayDanhGia <= :toDate
                    AND d.SoSao BETWEEN 1 AND 5
                WHERE 1=1 {$whereClause}
                GROUP BY p.MaPhong, p.TrangThaiPhong, lp.MaLoaiPhong, lp.TenLoaiPhong
                ORDER BY so_luot_danh_gia DESC, diem_trung_binh DESC, p.MaPhong ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongLuot = 0;
        $tongSoSao = 0;
        $soPhongCoDanhGia = 0;
        $soLuot12Sao = 0;

        $danhSach = [];
        foreach ($rows as $row) {
            $n = (int)$row['so_luot_danh_gia'];
            $s = (int)$row['tong_so_sao'];
            $s1 = (int)$row['so_luot_1_sao'];
            $s2 = (int)$row['so_luot_2_sao'];
            $s3 = (int)$row['so_luot_3_sao'];
            $s4 = (int)$row['so_luot_4_sao'];
            $s5 = (int)$row['so_luot_5_sao'];

            $tongLuot += $n;
            $tongSoSao += $s;
            if ($n > 0) {
                $soPhongCoDanhGia++;
            }
            $soLuot12Sao += ($s1 + $s2);

            $diemTB = $n > 0 ? round($s / $n, 2) : null;

            $danhSach[] = [
                'ma_phong' => $row['MaPhong'],
                'trang_thai_phong' => $row['TrangThaiPhong'],
                'ma_loai_phong' => $row['MaLoaiPhong'],
                'ten_loai_phong' => $row['TenLoaiPhong'],
                'so_luot_danh_gia' => $n,
                'tong_so_sao' => $s,
                'diem_trung_binh' => $diemTB, // null nếu N = 0
                'so_luot_1_sao' => $s1,
                'so_luot_2_sao' => $s2,
                'so_luot_3_sao' => $s3,
                'so_luot_4_sao' => $s4,
                'so_luot_5_sao' => $s5
            ];
        }

        $diemTBToanBo = $tongLuot > 0 ? round($tongSoSao / $tongLuot, 2) : null;

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'che_do' => 'room',
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all',
                'keyword' => $keyword
            ],
            'tong_quan' => [
                'tong_luot_danh_gia' => $tongLuot,
                'diem_trung_binh' => $diemTBToanBo,
                'so_phong_co_danh_gia' => $soPhongCoDanhGia,
                'so_luot_1_2_sao' => $soLuot12Sao,
                'so_danh_gia_1_2_sao' => $soLuot12Sao,
                'tong_so_phong' => count($danhSach)
            ],
            'danh_sach' => $danhSach
        ];
    }

    /**
     * Lấy danh sách đánh giá chi tiết theo loại phòng hoặc theo phòng cụ thể (UC21.3).
     */
    public function danhSachChiTietDanhGia(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $maLoaiPhong = $filters['ma_loai_phong'] ?? null;
        $maPhong = $filters['ma_phong'] ?? null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $params = [
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ];

        $where = "d.NgayDanhGia >= :fromDate AND d.NgayDanhGia <= :toDate AND d.SoSao BETWEEN 1 AND 5";

        if (!empty($maLoaiPhong) && $maLoaiPhong !== 'all') {
            $where .= " AND p.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $maLoaiPhong;
        }

        if (!empty($maPhong) && $maPhong !== 'all') {
            $where .= " AND d.MaPhong = :maPhong";
            $params[':maPhong'] = $maPhong;
        }

        $sql = "SELECT 
                    d.MaDanhGia,
                    d.SoSao,
                    d.NoiDung,
                    d.NgayDanhGia,
                    d.MaPhieu,
                    d.MaPhong,
                    p.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    k.MaKH,
                    k.HoTen AS HoTenKhachHang
                FROM DanhGia d
                JOIN Phong p ON d.MaPhong = p.MaPhong
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN KhachHang k ON d.MaKH = k.MaKH
                WHERE {$where}
                ORDER BY d.NgayDanhGia DESC, d.MaDanhGia DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * THỐNG KÊ SỐ LƯỢNG / TÌNH TRẠNG PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê số lượng phòng, trạng thái sử dụng và phân bổ loại phòng.
     */
    public function thongKeSoLuongPhong(): array
    {
        // 1. Tổng số phòng và phân loại theo trạng thái
        $sqlTrangThai = "SELECT 
                            TrangThaiPhong,
                            COUNT(*) AS SoLuong
                         FROM Phong
                         GROUP BY TrangThaiPhong";
        $stmtTT = $this->db->query($sqlTrangThai);
        $trangThaiList = $stmtTT->fetchAll(PDO::FETCH_ASSOC);

        $soLuongTheoTrangThai = [
            'Trong' => 0,
            'DaDat' => 0,
            'DangSuDung' => 0,
            'BaoTri' => 0,
        ];
        $tongPhong = 0;

        foreach ($trangThaiList as $row) {
            $tt = $row['TrangThaiPhong'];
            $sl = (int)$row['SoLuong'];
            $tongPhong += $sl;
            if (isset($soLuongTheoTrangThai[$tt])) {
                $soLuongTheoTrangThai[$tt] = $sl;
            } else {
                $soLuongTheoTrangThai[$tt] = $sl;
            }
        }

        // Tỷ lệ lấp đầy phòng (%) = (Đang sử dụng + Đã đặt) / Tổng phòng
        $phongBan = $soLuongTheoTrangThai['DangSuDung'] + $soLuongTheoTrangThai['DaDat'];
        $tyLeLapDay = $tongPhong > 0 ? round(($phongBan / $tongPhong) * 100, 1) : 0;

        // 2. Thống kê theo Loại Phòng
        $sqlLoaiPhong = "SELECT 
                            lp.MaLoaiPhong,
                            lp.TenLoaiPhong,
                            lp.Gia,
                            lp.SoNguoiToiDa,
                            COUNT(p.MaPhong) AS TongSoPhong,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'Trong' THEN 1 ELSE 0 END), 0) AS PhongTrong,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'DangSuDung' THEN 1 ELSE 0 END), 0) AS PhongDangO,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'DaDat' THEN 1 ELSE 0 END), 0) AS PhongDaDat,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'BaoTri' THEN 1 ELSE 0 END), 0) AS PhongBaoTri
                         FROM LoaiPhong lp
                         LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
                         GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa";
        $stmtLP = $this->db->query($sqlLoaiPhong);
        $loaiPhongList = $stmtLP->fetchAll(PDO::FETCH_ASSOC);

        // 3. Danh sách toàn bộ phòng và trạng thái chi tiết
        $sqlDanhSachPhong = "SELECT 
                                p.MaPhong,
                                p.TrangThaiPhong,
                                lp.TenLoaiPhong,
                                lp.Gia
                             FROM Phong p
                             JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                             ORDER BY p.MaPhong ASC";
        $stmtDSP = $this->db->query($sqlDanhSachPhong);
        $dsPhong = $stmtDSP->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tong_so_phong' => $tongPhong,
            'ty_le_lap_day' => $tyLeLapDay,
            'theo_trang_thai' => $soLuongTheoTrangThai,
            'theo_loai_phong' => $loaiPhongList,
            'danh_sach_phong' => $dsPhong,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ PHIẾU ĐẶT PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê số lượng phiếu nhận phòng theo trạng thái, kênh đặt và thời gian.
     */
    public function thongKeSoLuongPhieu(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');

        // 1. Thống kê theo trạng thái phiếu
        $sqlTrangThai = "SELECT 
                            TrangThai,
                            COUNT(*) AS SoLuong
                         FROM PhieuNhanPhong
                         WHERE NgayLap >= :fromDate AND NgayLap <= :toDate
                         GROUP BY TrangThai";
        $stmtTT = $this->db->prepare($sqlTrangThai);
        $stmtTT->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $trangThaiPhieu = $stmtTT->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 2. Thống kê theo kênh đặt
        $sqlKenhDat = "SELECT 
                          KenhDat,
                          COUNT(*) AS SoLuong
                       FROM PhieuNhanPhong
                       WHERE NgayLap >= :fromDate AND NgayLap <= :toDate
                       GROUP BY KenhDat";
        $stmtKenh = $this->db->prepare($sqlKenhDat);
        $stmtKenh->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $kenhDatPhieu = $stmtKenh->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 3. Tổng số phiếu
        $tongSoPhieu = array_sum($trangThaiPhieu);

        // 4. Danh sách các phiếu mới nhất kèm thông tin
        $sqlRecent = "SELECT 
                        pnp.MaPhieu,
                        pnp.NgayLap,
                        pnp.KenhDat,
                        pnp.TrangThai,
                        kh.HoTen AS TenKhachHang,
                        kh.SDT,
                        COALESCE(hd.TongTienSauVAT, 0) AS TongTien
                      FROM PhieuNhanPhong pnp
                      JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                      LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
                      ORDER BY pnp.NgayLap DESC
                      LIMIT 10";
        $stmtRecent = $this->db->query($sqlRecent);
        $danhSachPhieu = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_so_phieu' => $tongSoPhieu,
            'theo_trang_thai' => $trangThaiPhieu,
            'theo_kenh_dat' => $kenhDatPhieu,
            'danh_sach_phieu' => $danhSachPhieu,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ DỊCH VỤ SỬ DỤNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê dịch vụ sử dụng: top dịch vụ tiêu thụ, doanh thu và tồn kho.
     */
    public function thongKeDichVu(): array
    {
        // 1. Top dịch vụ sử dụng nhiều nhất từ bảng CTHD
        $sqlTopDichVu = "SELECT 
                            ctdv.MaCTDV,
                            ctdv.TenCTDV,
                            dv.TenDV AS NhomDichVu,
                            COUNT(cthd.MaCTHD) AS SoLuotDat,
                            COALESCE(SUM(cthd.SoLuong), 0) AS TongSoLuongTieuThu,
                            COALESCE(SUM(cthd.SoLuong * cthd.DonGia), 0) AS TongDoanhThuDichVu
                         FROM CTHD cthd
                         JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
                         JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                         GROUP BY ctdv.MaCTDV, ctdv.TenCTDV, dv.TenDV
                         ORDER BY TongDoanhThuDichVu DESC";
        $stmtTop = $this->db->query($sqlTopDichVu);
        $topDichVu = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        // 2. Thống kê theo nhóm dịch vụ
        $sqlNhom = "SELECT 
                        dv.MaDV,
                        dv.TenDV,
                        COUNT(ctdv.MaCTDV) AS SoLuongMatHang,
                        COALESCE(SUM(ctdv.SoLuongTon), 0) AS TongTonKho
                    FROM DichVu dv
                    LEFT JOIN ChiTietDichVu ctdv ON dv.MaDV = ctdv.MaDV
                    GROUP BY dv.MaDV, dv.TenDV";
        $stmtNhom = $this->db->query($sqlNhom);
        $nhomDichVu = $stmtNhom->fetchAll(PDO::FETCH_ASSOC);

        // 3. Tình trạng tồn kho của các mặt hàng
        $sqlTonKho = "SELECT 
                        ctdv.MaCTDV,
                        ctdv.TenCTDV,
                        ctdv.DonGia,
                        ctdv.SoLuongTon,
                        dv.TenDV AS NhomDichVu,
                        CASE 
                            WHEN ctdv.SoLuongTon = 0 THEN 'HetHang'
                            WHEN ctdv.SoLuongTon < 10 THEN 'SapHet'
                            ELSE 'ConHang'
                        END AS TrangThaiKho
                      FROM ChiTietDichVu ctdv
                      JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                      ORDER BY ctdv.SoLuongTon ASC";
        $stmtTonKho = $this->db->query($sqlTonKho);
        $tonKho = $stmtTonKho->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuDichVu = 0.0;
        foreach ($topDichVu as $item) {
            $tongDoanhThuDichVu += (float)$item['TongDoanhThuDichVu'];
        }

        return [
            'tong_doanh_thu_dich_vu' => $tongDoanhThuDichVu,
            'top_dich_vu' => $topDichVu,
            'nhom_dich_vu' => $nhomDichVu,
            'ton_kho_dich_vu' => $tonKho,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ TỔNG QUAN DASHBOARD (BE4)
     * ========================================================================= */

    /**
     * Tổng hợp các chỉ số KPI trọng yếu nhất phục vụ trang chủ Admin.
     */
    public function thongKeTongQuan(): array
    {
        $doanhThu = $this->thongKeDoanhThu();
        $phong = $this->thongKeSoLuongPhong();
        $phieu = $this->thongKeSoLuongPhieu();
        $dichVu = $this->thongKeDichVu();

        return [
            'status' => 'success',
            'kpi' => [
                'tong_doanh_thu' => $doanhThu['tong_quan']['tong_doanh_thu'],
                'tong_giao_dich' => $doanhThu['tong_quan']['tong_giao_dich'],
                'tong_phong' => $phong['tong_so_phong'],
                'ty_le_lap_day' => $phong['ty_le_lap_day'],
                'phong_dang_o' => $phong['theo_trang_thai']['DangSuDung'] ?? 0,
                'phong_trong' => $phong['theo_trang_thai']['Trong'] ?? 0,
                'tong_phieu_dat' => $phieu['tong_so_phieu'],
                'doanh_thu_dich_vu' => $dichVu['tong_doanh_thu_dich_vu'],
            ],
            'chi_tiet_doanh_thu' => $doanhThu['tong_quan'],
            'bieu_do_doanh_thu' => $doanhThu['bieu_do_doanh_thu'],
            'trang_thai_phong' => $phong['theo_trang_thai'],
            'giao_dich_gan_nhat' => $doanhThu['giao_dich_gan_nhat'],
            'phieu_dat_gan_nhat' => $phieu['danh_sach_phieu'],
            'top_dich_vu' => array_slice($dichVu['top_dich_vu'], 0, 5),
        ];
    }

    /* =========================================================================
     * QUẢN LÝ NHÂN VIÊN & LỄ TÂN (Hỗ trợ chia sẻ module)
     * ========================================================================= */

    public function thongKeSoLuongNhanVien(): array
    {
        $sqlAdmin = "SELECT COUNT(*) FROM Admin";
        $sqlLeTan = "SELECT COUNT(*) FROM LeTan";
        $soAdmin = (int)$this->db->query($sqlAdmin)->fetchColumn();
        $soLeTan = (int)$this->db->query($sqlLeTan)->fetchColumn();

        return [
            'tong_nhan_su' => $soAdmin + $soLeTan,
            'so_admin' => $soAdmin,
            'so_le_tan' => $soLeTan,
        ];
    }
}