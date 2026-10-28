<?php

declare(strict_types=1);

namespace App\DichVu\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho dịch vụ Giặt ủi (DVGiatUi).
 * Chịu trách nhiệm tương tác trực tiếp với cơ sở dữ liệu cho các hoạt động giặt ủi.
 */
class GiatUiRepository
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
     * Lấy danh sách 8 loại đồ giặt ủi cố định thuộc dịch vụ DVGiatUi
     */
    public function layDanhSachGiatUi(?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaDV = 'DVGiatUi' AND dv.TrangThai = 'DangCungCap'
            ORDER BY ctdv.MaCTDV ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm danh sách chi tiết đồ giặt theo mảng mã MaCTDV
     */
    public function timChiTietDichVuTheoDanhSach(array $maCTDVList, ?PDO $db = null): array
    {
        if (empty($maCTDVList)) {
            return [];
        }
        $db = $db ?? $this->getConnection();
        $placeholders = implode(',', array_fill(0, count($maCTDVList), '?'));
        $sql = "
            SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.MoTa, ctdv.MoTaChiTiet,
                   ctdv.DonGia, ctdv.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ctdv
            JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
            WHERE ctdv.MaCTDV IN ($placeholders) AND dv.TrangThai = 'DangCungCap'
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($maCTDVList);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['MaCTDV']] = $row;
        }
        return $result;
    }

    /**
     * Lấy thông tin phiếu và các phòng đang lưu trú hoặc đã đặt trước của khách hàng
     */
    public function timThongTinLuuTruHienTai(string $maKH, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT pnp.MaPhieu, pnp.MaKH, ctpnp.MaPhong, lp.TenLoaiPhong,
                   ctpnp.ThoiGianNhanPhong, ctpnp.ThoiGianTraPhong,
                   ctpnp.TrangThai, pnp.TrangThai AS TrangThaiPhieu
            FROM PhieuNhanPhong pnp
            JOIN ChiTietPhieuNhanPhong ctpnp ON pnp.MaPhieu = ctpnp.MaPhieu
            JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE pnp.MaKH = ? 
              AND pnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat') 
              AND ctpnp.TrangThai IN ('DangLuuTru', 'DaDatPhong', 'DaDat')
            ORDER BY 
              CASE WHEN pnp.TrangThai = 'DangLuuTru' THEN 1 ELSE 2 END,
              ctpnp.ThoiGianNhanPhong ASC,
              ctpnp.MaPhong ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maKH]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kiểm tra phòng được chọn có thực sự thuộc phiếu và đang lưu trú thực tế không (DangLuuTru)
     */
    public function kiemTraPhongCoDangLuuTru(string $maPhieu, string $maPhong, string $maKH, ?PDO $db = null): bool
    {
        $db = $db ?? $this->getConnection();
        $sql = "
            SELECT 1
            FROM PhieuNhanPhong pnp
            JOIN ChiTietPhieuNhanPhong ctpnp ON pnp.MaPhieu = ctpnp.MaPhieu
            WHERE pnp.MaPhieu = ? AND ctpnp.MaPhong = ? AND pnp.MaKH = ?
              AND pnp.TrangThai = 'DangLuuTru' 
              AND ctpnp.TrangThai = 'DangLuuTru'
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$maPhieu, $maPhong, $maKH]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lưu nhiều loại đồ giặt vào CTSuDungDichVu trong cùng một Database Transaction.
     * Mỗi loại đồ tạo thành một bản ghi riêng biệt.
     */
    public function luuDatDichVuGiatUi(
        array $danhSachMon,
        string $maPhieu,
        string $maPhong,
        ?string $ghiChu = null,
        ?PDO $db = null
    ): array {
        $conn = $db ?? $this->getConnection();
        $isExternalTx = $conn->inTransaction();

        if (!$isExternalTx) {
            $conn->beginTransaction();
        }

        try {
            $savedRecords = [];
            $sql = "
                INSERT INTO CTSuDungDichVu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);

            foreach ($danhSachMon as $item) {
                $maCTDV = $item['maCTDV'];
                $donGia = (float)$item['donGia'];
                $soLuong = (int)$item['soLuong'];
                $tenCTDV = $item['tenCTDV'] ?? '';
                $itemNote = !empty($item['ghiChu']) ? $item['ghiChu'] : $ghiChu;

                $stmt->execute([
                    $maCTDV,
                    $maPhieu,
                    $maPhong,
                    $donGia,
                    $itemNote,
                    $soLuong
                ]);

                $maCTSDDV = (int)$conn->lastInsertId();
                $savedRecords[] = [
                    'maCTSDDV' => $maCTSDDV,
                    'maCTDV' => $maCTDV,
                    'tenCTDV' => $tenCTDV,
                    'maPhieu' => $maPhieu,
                    'maPhong' => $maPhong,
                    'donGia' => $donGia,
                    'soLuong' => $soLuong,
                    'thanhTien' => $donGia * $soLuong,
                    'ghiChu' => $itemNote
                ];
            }

            if (!$isExternalTx) {
                $conn->commit();
            }

            return $savedRecords;
        } catch (\Throwable $e) {
            if (!$isExternalTx && $conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
