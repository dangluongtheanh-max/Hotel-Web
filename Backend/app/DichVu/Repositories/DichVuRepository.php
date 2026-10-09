<?php

namespace App\DichVu\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho module DichVu.
 * Lop duy nhat tuong tac truc tiep voi Database qua PDO.
 */
class DichVuRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Lay tat ca nhom dich vu kem theo danh sach mat hang con (danh sach cay).
     */
    public function getAllDichVuWithDetails(): array
    {
        $sqlDichVu = "SELECT MaDV, TenDV, MoTa, DonGia, TrangThai FROM DichVu ORDER BY MaDV ASC";
        $stmtDV = $this->db->query($sqlDichVu);
        $dichVuList = $stmtDV->fetchAll(PDO::FETCH_ASSOC);

        $sqlCT = "SELECT MaCTDV, MaDV, TenCTDV, DonGia, SoLuongTon FROM ChiTietDichVu ORDER BY MaCTDV ASC";
        $stmtCT = $this->db->query($sqlCT);
        $chiTietList = $stmtCT->fetchAll(PDO::FETCH_ASSOC);

        // Gom nhom chi tiet theo MaDV
        $grouped = [];
        foreach ($chiTietList as $ct) {
            $grouped[$ct['MaDV']][] = [
                'MaCTDV' => $ct['MaCTDV'],
                'TenCTDV' => $ct['TenCTDV'],
                'DonGia' => (float)$ct['DonGia'],
                'SoLuongTon' => (int)$ct['SoLuongTon'],
            ];
        }

        // Gan items vao tung DichVu
        foreach ($dichVuList as &$dv) {
            $dv['DonGia'] = (float)$dv['DonGia'];
            $dv['items'] = $grouped[$dv['MaDV']] ?? [];
        }

        return $dichVuList;
    }

    /**
     * Tim thong tin mot mat hang chi tiet theo MaCTDV.
     */
    public function findChiTietById(string $maCTDV): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, d.TenDV, d.TrangThai AS TrangThaiNhom 
             FROM ChiTietDichVu c 
             JOIN DichVu d ON c.MaDV = d.MaDV 
             WHERE c.MaCTDV = :maCTDV"
        );
        $stmt->execute([':maCTDV' => $maCTDV]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tim nhom dich vu theo MaDV.
     */
    public function findDichVuById(string $maDV): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM DichVu WHERE MaDV = :maDV");
        $stmt->execute([':maDV' => $maDV]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tru ton kho khi khach su dung hang hoa (chong am kho nho WHERE SoLuongTon >= :sl).
     */
    public function truTonKho(string $maCTDV, int $soLuong): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE ChiTietDichVu 
             SET SoLuongTon = SoLuongTon - :sl 
             WHERE MaCTDV = :maCTDV AND SoLuongTon >= :sl"
        );
        $stmt->execute([
            ':sl' => $soLuong,
            ':maCTDV' => $maCTDV,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cong them so luong ton kho (nhap hang).
     */
    public function congTonKho(string $maCTDV, int $soLuong): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE ChiTietDichVu 
             SET SoLuongTon = SoLuongTon + :sl 
             WHERE MaCTDV = :maCTDV"
        );
        return $stmt->execute([
            ':sl' => $soLuong,
            ':maCTDV' => $maCTDV,
        ]);
    }

    /**
     * Them nhom dich vu moi.
     */
    public function themDichVu(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO DichVu (MaDV, TenDV, MoTa, DonGia, TrangThai) 
             VALUES (:MaDV, :TenDV, :MoTa, :DonGia, :TrangThai)"
        );
        return $stmt->execute([
            ':MaDV' => $data['MaDV'],
            ':TenDV' => $data['TenDV'],
            ':MoTa' => $data['MoTa'] ?? null,
            ':DonGia' => $data['DonGia'] ?? 0,
            ':TrangThai' => $data['TrangThai'] ?? 'DangCungCap',
        ]);
    }

    /**
     * Them mat hang / chi tiet dich vu moi.
     */
    public function themChiTietDichVu(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ChiTietDichVu (MaCTDV, MaDV, TenCTDV, DonGia, SoLuongTon) 
             VALUES (:MaCTDV, :MaDV, :TenCTDV, :DonGia, :SoLuongTon)"
        );
        return $stmt->execute([
            ':MaCTDV' => $data['MaCTDV'],
            ':MaDV' => $data['MaDV'],
            ':TenCTDV' => $data['TenCTDV'],
            ':DonGia' => $data['DonGia'],
            ':SoLuongTon' => $data['SoLuongTon'] ?? 0,
        ]);
    }

    /**
     * Cap nhat thong tin mat hang chi tiet (Ten, DonGia, SoLuongTon).
     */
    public function capNhatChiTietDichVu(string $maCTDV, array $data): bool
    {
        $fields = [];
        $params = [':MaCTDV' => $maCTDV];

        if (isset($data['TenCTDV'])) {
            $fields[] = "TenCTDV = :TenCTDV";
            $params[':TenCTDV'] = $data['TenCTDV'];
        }
        if (isset($data['DonGia'])) {
            $fields[] = "DonGia = :DonGia";
            $params[':DonGia'] = $data['DonGia'];
        }
        if (isset($data['SoLuongTon'])) {
            $fields[] = "SoLuongTon = :SoLuongTon";
            $params[':SoLuongTon'] = $data['SoLuongTon'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE ChiTietDichVu SET " . implode(", ", $fields) . " WHERE MaCTDV = :MaCTDV";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Tim kiem dich vu theo tu khoa.
     */
    public function timKiemDichVu(string $keyword): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.MaCTDV, c.TenCTDV, c.DonGia, c.SoLuongTon, d.MaDV, d.TenDV 
             FROM ChiTietDichVu c 
             JOIN DichVu d ON c.MaDV = d.MaDV 
             WHERE c.TenCTDV LIKE :kw OR d.TenDV LIKE :kw 
             ORDER BY d.MaDV ASC"
        );
        $stmt->execute([':kw' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
