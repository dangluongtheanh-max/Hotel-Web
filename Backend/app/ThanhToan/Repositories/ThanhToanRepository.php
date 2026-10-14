<?php

namespace App\ThanhToan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho module ThanhToan.
 * Thao tác trực tiếp với bảng ThanhToan và liên kết với HoaDon trong CSDL.
 */
class ThanhToanRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Tìm giao dịch thanh toán theo mã thanh toán.
     */
    public function findById(string $maTT): ?array
    {
        $sql = "SELECT tt.*, hd.TongTienSauVAT, hd.MaPhieu 
                FROM ThanhToan tt
                JOIN HoaDon hd ON tt.MaHD = hd.MaHD
                WHERE tt.MaThanhToan = :maTT
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maTT' => $maTT]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tìm giao dịch thanh toán mới nhất theo mã hóa đơn.
     */
    public function findByMaHD(string $maHD): ?array
    {
        $sql = "SELECT tt.*, hd.TongTienSauVAT, hd.MaPhieu 
                FROM ThanhToan tt
                JOIN HoaDon hd ON tt.MaHD = hd.MaHD
                WHERE tt.MaHD = :maHD
                ORDER BY tt.ThoiGianThanhToan DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maHD' => $maHD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lấy danh sách toàn bộ các giao dịch thanh toán.
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT tt.*, hd.MaPhieu, hd.TongTienSauVAT,
                       kh.TenKhachHang
                FROM ThanhToan tt
                JOIN HoaDon hd ON tt.MaHD = hd.MaHD
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                ORDER BY tt.ThoiGianThanhToan DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tạo mới một giao dịch thanh toán.
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD) 
                VALUES (:maTT, :soTien, :thoiGian, :hinhThuc, :trangThai, :maHD)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':maTT' => $data['MaThanhToan'],
            ':soTien' => $data['SoTien'],
            ':thoiGian' => $data['ThoiGianThanhToan'] ?? date('Y-m-d H:i:s'),
            ':hinhThuc' => $data['HinhThuc'],
            ':trangThai' => $data['TrangThaiThanhToan'] ?? 'ChoThanhToan',
            ':maHD' => $data['MaHD'],
        ]);
    }

    /**
     * Cập nhật trạng thái thanh toán (ví dụ: 'HoanTat').
     */
    public function updateStatus(string $maTT, string $status, ?string $time = null): bool
    {
        $time = $time ?? date('Y-m-d H:i:s');
        $sql = "UPDATE ThanhToan 
                SET TrangThaiThanhToan = :status, 
                    ThoiGianThanhToan = :time 
                WHERE MaThanhToan = :maTT";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':time' => $time,
            ':maTT' => $maTT
        ]);
    }

    /**
     * Cập nhật thông tin hoàn tiền vào giao dịch thanh toán.
     */
    public function saveRefund(string $maTT, array $refundData): bool
    {
        $sql = "UPDATE ThanhToan 
                SET TrangThaiThanhToan = 'DaHoanTien',
                    SoTienHoan = :soTienHoan,
                    NganHangHoan = :nganHangHoan,
                    STKHoan = :stkHoan,
                    TenChuTKHoan = :tenChuTKHoan,
                    LyDoHoan = :lyDoHoan,
                    ThoiGianHoan = :thoiGianHoan
                WHERE MaThanhToan = :maTT";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':soTienHoan' => $refundData['SoTienHoan'],
            ':nganHangHoan' => $refundData['NganHangHoan'],
            ':stkHoan' => $refundData['STKHoan'],
            ':tenChuTKHoan' => $refundData['TenChuTKHoan'],
            ':lyDoHoan' => $refundData['LyDoHoan'] ?? 'Khách hủy phòng',
            ':thoiGianHoan' => $refundData['ThoiGianHoan'] ?? date('Y-m-d H:i:s'),
            ':maTT' => $maTT
        ]);
    }

    /**
     * Tự động sinh mã thanh toán tiếp theo (TT001, TT002, TT004...).
     */
    public function generateNextMaTT(): string
    {
        $sql = "SELECT MaThanhToan FROM ThanhToan ORDER BY MaThanhToan DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();

        if (!$last) {
            return 'TT001';
        }

        if (preg_match('/^TT(\d+)$/', $last, $matches)) {
            $num = (int)$matches[1] + 1;
            return 'TT' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }

        return 'TT_' . date('YmdHis');
    }
}
