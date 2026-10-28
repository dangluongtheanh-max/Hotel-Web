<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý Sơ đồ trạng thái phòng, Bảo trì,
 * Người lưu trú và Dịch vụ nhanh cho phòng đang lưu trú.
 */
class LeTanPhongVaKhachRepository
{
    private ?Database $db;
    private ?LeTanPhieuRepository $phieuRepo;

    public function __construct(?Database $db = null, ?LeTanPhieuRepository $phieuRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Kiểm tra phòng vật lý có đang ở trạng thái Trống hay không.
     * Tiêu chí thống nhất:
     * 1. Cột Phong.TrangThaiPhong = 'Trong'
     * 2. Không có phiếu nào đang lưu trú thực tế trong phòng đó (TrangThai = 'DangLuuTru' trong ChiTietPhieuNhanPhong)
     */
    public function kiemTraPhongCoTrong(string $maPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            SELECT p.TrangThaiPhong,
                   (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp 
                    WHERE ctp.MaPhong = p.MaPhong AND ctp.TrangThai = 'DangLuuTru') AS SoKhachDangO
            FROM Phong p
            WHERE p.MaPhong = ?
            LIMIT 1
        ");
        $stmt->execute([$maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Phòng được coi là trống / sẵn sàng nhận phòng:
        // - Không ở trạng thái DangSuDung (đang có khách ở) và không ở trạng thái BaoTri
        // - Không có phiếu nào khác đang lưu trú thực tế trong phòng đó (SoKhachDangO = 0)
        // Các trạng thái 'Trong' hoặc 'DaDat' (đã giữ chỗ cho khách đến nhận) đều hợp lệ.
        $isVacant = in_array($row['TrangThaiPhong'], ['Trong', 'DaDat'], true);
        return ($isVacant && (int)$row['SoKhachDangO'] === 0);
    }

    /**
     * Cập nhật chuyển phòng cho người lưu trú
     */
    public function capNhatPhongNguoiLuuTru(string $maPhieu, string $maNLT, string $maPhongMoi): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE NguoiLuuTru 
            SET MaPhong = ? 
            WHERE MaNguoiLuuTru = ? AND MaPhieu = ?
        ");
        return $stmt->execute([$maPhongMoi, $maNLT, $maPhieu]);
    }

    /**
     * Cập nhật thông tin giấy tờ, họ tên của người lưu trú
     */
    public function capNhatThongTinNLT(string $maPhieu, string $maNLT, array $data): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE NguoiLuuTru 
            SET HoTen = :hoTen,
                NgaySinh = :ngaySinh,
                QuocTich = :quocTich,
                LoaiGiayTo = :loaiGiayTo,
                SoGiayTo = :soGiayTo,
                GhiChu = :ghiChu
            WHERE MaNguoiLuuTru = :maNLT AND MaPhieu = :maPhieu
        ");
        return $stmt->execute([
            ':hoTen' => $data['hoTen'],
            ':ngaySinh' => $data['ngaySinh'],
            ':quocTich' => $data['quocTich'],
            ':loaiGiayTo' => $data['loaiGiayTo'],
            ':soGiayTo' => $data['soGiayTo'],
            ':ghiChu' => $data['ghiChu'] ?? null,
            ':maNLT' => $maNLT,
            ':maPhieu' => $maPhieu
        ]);
    }

    /**
     * Lấy thống kê KPI toàn khách sạn, danh sách các loại phòng kèm chỉ số phòng,
     * và danh sách chi tiết các phòng kèm thông tin người đứng phiếu và người lưu trú thực tế.
     */
    public function getThongKeLoaiPhongVaPhong(?string $maLoaiPhong = null): array
    {
        // 1. Tự động quét và hủy các phiếu quá hạn 24h & quét giải phóng phòng quá hạn 20 phút ChoThanhToan
        $this->phieuRepo->tuDongHuyPhieuQuaHan24h();
        try {
            (new \App\ThanhToan\Services\ThanhToanService())->quetVaXuLyTatCaPhieuHetHan();
        } catch (\Throwable $e) {}

        $db = $this->getConnection();

        // 2. Thống kê KPI tổng thể khách sạn
        $kpiRow = $db->query("
            SELECT 
                COUNT(*) as tongSoPhong,
                SUM(CASE WHEN TrangThaiPhong = 'Trong' THEN 1 ELSE 0 END) as soPhongTrong,
                SUM(CASE WHEN TrangThaiPhong = 'ChoThanhToan' THEN 1 ELSE 0 END) as soPhongChoThanhToan,
                SUM(CASE WHEN TrangThaiPhong = 'DangSuDung' THEN 1 ELSE 0 END) as soPhongDangO,
                SUM(CASE WHEN TrangThaiPhong = 'DaDat' THEN 1 ELSE 0 END) as soPhongDaDat,
                SUM(CASE WHEN TrangThaiPhong = 'BaoTri' THEN 1 ELSE 0 END) as soPhongBaoTri
            FROM Phong
        ")->fetch(PDO::FETCH_ASSOC);

        $tongSoPhong = (int)($kpiRow['tongSoPhong'] ?? 0);
        $soPhongTrong = (int)($kpiRow['soPhongTrong'] ?? 0);
        $soPhongChoThanhToan = (int)($kpiRow['soPhongChoThanhToan'] ?? 0);
        $soPhongDangO = (int)($kpiRow['soPhongDangO'] ?? 0);
        $soPhongDaDat = (int)($kpiRow['soPhongDaDat'] ?? 0);
        $soPhongBaoTri = (int)($kpiRow['soPhongBaoTri'] ?? 0);
        $tyLeLapDay = $tongSoPhong > 0 
            ? round((($soPhongDangO + $soPhongDaDat + $soPhongChoThanhToan) / $tongSoPhong) * 100, 1) 
            : 0.0;

        $kpi = [
            'tongSoPhong' => $tongSoPhong,
            'soPhongTrong' => $soPhongTrong,
            'soPhongChoThanhToan' => $soPhongChoThanhToan,
            'soPhongDangO' => $soPhongDangO,
            'soPhongDaDat' => $soPhongDaDat,
            'soPhongBaoTri' => $soPhongBaoTri,
            'tyLeLapDay' => $tyLeLapDay
        ];

        // 3. Danh sách Loại phòng kèm thống kê phòng
        $lpSql = "
            SELECT 
                lp.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia,
                lp.SoNguoiToiDa,
                lp.MoTa,
                lp.TienNghi,
                lp.TinhTrang,
                COUNT(p.MaPhong) as tongPhong,
                SUM(CASE WHEN p.TrangThaiPhong = 'Trong' THEN 1 ELSE 0 END) as soPhongTrong,
                SUM(CASE WHEN p.TrangThaiPhong = 'ChoThanhToan' THEN 1 ELSE 0 END) as soPhongChoThanhToan,
                SUM(CASE WHEN p.TrangThaiPhong = 'DangSuDung' THEN 1 ELSE 0 END) as soPhongDangO,
                SUM(CASE WHEN p.TrangThaiPhong = 'DaDat' THEN 1 ELSE 0 END) as soPhongDaDat,
                SUM(CASE WHEN p.TrangThaiPhong = 'BaoTri' THEN 1 ELSE 0 END) as soPhongBaoTri
            FROM LoaiPhong lp
            LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
        ";
        $lpParams = [];
        if (!empty($maLoaiPhong)) {
            $lpSql .= " WHERE lp.MaLoaiPhong = ?";
            $lpParams[] = $maLoaiPhong;
        }
        $lpSql .= " GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa, lp.MoTa, lp.TienNghi, lp.TinhTrang ORDER BY lp.MaLoaiPhong ASC";

        $lpStmt = $db->prepare($lpSql);
        $lpStmt->execute($lpParams);
        $loaiPhongs = $lpStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($loaiPhongs as &$lp) {
            $lp['tongPhong'] = (int)$lp['tongPhong'];
            $lp['soPhongTrong'] = (int)$lp['soPhongTrong'];
            $lp['soPhongChoThanhToan'] = (int)($lp['soPhongChoThanhToan'] ?? 0);
            $lp['soPhongDangO'] = (int)$lp['soPhongDangO'];
            $lp['soPhongDaDat'] = (int)$lp['soPhongDaDat'];
            $lp['soPhongBaoTri'] = (int)($lp['soPhongBaoTri'] ?? 0);
            $lp['tyLeSuDung'] = $lp['tongPhong'] > 0
                ? round((($lp['soPhongDangO'] + $lp['soPhongDaDat'] + $lp['soPhongChoThanhToan']) / $lp['tongPhong']) * 100, 1)
                : 0.0;
        }
        unset($lp);

        // 4. Danh sách chi tiết các phòng kèm thông tin phiếu đang hoạt động
        $phongSql = "
            SELECT 
                p.MaPhong,
                p.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia,
                lp.SoNguoiToiDa,
                p.TrangThaiPhong,
                ctp.MaPhieu,
                ctp.TrangThai as TrangThaiLuuTru,
                ctp.ThoiGianNhanPhong,
                ctp.ThoiGianTraPhong,
                kh.HoTen as TenNguoiDat,
                kh.SDT as SDTNguoiDat
            FROM Phong p
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            LEFT JOIN (
                SELECT c1.* 
                FROM ChiTietPhieuNhanPhong c1
                INNER JOIN (
                    SELECT ctp_sub.MaPhong, MAX(ctp_sub.MaPhieu) as MaxMaPhieu
                    FROM ChiTietPhieuNhanPhong ctp_sub
                    JOIN Phong p_sub ON ctp_sub.MaPhong = p_sub.MaPhong
                    WHERE (p_sub.TrangThaiPhong = 'DangSuDung' AND ctp_sub.TrangThai = 'DangLuuTru')
                       OR (p_sub.TrangThaiPhong = 'DaDat' AND ctp_sub.TrangThai = 'DaDatPhong')
                       OR (p_sub.TrangThaiPhong = 'ChoThanhToan' AND ctp_sub.TrangThai = 'ChoThanhToan')
                    GROUP BY ctp_sub.MaPhong
                ) c2 ON c1.MaPhong = c2.MaPhong AND c1.MaPhieu = c2.MaxMaPhieu
            ) ctp ON p.MaPhong = ctp.MaPhong AND (
                (p.TrangThaiPhong = 'DangSuDung' AND ctp.TrangThai = 'DangLuuTru')
                OR (p.TrangThaiPhong = 'DaDat' AND ctp.TrangThai = 'DaDatPhong')
                OR (p.TrangThaiPhong = 'ChoThanhToan' AND ctp.TrangThai = 'ChoThanhToan')
            )
            LEFT JOIN PhieuNhanPhong pnp ON ctp.MaPhieu = pnp.MaPhieu
            LEFT JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
        ";
        $phongParams = [];
        if (!empty($maLoaiPhong)) {
            $phongSql .= " WHERE p.MaLoaiPhong = ?";
            $phongParams[] = $maLoaiPhong;
        }
        $phongSql .= " ORDER BY p.MaLoaiPhong ASC, p.MaPhong ASC";

        $phongStmt = $db->prepare($phongSql);
        $phongStmt->execute($phongParams);
        $phongs = $phongStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Lấy danh sách người lưu trú thực tế cho các phòng đang có phiếu
        $activePhieuList = array_values(array_filter(array_unique(array_column($phongs, 'MaPhieu'))));
        $nltMap = [];
        if (!empty($activePhieuList)) {
            $placeholders = implode(',', array_fill(0, count($activePhieuList), '?'));
            $nltSql = "
                SELECT MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong
                FROM NguoiLuuTru
                WHERE MaPhieu IN ($placeholders)
            ";
            $nltStmt = $db->prepare($nltSql);
            $nltStmt->execute($activePhieuList);
            $nlts = $nltStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($nlts as $nlt) {
                $key = $nlt['MaPhieu'] . '_' . $nlt['MaPhong'];
                $nltMap[$key][] = $nlt;
            }
        }

        foreach ($phongs as &$phong) {
            if (!empty($phong['MaPhieu'])) {
                $key = $phong['MaPhieu'] . '_' . $phong['MaPhong'];
                $phong['danhSachNguoiLuuTru'] = $nltMap[$key] ?? [];
            } else {
                $phong['danhSachNguoiLuuTru'] = [];
            }
        }
        unset($phong);

        return [
            'kpi' => $kpi,
            'loaiPhong' => $loaiPhongs,
            'danhSachPhong' => $phongs
        ];
    }

    /**
     * Cập nhật chuyển đổi trạng thái phòng giữa 'Trong' và 'BaoTri'
     */
    public function capNhatTrangThaiBaoTri(string $maPhong, string $trangThaiMoi): array
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT MaPhong, TrangThaiPhong, MaLoaiPhong FROM Phong WHERE MaPhong = ?");
        $stmt->execute([$maPhong]);
        $phong = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phong) {
            throw new \Exception("Không tìm thấy thông tin phòng {$maPhong}.", 404);
        }

        $currentStatus = $phong['TrangThaiPhong'];
        if ($trangThaiMoi === 'Trong') {
            // Chuyển từ BaoTri sang Trong
            if ($currentStatus !== 'BaoTri') {
                throw new \Exception("Phòng {$maPhong} hiện không ở trạng thái Bảo trì (hiện tại: {$currentStatus}).", 400);
            }
        } elseif ($trangThaiMoi === 'BaoTri') {
            // Chuyển từ Trong sang BaoTri
            if ($currentStatus !== 'Trong') {
                throw new \Exception("Chỉ có thể chuyển phòng đang Trống sang Bảo trì (phòng {$maPhong} hiện tại: {$currentStatus}).", 400);
            }
        } else {
            throw new \Exception("Trạng thái mới không hợp lệ. Chỉ chấp nhận 'Trong' hoặc 'BaoTri'.", 400);
        }

        $stmtUp = $db->prepare("UPDATE Phong SET TrangThaiPhong = ? WHERE MaPhong = ?");
        $stmtUp->execute([$trangThaiMoi, $maPhong]);

        return [
            'maPhong' => $maPhong,
            'trangThaiCu' => $currentStatus,
            'trangThaiMoi' => $trangThaiMoi,
            'message' => ($trangThaiMoi === 'Trong') 
                ? "Hoàn tất bảo trì/dọn dẹp cho phòng {$maPhong}. Phòng đã sẵn sàng đón khách!" 
                : "Đã chuyển phòng {$maPhong} sang trạng thái Bảo trì / Chờ dọn dẹp."
        ];
    }

