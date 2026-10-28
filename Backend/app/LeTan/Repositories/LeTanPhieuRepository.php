<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý Phiếu nhận phòng và nghiệp vụ Check-in / Hủy phiếu.
 */
class LeTanPhieuRepository
{
    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Tự động quét và chuyển sang trạng thái DaHuy khi quá 24h tính từ ThoiGianNhanPhong
     * mà khách chưa đến nhận phòng (vẫn ở trạng thái DaDatPhong).
     */
    public function tuDongHuyPhieuQuaHan24h(): int
    {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            $lyDoAuto = "Khách không đến nhận phòng suốt thời gian đặt (No-show theo quy định - Phí hủy 100%)";

            // 1. Cập nhật các phòng trong ChiTietPhieuNhanPhong khi đã quá toàn bộ thời gian trả phòng mà khách chưa đến
            $sqlPhong = "
                UPDATE ChiTietPhieuNhanPhong
                SET TrangThai = 'DaHuy', LyDoHuy = ?
                WHERE TrangThai = 'DaDatPhong'
                  AND NOW() > ThoiGianTraPhong
            ";
            $stmt = $db->prepare($sqlPhong);
            $stmt->execute([$lyDoAuto]);
            $affectedRooms = $stmt->rowCount();

            // Trả trạng thái phòng trong bảng Phong về 'Trong'
            if ($affectedRooms > 0) {
                $db->exec("
                    UPDATE Phong p
                    JOIN ChiTietPhieuNhanPhong ctp ON p.MaPhong = ctp.MaPhong
                    SET p.TrangThaiPhong = 'Trong'
                    WHERE ctp.TrangThai = 'DaHuy' AND p.TrangThaiPhong = 'DaDat'
                ");
            }

            // 2. Chuyển phiếu cha sang DaHuy nếu toàn bộ phòng trong phiếu đã DaHuy
            $sqlPhieu = "
                UPDATE PhieuNhanPhong pnp
                SET pnp.TrangThai = 'DaHuy',
                    pnp.LyDoHuy = ?
                WHERE pnp.TrangThai = 'DaDatPhong'
                  AND NOT EXISTS (
                      SELECT 1 FROM ChiTietPhieuNhanPhong ctp 
                      WHERE ctp.MaPhieu = pnp.MaPhieu 
                        AND ctp.TrangThai != 'DaHuy'
                  )
            ";
            $stmt2 = $db->prepare($sqlPhieu);
            $stmt2->execute([$lyDoAuto]);

            if ($isOwner) {
                $db->commit();
            }
            return $affectedRooms;
        } catch (\Exception $e) {
            if ($isOwner && $db->inTransaction()) {
                $db->rollBack();
            }
            return 0;
        }
    }

