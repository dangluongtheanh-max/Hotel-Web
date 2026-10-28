<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository quản lý danh mục & bảng giá chi tiết dịch vụ.
 */
class AdminDichVuRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * QUẢN LÝ DỊCH VỤ (Admin)
     * ========================================================================= */

    public function getAllDichVu(array $filters = []): array
    {
        $sql = "SELECT ctdv.MaCTDV, ctdv.TenCTDV, ctdv.DonGia, ctdv.SoLuongTon, ctdv.MoTa, ctdv.MoTaChiTiet,
                       COALESCE(ctdv.TrangThai, 'DangCungCap') AS TrangThai,
                       dv.MaDV, dv.TenDV, dv.TrangThai AS TrangThaiNhom
                FROM ChiTietDichVu ctdv
                JOIN DichVu dv ON ctdv.MaDV = dv.MaDV";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['maDV'])) {
            $conditions[] = 'ctdv.MaDV = :maDV';
            $parameters[':maDV'] = $filters['maDV'];
        }

        if (!empty($filters['keyword'])) {
            $conditions[] = '(ctdv.MaCTDV LIKE :kw OR ctdv.TenCTDV LIKE :kw OR dv.TenDV LIKE :kw)';
            $parameters[':kw'] = '%' . $filters['keyword'] . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY dv.MaDV ASC, ctdv.MaCTDV ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findChiTietDichVuById(string $maCTDV): ?array
    {
        $sql = "SELECT ctdv.MaCTDV, ctdv.MaDV, ctdv.TenCTDV, ctdv.DonGia, ctdv.SoLuongTon, 
                       ctdv.MoTa, ctdv.MoTaChiTiet, COALESCE(ctdv.TrangThai, 'DangCungCap') AS TrangThai,
                       dv.TenDV, dv.TrangThai AS TrangThaiNhom
                FROM ChiTietDichVu ctdv
                JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                WHERE ctdv.MaCTDV = ?
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$maCTDV]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function generateMaChiTietDichVu(string $maDV): string
    {
        $prefixMap = [
            'DVDoAn' => 'CTDVDoAn',
            'DVGiatUi' => 'CTDVGiatUi',
            'DVThueXe' => 'CTDVThueXe',
        ];
        $prefix = $prefixMap[$maDV] ?? 'CTDV';

        $stmt = $this->db->prepare("SELECT MaCTDV FROM ChiTietDichVu WHERE MaCTDV LIKE :prefix ORDER BY MaCTDV DESC");
        $stmt->execute([':prefix' => $prefix . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, strlen($prefix));
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;
        return sprintf('%s%02d', $prefix, $nextNum);
    }

    public function createChiTietDichVu(array $data): array
    {
        $maDV = $data['MaDV'] ?? 'DVDoAn';
        $maCTDV = !empty($data['MaCTDV']) ? trim($data['MaCTDV']) : $this->generateMaChiTietDichVu($maDV);
        $tenCTDV = trim($data['TenCTDV'] ?? '');
        $donGia = (float)($data['DonGia'] ?? 0);
        $soLuongTon = (int)($data['SoLuongTon'] ?? 0);
        $moTa = $data['MoTa'] ?? null;
        $moTaChiTiet = $data['MoTaChiTiet'] ?? null;
        $trangThai = !empty($data['TrangThai']) ? $data['TrangThai'] : 'DangCungCap';

        $check = $this->db->prepare("SELECT 1 FROM ChiTietDichVu WHERE MaCTDV = ?");
        $check->execute([$maCTDV]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã chi tiết dịch vụ '{$maCTDV}' đã tồn tại trong hệ thống!");
        }

        $sql = "INSERT INTO ChiTietDichVu (MaCTDV, MaDV, TenCTDV, MoTa, MoTaChiTiet, DonGia, SoLuongTon, TrangThai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $maCTDV,
            $maDV,
            $tenCTDV,
            $moTa,
            $moTaChiTiet,
            $donGia,
            $soLuongTon,
            $trangThai
        ]);

        return $this->findChiTietDichVuById($maCTDV);
    }

    public function updateChiTietDichVu(string $maCTDV, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['TenCTDV'])) {
            $fields[] = 'TenCTDV = :tenCTDV';
            $params[':tenCTDV'] = trim($data['TenCTDV']);
        }
        if (isset($data['MaDV'])) {
            $fields[] = 'MaDV = :maDV';
            $params[':maDV'] = trim($data['MaDV']);
        }
        if (isset($data['DonGia'])) {
            $fields[] = 'DonGia = :donGia';
            $params[':donGia'] = (float)$data['DonGia'];
        }
        if (isset($data['SoLuongTon'])) {
            $fields[] = 'SoLuongTon = :soLuongTon';
            $params[':soLuongTon'] = (int)$data['SoLuongTon'];
        }
        if (array_key_exists('MoTa', $data)) {
            $fields[] = 'MoTa = :moTa';
            $params[':moTa'] = $data['MoTa'];
        }
        if (array_key_exists('MoTaChiTiet', $data)) {
            $fields[] = 'MoTaChiTiet = :moTaChiTiet';
            $params[':moTaChiTiet'] = $data['MoTaChiTiet'];
        }
        if (isset($data['TrangThai'])) {
            $fields[] = 'TrangThai = :trangThai';
            $params[':trangThai'] = $data['TrangThai'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[':maCTDV'] = $maCTDV;
        $sql = "UPDATE ChiTietDichVu SET " . implode(', ', $fields) . " WHERE MaCTDV = :maCTDV";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function toggleTrangThaiChiTietDichVu(string $maCTDV): array
    {
        $item = $this->findChiTietDichVuById($maCTDV);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy chi tiết dịch vụ '{$maCTDV}'!");
        }

        $currentStatus = $item['TrangThai'] ?? 'DangCungCap';
        $newStatus = ($currentStatus === 'DangCungCap') ? 'NgungCungCap' : 'DangCungCap';

        $stmt = $this->db->prepare("UPDATE ChiTietDichVu SET TrangThai = ? WHERE MaCTDV = ?");
        $stmt->execute([$newStatus, $maCTDV]);

        $item['TrangThai'] = $newStatus;
        return $item;
    }

}