    /**
     * Lấy danh mục dịch vụ khả dụng trong khách sạn (Đồ ăn, giặt ủi, thuê xe...)
     */
    public function layDanhSachDichVuKhaDung(): array
    {
        $db = $this->getConnection();
        $sql = "
            SELECT 
                dv.MaDV,
                dv.TenDV,
                dv.MoTa as MoTaDV,
                ct.MaCTDV,
                ct.TenCTDV,
                ct.MoTa as MoTaCTDV,
                ct.DonGia,
                ct.SoLuongTon
            FROM DichVu dv
            JOIN ChiTietDichVu ct ON dv.MaDV = ct.MaDV
            WHERE dv.TrangThai = 'DangCungCap' AND dv.MaDV != 'DVPhuThu'
            ORDER BY dv.MaDV ASC, ct.TenCTDV ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];
        foreach ($rows as $r) {
            $maDV = $r['MaDV'];
            if (!isset($groups[$maDV])) {
                $groups[$maDV] = [
                    'maDV' => $maDV,
                    'tenDV' => $r['TenDV'],
                    'moTa' => $r['MoTaDV'],
                    'danhSach' => []
                ];
            }
            $groups[$maDV]['danhSach'][] = [
                'maCTDV' => $r['MaCTDV'],
                'tenCTDV' => $r['TenCTDV'],
                'moTa' => $r['MoTaCTDV'],
                'donGia' => (float)$r['DonGia'],
                'soLuongTon' => (int)$r['SoLuongTon']
            ];
        }

