<?php

namespace App\Admin\Repositories;

use App\Admin\Models\Admin;
use Database\Database;
use PDO;

/**
 * Repository cho module Admin & Báo Cáo Thống Kê (BE4).
 * Chịu trách nhiệm toàn bộ các câu truy vấn cơ sở dữ liệu thực tế liên quan đến
 * Doanh thu, Phiếu đặt phòng, Tình trạng phòng, Dịch vụ sử dụng và Nhân sự.
 */
class AdminRepository
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
                        COALESCE(SUM(SoTienHoan), 0) AS TongTienHoan,
                        COALESCE(SUM(CASE WHEN HinhThuc = 'ChuyenKhoan' THEN SoTien ELSE 0 END), 0) AS DoanhThuChuyenKhoan,
                        COALESCE(SUM(CASE WHEN HinhThuc = 'TienMat' THEN SoTien ELSE 0 END), 0) AS DoanhThuTienMat
                       FROM ThanhToan
                       WHERE TrangThaiThanhToan = 'HoanTat'
                         AND ThoiGianThanhToan >= :fromDate
                         AND ThoiGianThanhToan <= :toDate";
        $stmt = $this->db->prepare($sqlTongThu);
        $stmt->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $tongQuanDoanhThu = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 2. Cơ cấu doanh thu: Tiền Phòng vs Tiền Dịch Vụ từ bảng CTHD
        $sqlCoCau = "SELECT 
                        COALESCE(SUM(CASE WHEN cthd.MaPhong IS NOT NULL THEN (cthd.SoDem * cthd.DonGia) ELSE 0 END), 0) AS DoanhThuPhong,
                        COALESCE(SUM(CASE WHEN cthd.MaCTDV IS NOT NULL THEN (cthd.SoLuong * cthd.DonGia) ELSE 0 END), 0) AS DoanhThuDichVu
                     FROM CTHD cthd
                     JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
                     JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                     WHERE tt.TrangThaiThanhToan = 'HoanTat'
                       AND tt.ThoiGianThanhToan >= :fromDate
                       AND tt.ThoiGianThanhToan <= :toDate";
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
                            tt.ThoiGianThanhToan, tt.TrangThaiThanhToan, tt.SoTienHoan,
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
                'tong_tien_hoan' => (float)($tongQuanDoanhThu['TongTienHoan'] ?? 0),
                'doanh_thu_chuyen_khoan' => (float)($tongQuanDoanhThu['DoanhThuChuyenKhoan'] ?? 0),
                'doanh_thu_tien_mat' => (float)($tongQuanDoanhThu['DoanhThuTienMat'] ?? 0),
                'doanh_thu_phong' => (float)($coCau['DoanhThuPhong'] ?? 0),
                'doanh_thu_dich_vu' => (float)($coCau['DoanhThuDichVu'] ?? 0),
            ],
            'bieu_do_doanh_thu' => $timeline,
            'giao_dich_gan_nhat' => $dsGiaoDich,
        ];
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

    public function getAllNhanVien(array $filters = []): array
    {
        $sql = "SELECT lt.MaLeTan AS MaNV, lt.HoTen, lt.SDT, lt.Email, 'LeTan' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM LeTan lt
                JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
                UNION ALL
                SELECT adm.MaAdmin AS MaNV, adm.HoTen, adm.SDT, adm.Email, 'Admin' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM Admin adm
                JOIN TaiKhoan tk ON adm.MaTK = tk.MaTK";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findNhanVienById(string $id): ?array
    {
        $sql = "SELECT lt.MaLeTan AS MaNV, lt.HoTen, lt.SDT, lt.Email, 'LeTan' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.MaTK
                FROM LeTan lt
                JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
                WHERE lt.MaLeTan = :id
                UNION ALL
                SELECT adm.MaAdmin AS MaNV, adm.HoTen, adm.SDT, adm.Email, 'Admin' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.MaTK
                FROM Admin adm
                JOIN TaiKhoan tk ON adm.MaTK = tk.MaTK
                WHERE adm.MaAdmin = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateTrangThaiTaiKhoan(string $maTK, string $trangThai): bool
    {
        $stmt = $this->db->prepare("UPDATE TaiKhoan SET TrangThaiTaiKhoan = :trangThai WHERE MaTK = :maTK");
        return $stmt->execute([':trangThai' => $trangThai, ':maTK' => $maTK]);
    }

    public function findAdminById(string $id): ?Admin
    {
        $stmt = $this->db->prepare("SELECT * FROM Admin WHERE MaAdmin = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new Admin($row['MaAdmin'], $row['HoTen'], $row['SDT'], $row['Email'], $row['MaTK']);
    }

    public function getAllAdmin(array $filters = []): array
    {
        $stmt = $this->db->query("SELECT * FROM Admin");
        $list = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list[] = (new Admin($row['MaAdmin'], $row['HoTen'], $row['SDT'], $row['Email'], $row['MaTK']))->toArray();
        }
        return $list;
    }

    public function saveAdmin(Admin $admin): bool
    {
        $data = $admin->toArray();
        $stmt = $this->db->prepare("REPLACE INTO Admin (MaAdmin, HoTen, SDT, Email, MaTK) VALUES (:MaAdmin, :HoTen, :SDT, :Email, :MaTK)");
        return $stmt->execute($data);
    }

    public function deleteAdmin(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Admin WHERE MaAdmin = :id");
        return $stmt->execute([':id' => $id]);
    }
}
