<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;
use Shared\ChinhSachHoanHuy;

/**
 * Repository chuyên trách quản lý Phiếu nhận phòng và nghiệp vụ Check-in / Hủy phiếu.
 */
class LeTanPhieuRepository
{
    private ?Database $db;
    private array $columnCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Kiểm tra sự tồn tại của một cột trong bảng để tương thích cả trước và sau migration.
     */
    private function hasColumn(PDO $db, string $table, string $column): bool
    {
        $key = strtolower("{$table}.{$column}");
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND LOWER(TABLE_NAME) = LOWER(?)
                  AND LOWER(COLUMN_NAME) = LOWER(?)
            ");
            $stmt->execute([$table, $column]);
            $this->columnCache[$key] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $this->columnCache[$key] = false;
        }
        return $this->columnCache[$key];
    }

    /**
     * Chỉ trả trạng thái phòng trong bảng Phong về 'Trong' khi phòng không còn phiếu active nào khác.
     */
    private function releaseRoomsSafely(PDO $db, array $maPhongs): void
    {
        $uniqueRooms = array_values(array_unique(array_filter($maPhongs)));
        if (empty($uniqueRooms)) {
            return;
        }

        $stmtCheck = $db->prepare("
            SELECT COUNT(*)
            FROM ChiTietPhieuNhanPhong ctp
            JOIN PhieuNhanPhong pnp ON ctp.MaPhieu = pnp.MaPhieu
            WHERE ctp.MaPhong = ?
              AND (
                  ctp.TrangThai IN ('DangLuuTru', 'DaDatPhong')
                  OR (
                      ctp.TrangThai = 'ChoThanhToan'
                      AND (pnp.ThoiGianHetHan IS NULL OR pnp.ThoiGianHetHan > NOW())
                  )
              )
        ");
        $stmtReset = $db->prepare("
            UPDATE Phong
            SET TrangThaiPhong = 'Trong'
            WHERE MaPhong = ? AND TrangThaiPhong IN ('DaDat', 'ChoThanhToan')
        ");

        foreach ($uniqueRooms as $roomId) {
            $stmtCheck->execute([$roomId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtReset->execute([$roomId]);
            }
        }
    }

    /**
     * Tự động quét và chuyển sang trạng thái DaHuy khi quá thời gian trả phòng dự kiến (NOW() > ThoiGianTraPhong)
     * mà khách vẫn chưa đến nhận phòng (Do khách đã thanh toán 100% nên phòng được giữ trọn kỳ đặt).
     */
    public function tuDongHuyPhieuQuaHanTraPhong(): int
    {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            $lyDoAuto = "Khách không đến nhận phòng suốt thời gian đặt - Quá giờ trả phòng dự kiến (Hoàn 0% - Phí hủy 100%)";

            $stmtFind = $db->query("
                SELECT ctp.MaPhieu, ctp.MaPhong
                FROM ChiTietPhieuNhanPhong ctp
                WHERE ctp.TrangThai = 'DaDatPhong'
                  AND NOW() > ctp.ThoiGianTraPhong
            ");
            $expiredRows = $stmtFind ? $stmtFind->fetchAll(PDO::FETCH_ASSOC) : [];

            if (empty($expiredRows)) {
                if ($isOwner) {
                    $db->commit();
                }
                return 0;
            }

            // 1. Cập nhật các phòng quá hạn trong ChiTietPhieuNhanPhong
            $setExtraCtp = $this->hasColumn($db, 'ChiTietPhieuNhanPhong', 'ThoiGianHuy')
                ? ", ThoiGianHuy = NOW(), TyLeHoan = 0"
                : "";
            $sqlPhong = "
                UPDATE ChiTietPhieuNhanPhong
                SET TrangThai = 'DaHuy', LyDoHuy = ? {$setExtraCtp}
                WHERE TrangThai = 'DaDatPhong'
                  AND NOW() > ThoiGianTraPhong
            ";
            $stmt = $db->prepare($sqlPhong);
            $stmt->execute([$lyDoAuto]);
            $affectedRooms = $stmt->rowCount();

            // 2. Giải phóng trạng thái bảng Phong an toàn (không ghi đè nếu phòng có đơn active khác)
            $this->releaseRoomsSafely($db, array_column($expiredRows, 'MaPhong'));

            // 3. Chuyển phiếu cha sang DaHuy nếu toàn bộ phòng trong phiếu đã DaHuy
            $setExtraPnp = $this->hasColumn($db, 'PhieuNhanPhong', 'ThoiGianHuy')
                ? ", pnp.ThoiGianHuy = NOW(), pnp.TyLeHoan = 0, pnp.SoTienHoan = 0"
                : "";
            $sqlPhieu = "
                UPDATE PhieuNhanPhong pnp
                SET pnp.TrangThai = 'DaHuy',
                    pnp.LyDoHuy = ? {$setExtraPnp}
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
     * Alias giữ tương thích ngược cho các caller cũ.
     */
    public function tuDongHuyPhieuQuaHan24h(): int
    {
        return $this->tuDongHuyPhieuQuaHanTraPhong();
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

        // 2. Tự động quét và hủy các phiếu No-show đã quá thời gian trả phòng dự kiến
        $this->tuDongHuyPhieuQuaHanTraPhong();

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
     * Cập nhật Hủy phiếu / Hủy từng phòng lẻ kèm ghi nhận hoàn tiền & giải phóng phòng an toàn.
     */
    public function capNhatHuy(
        string $maPhieu,
        ?string $maLeTan = null,
        ?string $maPhong = null,
        ?string $lyDo = null,
        array $refundMeta = []
    ): bool {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            $hasCtpAudit = $this->hasColumn($db, 'ChiTietPhieuNhanPhong', 'ThoiGianHuy')
                && $this->hasColumn($db, 'ChiTietPhieuNhanPhong', 'PhiHuy')
                && $this->hasColumn($db, 'ChiTietPhieuNhanPhong', 'SoTienHoan')
                && $this->hasColumn($db, 'ChiTietPhieuNhanPhong', 'TyLeHoan');
            $hasPnpAudit = $this->hasColumn($db, 'PhieuNhanPhong', 'ThoiGianHuy')
                && $this->hasColumn($db, 'PhieuNhanPhong', 'TyLeHoan')
                && $this->hasColumn($db, 'PhieuNhanPhong', 'SoTienHoan');

            $tiLeHoanChung = (float)($refundMeta['tiLeHoan'] ?? 0);
            $soTienHoanTong = (float)($refundMeta['soTienHoan'] ?? 0);
            $roomBreakdown = [];
            foreach (($refundMeta['chiTietPhong'] ?? []) as $item) {
                if (!empty($item['maPhong'])) {
                    $roomBreakdown[$item['maPhong']] = $item;
                }
            }

            $affectedRooms = [];
            $isSingleRoom = !empty($maPhong) && $maPhong !== 'ALL';

            // 1. Lấy danh sách phòng hợp lệ được phép hủy (chỉ các phòng chưa check-in / chưa trả / chưa hủy)
            if ($isSingleRoom) {
                $affectedRooms = [$maPhong];
            } else {
                $stmtTarget = $db->prepare("
                    SELECT MaPhong FROM ChiTietPhieuNhanPhong
                    WHERE MaPhieu = ? AND TrangThai IN ('DaDatPhong', 'ChoThanhToan')
                ");
                $stmtTarget->execute([$maPhieu]);
                $affectedRooms = $stmtTarget->fetchAll(PDO::FETCH_COLUMN);
            }

            // 2. Cập nhật từng phòng chi tiết bị hủy (không ghi đè phòng đã DaTraPhong hoặc DangLuuTru)
            foreach ($affectedRooms as $roomId) {
                $rMeta = $roomBreakdown[$roomId] ?? [];
                $rTyLe = (float)($rMeta['tiLeHoan'] ?? $tiLeHoanChung);
                $rPhiHuy = (float)($rMeta['phiHuy'] ?? 0);
                $rSoTienHoan = (float)($rMeta['soTienHoan'] ?? ($isSingleRoom ? $soTienHoanTong : 0));

                if ($hasCtpAudit) {
                    $stmtUp = $db->prepare("
                        UPDATE ChiTietPhieuNhanPhong
                        SET TrangThai = 'DaHuy',
                            LyDoHuy = COALESCE(?, LyDoHuy),
                            MaLeTanHuy = COALESCE(?, MaLeTanHuy),
                            ThoiGianHuy = NOW(),
                            TyLeHoan = ?,
                            PhiHuy = ?,
                            SoTienHoan = ?
                        WHERE MaPhieu = ? AND MaPhong = ? AND TrangThai IN ('DaDatPhong', 'ChoThanhToan')
                    ");
                    $stmtUp->execute([$lyDo, $maLeTan, $rTyLe, $rPhiHuy, $rSoTienHoan, $maPhieu, $roomId]);
                } else {
                    $stmtUp = $db->prepare("
                        UPDATE ChiTietPhieuNhanPhong
                        SET TrangThai = 'DaHuy',
                            LyDoHuy = COALESCE(?, LyDoHuy),
                            MaLeTanHuy = COALESCE(?, MaLeTanHuy)
                        WHERE MaPhieu = ? AND MaPhong = ? AND TrangThai IN ('DaDatPhong', 'ChoThanhToan')
                    ");
                    $stmtUp->execute([$lyDo, $maLeTan, $maPhieu, $roomId]);
                }
            }

            // 3. Giải phóng trạng thái bảng Phong an toàn (chỉ về 'Trong' nếu không còn đơn active khác)
            $this->releaseRoomsSafely($db, $affectedRooms);

            // 4. Kiểm tra số phòng còn lại chưa kết thúc (chưa trả phòng và chưa hủy)
            $stmtRemaining = $db->prepare("
                SELECT COUNT(*) FROM ChiTietPhieuNhanPhong
                WHERE MaPhieu = ? AND TrangThai NOT IN ('DaTraPhong', 'DaHuy')
            ");
            $stmtRemaining->execute([$maPhieu]);
            $remaining = (int)$stmtRemaining->fetchColumn();

            if ($remaining === 0) {
                $stmtCheckTra = $db->prepare("
                    SELECT COUNT(*) FROM ChiTietPhieuNhanPhong 
                    WHERE MaPhieu = ? AND TrangThai = 'DaTraPhong'
                ");
                $stmtCheckTra->execute([$maPhieu]);
                $countTra = (int)$stmtCheckTra->fetchColumn();

                $newTrangThaiPhieu = ($countTra > 0) ? 'DaTraPhong' : 'DaHuy';
                if ($newTrangThaiPhieu === 'DaHuy') {
                    // Hoàn lại lượt sử dụng KhuyenMai nếu phiếu này chưa từng bị hủy trước đó
                    $stmtRestoreKm = $db->prepare("
                        UPDATE KhuyenMai km
                        JOIN PhieuNhanPhong pnp ON km.MaKM = pnp.MaKM
                        SET km.SoLuongDaDung = GREATEST(0, km.SoLuongDaDung - 1)
                        WHERE pnp.MaPhieu = ? AND pnp.TrangThai != 'DaHuy' AND pnp.MaKM IS NOT NULL
                    ");
                    $stmtRestoreKm->execute([$maPhieu]);
                }

                if ($hasPnpAudit && $hasCtpAudit) {
                    $stmtSum = $db->prepare("SELECT COALESCE(SUM(SoTienHoan), 0) FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND TrangThai = 'DaHuy'");
                    $stmtSum->execute([$maPhieu]);
                    $tongHoanPhieu = (float)$stmtSum->fetchColumn();

                    $stmt1 = $db->prepare("
                        UPDATE PhieuNhanPhong 
                        SET TrangThai = ?,
                            LyDoHuy = CASE WHEN ? = 'DaHuy' THEN COALESCE(?, LyDoHuy) ELSE LyDoHuy END,
                            ThoiGianHuy = CASE WHEN ? = 'DaHuy' THEN NOW() ELSE ThoiGianHuy END,
                            TyLeHoan = CASE WHEN ? = 'DaHuy' THEN ? ELSE TyLeHoan END,
                            SoTienHoan = ?
                        WHERE MaPhieu = ?
                    ");
                    $stmt1->execute([
                        $newTrangThaiPhieu,
                        $newTrangThaiPhieu,
                        $lyDo,
                        $newTrangThaiPhieu,
                        $newTrangThaiPhieu,
                        $tiLeHoanChung,
                        $tongHoanPhieu,
                        $maPhieu
                    ]);
                } else {
                    $stmt1 = $db->prepare("
                        UPDATE PhieuNhanPhong 
                        SET TrangThai = ?, LyDoHuy = CASE WHEN ? = 'DaHuy' THEN COALESCE(?, LyDoHuy) ELSE LyDoHuy END
                        WHERE MaPhieu = ?
                    ");
                    $stmt1->execute([$newTrangThaiPhieu, $newTrangThaiPhieu, $lyDo, $maPhieu]);
                }
            } elseif ($hasPnpAudit && $hasCtpAudit) {
                $stmtSum = $db->prepare("SELECT COALESCE(SUM(SoTienHoan), 0) FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ? AND TrangThai = 'DaHuy'");
                $stmtSum->execute([$maPhieu]);
                $tongHoanPhieu = (float)$stmtSum->fetchColumn();
                $stmt1 = $db->prepare("UPDATE PhieuNhanPhong SET SoTienHoan = ? WHERE MaPhieu = ?");
                $stmt1->execute([$tongHoanPhieu, $maPhieu]);
            }

            // 5. Đồng bộ trạng thái & thông tin hoàn tiền trong bảng ThanhToan
            $this->dongBoThanhToanKhiHuy($db, $maPhieu, $remaining, $soTienHoanTong, $lyDo, $refundMeta);

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
     * Đồng bộ bản ghi ThanhToan của hóa đơn liên quan khi hủy toàn bộ hoặc hủy một phần phiếu.
     */
    private function dongBoThanhToanKhiHuy(
        PDO $db,
        string $maPhieu,
        int $remainingRooms,
        float $soTienHoanDotNay,
        ?string $lyDo,
        array $refundMeta
    ): void {
        $stmtHD = $db->prepare("SELECT MaHD FROM HoaDon WHERE MaPhieu = ? LIMIT 1");
        $stmtHD->execute([$maPhieu]);
        $maHD = $stmtHD->fetchColumn();
        if (!$maHD) {
            return;
        }

        // Hủy các giao dịch đang chờ thanh toán nếu phiếu không còn phòng nào active
        if ($remainingRooms === 0) {
            $stmtCancelPending = $db->prepare("
                UPDATE ThanhToan
                SET TrangThaiThanhToan = ?
                WHERE MaHD = ? AND TrangThaiThanhToan = ?
            ");
            $stmtCancelPending->execute([
                ChinhSachHoanHuy::TT_DA_HUY,
                $maHD,
                ChinhSachHoanHuy::TT_CHO_THANH_TOAN
            ]);
        }

        if ($soTienHoanDotNay <= 0) {
            return;
        }

        $hasRefundCols = $this->hasColumn($db, 'ThanhToan', 'SoTienHoan');
        $stmtPaid = $db->prepare("
            SELECT MaThanhToan, SoTien" . ($hasRefundCols ? ", COALESCE(SoTienHoan, 0) AS SoTienHoan" : "") . "
            FROM ThanhToan
            WHERE MaHD = ? AND TrangThaiThanhToan IN (?, ?)
            ORDER BY ThoiGianThanhToan ASC
            LIMIT 1
        ");
        $stmtPaid->execute([
            $maHD,
            ChinhSachHoanHuy::TT_HOAN_TAT,
            ChinhSachHoanHuy::TT_HOAN_MOT_PHAN
        ]);
        $paidRow = $stmtPaid->fetch(PDO::FETCH_ASSOC);
        if (!$paidRow) {
            return;
        }

        $soTienGoc = (float)$paidRow['SoTien'];
        $daHoanCu = (float)($paidRow['SoTienHoan'] ?? 0);
        $tongHoanMoi = min($soTienGoc, round($daHoanCu + $soTienHoanDotNay, 0));

        $trangThaiMoi = ($remainingRooms === 0 || $tongHoanMoi >= $soTienGoc)
            ? ChinhSachHoanHuy::TT_DA_HOAN_TIEN
            : ChinhSachHoanHuy::TT_HOAN_MOT_PHAN;

        if ($hasRefundCols) {
            $stmtUpTT = $db->prepare("
                UPDATE ThanhToan
                SET SoTienHoan = ?,
                    NganHangHoan = COALESCE(NULLIF(?, ''), NganHangHoan),
                    STKHoan = COALESCE(NULLIF(?, ''), STKHoan),
                    TenChuTKHoan = COALESCE(NULLIF(?, ''), TenChuTKHoan),
                    LyDoHoan = COALESCE(NULLIF(?, ''), LyDoHoan),
                    ThoiGianHoan = NOW(),
                    TrangThaiThanhToan = ?
                WHERE MaThanhToan = ?
            ");
            $stmtUpTT->execute([
                $tongHoanMoi,
                trim((string)($refundMeta['nganHangHoan'] ?? '')),
                trim((string)($refundMeta['stkHoan'] ?? '')),
                trim((string)($refundMeta['tenChuTKHoan'] ?? '')),
                $lyDo ?? '',
                $trangThaiMoi,
                $paidRow['MaThanhToan']
            ]);
        } else {
            $stmtUpTT = $db->prepare("UPDATE ThanhToan SET TrangThaiThanhToan = ? WHERE MaThanhToan = ?");
            $stmtUpTT->execute([$trangThaiMoi, $paidRow['MaThanhToan']]);
        }
    }
}
