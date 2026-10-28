<?php

declare(strict_types=1);

namespace App\ThanhToan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý các bản ghi thanh toán, tạo mã,
 * cập nhật trạng thái giao dịch và lưu thông tin hoàn tiền.
 */
class ThanhToanGiaoDichRepository
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
     * Sinh mã thanh toán tự động theo định dạng TT001, TT002,...
     */
    public function generateMaThanhToan(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaThanhToan FROM ThanhToan WHERE MaThanhToan LIKE 'TT%' ORDER BY CAST(SUBSTRING(MaThanhToan, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = ($row && !empty($row['MaThanhToan'])) ? ((int) substr($row['MaThanhToan'], 2)) + 1 : 1;
        return sprintf('TT%03d', $next);
    }

    /**
     * Lưu bản ghi thanh toán vào bảng ThanhToan
     */
    public function luuThanhToan(
        string $maTT,
        float $soTien,
        string $thoiGian,
        string $hinhThuc,
        string $trangThai,
        string $maHD,
        PDO $db
    ): bool {
        $sql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $maTT,
            $soTien,
            $thoiGian,
            $hinhThuc,
            $trangThai,
            $maHD
        ]);
    }

    public function findThanhToanById(string $id, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM ThanhToan WHERE MaThanhToan = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findThanhToanByMaHD(string $maHD, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM ThanhToan WHERE MaHD = ? ORDER BY ThoiGianThanhToan DESC");
        $stmt->execute([$maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function capNhatTrangThai(string $maTT, string $trangThai, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("UPDATE ThanhToan SET TrangThaiThanhToan = ? WHERE MaThanhToan = ?");
        return $stmt->execute([$trangThai, $maTT]);
    }

    /**
     * Lấy danh sách toàn bộ các giao dịch thanh toán
     */
    public function getAll(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT tt.*, hd.TongTienSauVAT, pnp.MaPhieu, kh.HoTen as TenKhachHang
            FROM ThanhToan tt
            LEFT JOIN HoaDon hd ON tt.MaHD = hd.MaHD
            LEFT JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
            LEFT JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            ORDER BY tt.ThoiGianThanhToan DESC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lưu thông tin hoàn tiền vào bản ghi thanh toán
     */
    public function saveRefund(string $maTT, array $refundData, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();

        // Kiểm tra xem bảng ThanhToan có các cột hoàn tiền chưa
        try {
            $checkCols = $db->query("SHOW COLUMNS FROM ThanhToan LIKE 'SoTienHoan'")->fetch();
            if ($checkCols) {
                $stmt = $db->prepare("
                    UPDATE ThanhToan 
                    SET SoTienHoan = :soTienHoan,
                        NganHangHoan = :nganHangHoan,
                        STKHoan = :stkHoan,
                        TenChuTKHoan = :tenChuTKHoan,
                        LyDoHoan = :lyDoHoan,
                        ThoiGianHoan = :thoiGianHoan,
                        TrangThaiThanhToan = 'DaHoanTien'
                    WHERE MaThanhToan = :maTT
                ");
                return $stmt->execute([
                    ':soTienHoan' => $refundData['SoTienHoan'] ?? 0,
                    ':nganHangHoan' => $refundData['NganHangHoan'] ?? '',
                    ':stkHoan' => $refundData['STKHoan'] ?? '',
                    ':tenChuTKHoan' => $refundData['TenChuTKHoan'] ?? '',
                    ':lyDoHoan' => $refundData['LyDoHoan'] ?? '',
                    ':thoiGianHoan' => $refundData['ThoiGianHoan'] ?? date('Y-m-d H:i:s'),
                    ':maTT' => $maTT
                ]);
            }
        } catch (\Throwable $e) {}

        // Fallback an toàn: Cập nhật trạng thái thành DaHoanTien nếu chưa có các cột phụ
        return $this->capNhatTrangThai($maTT, 'DaHoanTien', $db);
    }

    // Các hàm tương thích
    public function findByMaHD(string $maHD, ?PDO $db = null): ?array
    {
        $rows = $this->findThanhToanByMaHD($maHD, $db);
        return !empty($rows) ? $rows[0] : null;
    }

    public function findById(string $id, ?PDO $db = null): ?array
    {
        return $this->findThanhToanById($id, $db);
    }

    public function updateStatus(string $maTT, string $trangThai, ?string $time = null, ?PDO $db = null): bool
    {
        return $this->capNhatTrangThai($maTT, $trangThai, $db);
    }

    public function generateNextMaTT(?PDO $db = null): string
    {
        return $this->generateMaThanhToan($db);
    }

    public function create(array $data, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        return $this->luuThanhToan(
            $data['MaThanhToan'],
            (float)$data['SoTien'],
            $data['ThoiGianThanhToan'] ?? date('Y-m-d H:i:s'),
            $data['HinhThuc'] ?? 'ChuyenKhoan',
            $data['TrangThaiThanhToan'] ?? 'ChoThanhToan',
            $data['MaHD'],
            $db
        );
    }
}