        return array_values($groups);
    }

    /**
     * Đặt dịch vụ nhanh cho phòng đang lưu trú
     */
    public function datDichVuNhanhChoPhong(array $data): array
    {
        $db = $this->getConnection();
        $maPhong = trim($data['maPhong'] ?? '');
        $maCTDV = trim($data['maCTDV'] ?? '');
        $soLuong = max(1, (int)($data['soLuong'] ?? 1));
        $ghiChu = trim($data['ghiChu'] ?? '');
        $thoiGianNhan = !empty($data['thoiGianNhan']) ? $data['thoiGianNhan'] : null;
        $thoiGianTra = !empty($data['thoiGianTra']) ? $data['thoiGianTra'] : null;

        if (empty($maPhong)) {
            throw new \Exception("Vui lòng cung cấp mã phòng cần đặt dịch vụ.", 400);
        }
        if (empty($maCTDV)) {
            throw new \Exception("Vui lòng chọn dịch vụ chi tiết cần đặt.", 400);
        }

        // 1. Kiểm tra phòng và tìm mã phiếu đang lưu trú
        $stmtPhieu = $db->prepare("
            SELECT ctp.MaPhieu, ctp.MaPhong, pnp.MaKH, kh.HoTen as TenKH
            FROM ChiTietPhieuNhanPhong ctp
            JOIN PhieuNhanPhong pnp ON ctp.MaPhieu = pnp.MaPhieu
            LEFT JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            WHERE ctp.MaPhong = ? AND ctp.TrangThai = 'DangLuuTru'
            ORDER BY ctp.MaPhieu DESC LIMIT 1
        ");
        $stmtPhieu->execute([$maPhong]);
        $activeRoom = $stmtPhieu->fetch(PDO::FETCH_ASSOC);

        if (!$activeRoom) {
            throw new \Exception("Phòng {$maPhong} hiện không có khách đang lưu trú (hoặc chưa check-in). Không thể đặt dịch vụ.", 400);
        }

        $maPhieu = $activeRoom['MaPhieu'];

        // 2. Lấy thông tin dịch vụ chi tiết
        $stmtDV = $db->prepare("
            SELECT ct.MaCTDV, ct.TenCTDV, ct.DonGia, ct.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ct
            JOIN DichVu dv ON ct.MaDV = dv.MaDV
            WHERE ct.MaCTDV = ? AND dv.TrangThai = 'DangCungCap'
        ");
        $stmtDV->execute([$maCTDV]);
        $dvInfo = $stmtDV->fetch(PDO::FETCH_ASSOC);

        if (!$dvInfo) {
            throw new \Exception("Dịch vụ được chọn không tồn tại hoặc tạm ngưng cung cấp.", 404);
        }

        $donGia = (float)$dvInfo['DonGia'];
        $thanhTien = $donGia * $soLuong;

        // 3. Insert vào ctsudungdichvu
        $stmtInsert = $db->prepare("
            INSERT INTO ctsudungdichvu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong, ThoiGianNhan, ThoiGianTra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu ?: "Đặt nhanh tại phòng {$maPhong}",
            $soLuong,
            $thoiGianNhan,
            $thoiGianTra
        ]);

        $insertId = $db->lastInsertId();

        return [
            'maCTSDDV' => $insertId,
            'maPhieu' => $maPhieu,
            'maPhong' => $maPhong,
            'tenKhach' => $activeRoom['TenKH'] ?? 'Khách lưu trú',
            'tenDichVu' => $dvInfo['TenDV'],
            'tenCTDV' => $dvInfo['TenCTDV'],
            'soLuong' => $soLuong,
            'donGia' => $donGia,
            'thanhTien' => $thanhTien,
            'ghiChu' => $ghiChu,
            'thoiGianNhan' => $thoiGianNhan,
            'thoiGianTra' => $thoiGianTra,
            'message' => "Đặt thành công dịch vụ '{$dvInfo['TenCTDV']}' (x{$soLuong}) cho phòng {$maPhong}!"
        ];
    }
}
