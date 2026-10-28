<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý nghiệp vụ trả phòng (Check-out) và dịch vụ sử dụng.
 */
class LeTanCheckoutRepository
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
     * Cập nhật Check-out hoàn tất: DangLuuTru -> DaTraPhong
     * Đồng thời trả trạng thái phòng vật lý trong bảng Phong về 'BaoTri' (để dọn dẹp)
     */
    public function capNhatCheckout(string $maPhieu, ?string $maLeTan = 'LT001', ?string $maPhong = null): bool
    {
        $db = $this->getConnection();
        $isOwner = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $isOwner = true;
        }

        try {
            // 1. Cập nhật phòng chi tiết kèm MaLeTanCheckout
            if (!empty($maPhong)) {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DaTraPhong',
                        ThoiGianTraPhong = CASE 
                            WHEN NOW() > ThoiGianNhanPhong THEN NOW() 
                            ELSE DATE_ADD(ThoiGianNhanPhong, INTERVAL 1 MINUTE) 
                        END,
                        MaLeTanCheckout = :maLeTan
                    WHERE MaPhieu = :maPhieu AND MaPhong = :maPhong
                ");
                $stmt2->execute([':maLeTan' => $maLeTan, ':maPhieu' => $maPhieu, ':maPhong' => $maPhong]);

                // Chuyển phòng vật lý sang trạng thái 'BaoTri'
                $stmtP = $db->prepare("UPDATE Phong SET TrangThaiPhong = 'BaoTri' WHERE MaPhong = ?");
                $stmtP->execute([$maPhong]);
            } else {
                $stmt2 = $db->prepare("
                    UPDATE ChiTietPhieuNhanPhong 
                    SET TrangThai = 'DaTraPhong',
                        ThoiGianTraPhong = CASE 
                            WHEN NOW() > ThoiGianNhanPhong THEN NOW() 
                            ELSE DATE_ADD(ThoiGianNhanPhong, INTERVAL 1 MINUTE) 
                        END,
                        MaLeTanCheckout = :maLeTan
                    WHERE MaPhieu = :maPhieu AND TrangThai NOT IN ('DaTraPhong', 'DaHuy')
                ");
                $stmt2->execute([':maLeTan' => $maLeTan, ':maPhieu' => $maPhieu]);

                // Chuyển các phòng vừa trả sang trạng thái 'BaoTri'
                $stmtP = $db->prepare("
                    UPDATE Phong p
                    JOIN ChiTietPhieuNhanPhong ctp ON p.MaPhong = ctp.MaPhong
                    SET p.TrangThaiPhong = 'BaoTri'
                    WHERE ctp.MaPhieu = ? AND ctp.TrangThai = 'DaTraPhong'
                ");
                $stmtP->execute([$maPhieu]);
            }

            // 2. Đếm số phòng còn đang lưu trú hoặc chưa trả trong phiếu
            $stmtCount = $db->prepare("
                SELECT COUNT(*) 
                FROM ChiTietPhieuNhanPhong 
                WHERE MaPhieu = ? AND TrangThai NOT IN ('DaTraPhong', 'DaHuy')
            ");
            $stmtCount->execute([$maPhieu]);
            $remaining = (int)$stmtCount->fetchColumn();

            // Nếu toàn bộ phòng trong phiếu đã hoàn tất trả phòng/hủy -> Đổi phiếu tổng sang DaTraPhong
            if ($remaining === 0) {
                $stmt1 = $db->prepare("
                    UPDATE PhieuNhanPhong 
                    SET TrangThai = 'DaTraPhong'
                    WHERE MaPhieu = ?
                ");
                $stmt1->execute([$maPhieu]);
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
