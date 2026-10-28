<?php

declare(strict_types=1);

namespace App\DatPhong\Repositories;

use Database\Database;
use PDO;
use Throwable;

/**
 * Repository chuyên trách việc ghi nhận phiếu đặt phòng và điều phối Transaction an toàn.
 */
class DatPhongGiaoDichRepository
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
     * Thực thi một chuỗi tác vụ ghi dữ liệu trong 1 Transaction duy nhất
     */
    public function transaction(callable $callback)
    {
        $db = $this->getConnection();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function generateMaPhieu(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaPhieu FROM PhieuNhanPhong WHERE MaPhieu LIKE 'PP%' ORDER BY CAST(SUBSTRING(MaPhieu, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && !empty($row['MaPhieu'])) ? ((int) substr($row['MaPhieu'], 2)) + 1 : 1;
        return sprintf('PP%03d', $next);
    }

    public function generateMaNguoiLuuTru(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaNguoiLuuTru FROM NguoiLuuTru WHERE MaNguoiLuuTru LIKE 'NLT%' ORDER BY CAST(SUBSTRING(MaNguoiLuuTru, 4) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && !empty($row['MaNguoiLuuTru'])) ? ((int) substr($row['MaNguoiLuuTru'], 3)) + 1 : 1;
        return sprintf('NLT%03d', $next);
    }

    public function luuPhieuNhanPhong(
        string $maPhieu,
        string $ngayLap,
        string $kenhDat,
        string $trangThai,
        string $maKH,
        ?string $maLeTan,
        ?string $maKM,
        ?string $thoiGianHetHan,
        PDO $db
    ): bool {
        $sql = "INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, ThoiGianHetHan, MaKH, MaLeTan, MaKM)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maPhieu,
            $ngayLap,
            $kenhDat,
            $trangThai,
            $thoiGianHetHan,
            $maKH,
            $maLeTan,
            $maKM
        ]);
    }

    public function luuChiTietPhieuNhanPhong(
        string $maPhieu,
        string $maPhong,
        string $nhanPhong,
        string $traPhong,
        int $soNguoi,
        string $trangThai,
        PDO $db
    ): bool {
        $sql = "INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maPhieu,
            $maPhong,
            $nhanPhong,
            $traPhong,
            $soNguoi,
            $trangThai
        ]);
    }

    public function luuNguoiLuuTru(
        string $maNLT,
        string $hoTen,
        string $ngaySinh,
        string $quocTich,
        string $loaiGiayTo,
        string $soGiayTo,
        ?string $ghiChu,
        string $maPhieu,
        string $maPhong,
        PDO $db
    ): bool {
        $sql = "INSERT INTO NguoiLuuTru (MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maNLT,
            $hoTen,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $ghiChu,
            $maPhieu,
            $maPhong
        ]);
    }

    public function luuCTSuDungDichVu(
        string $maCTDV,
        string $maPhieu,
        string $maPhong,
        float $donGia,
        string $ghiChu,
        int $soLuong,
        PDO $db
    ): bool {
        $sql = "INSERT INTO CTSuDungDichVu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu,
            $soLuong
        ]);
    }

    /**
     * Cập nhật trạng thái của Phiếu nhận phòng (kèm lý do huỷ nếu có)
     */
    public function capNhatTrangThaiPhieu(string $maPhieu, string $trangThai, ?string $lyDoHuy = null, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        if ($lyDoHuy !== null) {
            $stmt = $db->prepare("UPDATE PhieuNhanPhong SET TrangThai = ?, LyDoHuy = ? WHERE MaPhieu = ?");
            return $stmt->execute([$trangThai, $lyDoHuy, $maPhieu]);
        }
        $stmt = $db->prepare("UPDATE PhieuNhanPhong SET TrangThai = ? WHERE MaPhieu = ?");
        return $stmt->execute([$trangThai, $maPhieu]);
    }

    /**
     * Cập nhật trạng thái của toàn bộ Chi tiết phiếu nhận phòng thuộc mã phiếu
     */
    public function capNhatTrangThaiChiTiet(string $maPhieu, string $trangThai, ?string $lyDoHuy = null, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        if ($lyDoHuy !== null) {
            $stmt = $db->prepare("UPDATE ChiTietPhieuNhanPhong SET TrangThai = ?, LyDoHuy = ? WHERE MaPhieu = ?");
            return $stmt->execute([$trangThai, $lyDoHuy, $maPhieu]);
        }
        $stmt = $db->prepare("UPDATE ChiTietPhieuNhanPhong SET TrangThai = ? WHERE MaPhieu = ?");
        return $stmt->execute([$trangThai, $maPhieu]);
    }
}