    /**
     * Lấy danh sách phiếu đặt phòng theo bộ lọc trạng thái hoặc từ khóa
     */
    public function getDanhSachPhieu(array $filters = []): array
    {
        // 1. Quét và hủy phiếu Chờ thanh toán quá hạn 20 phút
        try {
            (new \App\ThanhToan\Services\ThanhToanService())->quetVaXuLyTatCaPhieuHetHan();
        } catch (\Throwable $e) {}

        // 2. Tự động quét và hủy các phiếu quá hạn 24h trước khi truy vấn
        $this->tuDongHuyPhieuQuaHan24h();

        $db = $this->getConnection();
        $params = [];
        $where = [];

        if (!empty($filters['trangThai'])) {
            $where[] = "pnp.TrangThai = ?";
            $params[] = $filters['trangThai'];
        }

        if (!empty($filters['tuKhoa'])) {
            $kw = '%' . trim($filters['tuKhoa']) . '%';
            $where[] = "(pnp.MaPhieu LIKE ? OR kh.HoTen LIKE ? OR kh.SDT LIKE ? OR tk.Email LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                pnp.MaPhieu,
                pnp.NgayLap,
                pnp.KenhDat,
                pnp.TrangThai,
                pnp.LyDoHuy,
                pnp.MaKH,
                pnp.MaLeTan,
                pnp.MaKM,
                kh.HoTen AS TenKH,
                kh.SDT,
                tk.Email,
                (SELECT MIN(ctp.ThoiGianNhanPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayNhan,
                (SELECT MAX(ctp.ThoiGianTraPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayTra,
                (SELECT GROUP_CONCAT(ctp.MaPhong SEPARATOR ', ') FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS DanhSachPhong,
                (SELECT GROUP_CONCAT(CONCAT(ctp.MaPhong, ':', ctp.TrangThai, ':', COALESCE(ctp.LyDoHuy, '')) SEPARATOR ' | ') FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS PhongVaTrangThai,
                (SELECT COALESCE(SUM(ctp.SoNguoi), 0) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS TongSoKhach,
                (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS SoPhong,
                hd.MaHD,
                hd.TongTienSauVAT
            FROM PhieuNhanPhong pnp
            JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK
            LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
            {$whereClause}
            ORDER BY 
                CASE pnp.TrangThai 
                    WHEN 'DaDatPhong' THEN 1 
                    WHEN 'DangLuuTru' THEN 2 
                    WHEN 'DaTraPhong' THEN 3 
                    WHEN 'DaHuy' THEN 4 
                    ELSE 5 
                END,
                pnp.NgayLap DESC,
                pnp.MaPhieu DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy đầy đủ chi tiết của 1 phiếu nhận phòng phục vụ đối soát Check-in / Check-out
     */
    public function getChiTietPhieu(string $maPhieu): ?array
    {
        $db = $this->getConnection();

        // 1. Thông tin phiếu & khách hàng
        $sqlPhieu = "
            SELECT 
                pnp.MaPhieu, pnp.NgayLap, pnp.KenhDat, pnp.TrangThai, pnp.LyDoHuy, pnp.MaKH, pnp.MaLeTan, pnp.MaKM,
                kh.HoTen AS TenKH, kh.SDT, tk.Email,
                lt.HoTen AS TenLeTan
            FROM PhieuNhanPhong pnp
            JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK
            LEFT JOIN LeTan lt ON pnp.MaLeTan = lt.MaLeTan
            WHERE pnp.MaPhieu = ?
            LIMIT 1
        ";
        $stmt = $db->prepare($sqlPhieu);
        $stmt->execute([$maPhieu]);
        $phieu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            return null;
        }

        // 2. Danh sách phòng thuộc phiếu kèm thông tin Lễ tân Check-in & Check-out
        $sqlPhong = "
            SELECT 
                ctp.MaPhong, ctp.ThoiGianNhanPhong, ctp.ThoiGianTraPhong, ctp.SoNguoi, ctp.TrangThai, ctp.LyDoHuy,
                ctp.MaLeTanCheckin, lt_in.HoTen AS TenLeTanCheckin,
                ctp.MaLeTanCheckout, lt_out.HoTen AS TenLeTanCheckout,
                ctp.MaLeTanHuy, lt_cancel.HoTen AS TenLeTanHuy,
                p.TrangThaiPhong,
                (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp2 WHERE ctp2.MaPhong = p.MaPhong AND ctp2.TrangThai = 'DangLuuTru') AS SoKhachDangO,
                lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa
            FROM ChiTietPhieuNhanPhong ctp
            JOIN Phong p ON ctp.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            LEFT JOIN LeTan lt_in ON ctp.MaLeTanCheckin = lt_in.MaLeTan
            LEFT JOIN LeTan lt_out ON ctp.MaLeTanCheckout = lt_out.MaLeTan
            LEFT JOIN LeTan lt_cancel ON ctp.MaLeTanHuy = lt_cancel.MaLeTan
            WHERE ctp.MaPhieu = ?
            ORDER BY ctp.MaPhong ASC
        ";
        $stmt = $db->prepare($sqlPhong);
        $stmt->execute([$maPhieu]);
        $danhSachPhong = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Danh sách người lưu trú đã khai báo
        $sqlNLT = "
            SELECT 
                nlt.MaNguoiLuuTru, nlt.HoTen, nlt.NgaySinh, nlt.QuocTich,
                nlt.LoaiGiayTo, nlt.SoGiayTo, nlt.GhiChu, nlt.MaPhong
            FROM NguoiLuuTru nlt
            WHERE nlt.MaPhieu = ?
            ORDER BY nlt.MaPhong ASC, nlt.MaNguoiLuuTru ASC
        ";
        $stmt = $db->prepare($sqlNLT);
        $stmt->execute([$maPhieu]);
        $danhSachNLT = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Hóa đơn và các giao dịch thanh toán
        $sqlHD = "SELECT * FROM HoaDon WHERE MaPhieu = ? LIMIT 1";
        $stmt = $db->prepare($sqlHD);
        $stmt->execute([$maPhieu]);
        $hoaDon = $stmt->fetch(PDO::FETCH_ASSOC);

        $danhSachThanhToan = [];
        if ($hoaDon) {
            $sqlTT = "SELECT * FROM ThanhToan WHERE MaHD = ? ORDER BY ThoiGianThanhToan ASC";
            $stmt = $db->prepare($sqlTT);
            $stmt->execute([$hoaDon['MaHD']]);
            $danhSachThanhToan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Dịch vụ đã sử dụng trong suốt kỳ lưu trú
        $danhSachDichVu = $this->getDichVuSuDung($maPhieu);

        return [
            'phieu' => $phieu,
            'danhSachPhong' => $danhSachPhong,
            'danhSachNguoiLuuTru' => $danhSachNLT,
            'hoaDon' => $hoaDon,
            'danhSachThanhToan' => $danhSachThanhToan,
            'danhSachDichVu' => $danhSachDichVu
        ];
    }

    /**
     * Lấy các dịch vụ đã sử dụng của phiếu (từ CTSuDungDichVu)
     */
    public function getDichVuSuDung(string $maPhieu): array
    {
        $db = $this->getConnection();
        $sql = "
            SELECT 
                sddv.MaCTSDDV, sddv.MaCTDV, sddv.MaPhong, sddv.DonGia, sddv.GhiChu,
                sddv.SoLuong, sddv.ThoiGianNhan, sddv.ThoiGianTra,
                ctdv.TenCTDV, ctdv.MaDV,
                dv.TenDV,
                (sddv.DonGia * sddv.SoLuong) AS ThanhTien
            FROM CTSuDungDichVu sddv
            JOIN ChiTietDichVu ctdv ON sddv.MaCTDV = ctdv.MaCTDV
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE sddv.MaPhieu = ?
            ORDER BY sddv.MaCTSDDV ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maPhieu]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật trạng thái Check-in thành công: DaDatPhong -> DangLuuTru
     */
    public function capNhatCheckin(string $maPhieu, string $maLeTan = 'LT001', ?string $maPhong = null): bool
    {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            // 1. Cập nhật từng phòng chi tiết kèm MaLeTanCheckin
            if (!empty($maPhong)) {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DangLuuTru', MaLeTanCheckin = :maLeTan
                    WHERE MaPhieu = :maPhieu AND MaPhong = :maPhong
                ");
                $stmt2->execute([':maLeTan' => $maLeTan, ':maPhieu' => $maPhieu, ':maPhong' => $maPhong]);

                // Cập nhật bảng Phong sang 'DangSuDung'
                $stmtP = $db->prepare("UPDATE Phong SET TrangThaiPhong = 'DangSuDung' WHERE MaPhong = ?");
                $stmtP->execute([$maPhong]);
            } else {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DangLuuTru', MaLeTanCheckin = :maLeTan
                    WHERE MaPhieu = :maPhieu AND TrangThai = 'DaDatPhong'
                ");
                $stmt2->execute([':maLeTan' => $maLeTan, ':maPhieu' => $maPhieu]);

                // Cập nhật tất cả các phòng thuộc phiếu sang 'DangSuDung'
                $stmtP = $db->prepare("
                    UPDATE Phong p
                    JOIN ChiTietPhieuNhanPhong ctp ON p.MaPhong = ctp.MaPhong
                    SET p.TrangThaiPhong = 'DangSuDung'
                    WHERE ctp.MaPhieu = ? AND ctp.TrangThai = 'DangLuuTru'
                ");
                $stmtP->execute([$maPhieu]);
            }

            // 2. Phiếu tổng chuyển sang DangLuuTru
            $stmt1 = $db->prepare("
                UPDATE PhieuNhanPhong 
                SET TrangThai = 'DangLuuTru'
                WHERE MaPhieu = ?
            ");
            $stmt1->execute([$maPhieu]);

            if ($isOwner) {
                $db->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($isOwner && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cập nhật Hủy phiếu / Hủy từng phòng lẻ
     */
    public function capNhatHuy(string $maPhieu, ?string $maLeTan = null, ?string $maPhong = null, ?string $lyDo = null): bool
    {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            // 1. Cập nhật phòng cụ thể hoặc toàn bộ phòng trong ChiTietPhieuNhanPhong kèm MaLeTanHuy
            if (!empty($maPhong) && $maPhong !== 'ALL') {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DaHuy', LyDoHuy = COALESCE(?, LyDoHuy), MaLeTanHuy = COALESCE(?, MaLeTanHuy)
                    WHERE MaPhieu = ? AND MaPhong = ?
                ");
                $stmt2->execute([$lyDo, $maLeTan, $maPhieu, $maPhong]);

                // Trả phòng về 'Trong'
                $stmtP = $db->prepare("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong = ?");
                $stmtP->execute([$maPhong]);
            } else {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DaHuy', LyDoHuy = COALESCE(?, LyDoHuy), MaLeTanHuy = COALESCE(?, MaLeTanHuy)
                    WHERE MaPhieu = ?
                ");
                $stmt2->execute([$lyDo, $maLeTan, $maPhieu]);

                // Trả tất cả các phòng của phiếu về 'Trong'
                $stmtP = $db->prepare("
                    UPDATE Phong p
                    JOIN ChiTietPhieuNhanPhong ctp ON p.MaPhong = ctp.MaPhong
                    SET p.TrangThaiPhong = 'Trong'
                    WHERE ctp.MaPhieu = ?
                ");
                $stmtP->execute([$maPhieu]);
            }

            // 2. Kiểm tra số phòng còn lại chưa kết thúc (chưa trả phòng và chưa hủy)
            $stmtRemaining = $db->prepare("
                SELECT COUNT(*) FROM ChiTietPhieuNhanPhong
                WHERE MaPhieu = ? AND TrangThai NOT IN ('DaTraPhong', 'DaHuy')
            ");
            $stmtRemaining->execute([$maPhieu]);
            $remaining = (int)$stmtRemaining->fetchColumn();

            // Nếu toàn bộ phòng trong phiếu đã kết thúc
            if ($remaining === 0) {
                $stmtCheckTra = $db->prepare("
                    SELECT COUNT(*) FROM ChiTietPhieuNhanPhong 
                    WHERE MaPhieu = ? AND TrangThai = 'DaTraPhong'
                ");
                $stmtCheckTra->execute([$maPhieu]);
                $countTra = (int)$stmtCheckTra->fetchColumn();

                $newTrangThaiPhieu = ($countTra > 0) ? 'DaTraPhong' : 'DaHuy';
                $stmt1 = $db->prepare("
                    UPDATE PhieuNhanPhong 
                    SET TrangThai = ?, LyDoHuy = CASE WHEN ? = 'DaHuy' THEN COALESCE(?, LyDoHuy) ELSE LyDoHuy END
                    WHERE MaPhieu = ?
                ");
                $stmt1->execute([$newTrangThaiPhieu, $newTrangThaiPhieu, $lyDo, $maPhieu]);
            }

            if ($isOwner) {
                $db->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($isOwner && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
