<?php

declare(strict_types=1);

namespace App\ThanhToan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách đối soát cập nhật đồng bộ trạng thái phiếu, chi tiết phiếu và phòng
 * khi thanh toán hoàn tất hoặc khi quá hạn thanh toán 20 phút.
 */
class ThanhToanDoiSoatRepository
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
     * Cập nhật hoàn tất đặt phòng khi thanh toán thành công:
     * - PhieuNhanPhong -> DaDatPhong
     * - ChiTietPhieuNhanPhong -> DaDatPhong
     * - Phong -> DaDat
     */
    public function capNhatHoanTatDatPhong(string $maHD, ?PDO $db = null): void
    {
        $db = $db ?? $this->getConnection();

        // 1. PhieuNhanPhong -> DaDatPhong
        $stmt1 = $db->prepare("
            UPDATE PhieuNhanPhong 
            SET TrangThai = 'DaDatPhong' 
            WHERE MaPhieu = (SELECT MaPhieu FROM HoaDon WHERE MaHD = ?) 
              AND TrangThai = 'ChoThanhToan'
        ");
        $stmt1->execute([$maHD]);

        // 2. ChiTietPhieuNhanPhong -> DaDatPhong
        $stmt2 = $db->prepare("
            UPDATE ChiTietPhieuNhanPhong 
            SET TrangThai = 'DaDatPhong' 
            WHERE MaPhieu = (SELECT MaPhieu FROM HoaDon WHERE MaHD = ?) 
              AND TrangThai = 'ChoThanhToan'
        ");
        $stmt2->execute([$maHD]);

        // 3. Phong -> DaDat
        $stmt3 = $db->prepare("
            UPDATE Phong 
            SET TrangThaiPhong = 'DaDat' 
            WHERE MaPhong IN (
                SELECT ctp.MaPhong 
                FROM ChiTietPhieuNhanPhong ctp 
                JOIN HoaDon hd ON ctp.MaPhieu = hd.MaPhieu 
                WHERE hd.MaHD = ?
            ) AND TrangThaiPhong = 'ChoThanhToan'
        ");
        $stmt3->execute([$maHD]);
    }

    /**
     * Xử lý hết hạn hoặc hủy thanh toán cho một hóa đơn:
     * - ThanhToan -> DaHetHan
     * - PhieuNhanPhong -> ThanhToanThatBai
     * - ChiTietPhieuNhanPhong -> ThanhToanThatBai
     * - Phong -> Trong (nhả lại trạng thái trống)
     */
    public function xuLyHetHanThanhToan(string $maHD, string $lyDo = 'Quá hạn thanh toán 20 phút', ?PDO $db = null): void
    {
        $db = $db ?? $this->getConnection();

        // 1. ThanhToan -> DaHetHan
        $stmt0 = $db->prepare("UPDATE ThanhToan SET TrangThaiThanhToan = 'DaHetHan' WHERE MaHD = ? AND TrangThaiThanhToan = 'ChoThanhToan'");
        $stmt0->execute([$maHD]);

        // 2. PhieuNhanPhong -> ThanhToanThatBai
        $stmt1 = $db->prepare("
            UPDATE PhieuNhanPhong 
            SET TrangThai = 'ThanhToanThatBai', LyDoHuy = ? 
            WHERE MaPhieu = (SELECT MaPhieu FROM HoaDon WHERE MaHD = ?) 
              AND TrangThai = 'ChoThanhToan'
        ");
        $stmt1->execute([$lyDo, $maHD]);

        // 3. ChiTietPhieuNhanPhong -> ThanhToanThatBai
        $stmt2 = $db->prepare("
            UPDATE ChiTietPhieuNhanPhong 
            SET TrangThai = 'ThanhToanThatBai', LyDoHuy = ? 
            WHERE MaPhieu = (SELECT MaPhieu FROM HoaDon WHERE MaHD = ?) 
              AND TrangThai = 'ChoThanhToan'
        ");
        $stmt2->execute([$lyDo, $maHD]);

        // 4. Nhả phòng về trạng thái 'Trong'
        $stmt3 = $db->prepare("
            UPDATE Phong 
            SET TrangThaiPhong = 'Trong' 
            WHERE MaPhong IN (
                SELECT ctp.MaPhong 
                FROM ChiTietPhieuNhanPhong ctp 
                JOIN HoaDon hd ON ctp.MaPhieu = hd.MaPhieu 
                WHERE hd.MaHD = ?
            ) AND TrangThaiPhong = 'ChoThanhToan'
        ");
        $stmt3->execute([$maHD]);
    }

    /**
     * Lấy danh sách mã hóa đơn của các phiếu đang Chờ thanh toán đã quá 20 phút
     */
    public function quetDanhSachPhieuQuaHan(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT DISTINCT hd.MaHD
            FROM HoaDon hd
            JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
            JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
            WHERE pnp.TrangThai = 'ChoThanhToan'
              AND tt.TrangThaiThanhToan = 'ChoThanhToan'
              AND (
                  (pnp.ThoiGianHetHan IS NOT NULL AND NOW() > pnp.ThoiGianHetHan)
                  OR (TIMESTAMPDIFF(SECOND, tt.ThoiGianThanhToan, NOW()) >= 1200)
              )
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
