<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository quản lý chương trình khuyến mãi.
 */
class AdminKhuyenMaiRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * QUẢN LÝ KHUYẾN MÃI (Admin)
     * ========================================================================= */

    public function getAllKhuyenMai(array $filters = []): array
    {
        $sql = "SELECT MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, NgayBatDau, NgayKetThuc, TrangThai
                FROM KhuyenMai";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(MaKM LIKE :kw OR TenKM LIKE :kw)';
            $parameters[':kw'] = '%' . $filters['keyword'] . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY NgayBatDau DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * QUẢN LÝ KHUYẾN MÃI (Admin CRUD)
     * ========================================================================= */

    public function generateMaKhuyenMai(): string
    {
        $stmt = $this->db->query("SELECT MaKM FROM KhuyenMai WHERE MaKM LIKE 'KM%' ORDER BY MaKM DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, 2);
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        return sprintf('KM%03d', $maxNum + 1);
    }

    public function findKhuyenMaiById(string $maKM): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM KhuyenMai WHERE MaKM = ? LIMIT 1");
        $stmt->execute([$maKM]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createKhuyenMai(array $data): array
    {
        $maKM = !empty($data['MaKM']) ? trim($data['MaKM']) : $this->generateMaKhuyenMai();
        $check = $this->db->prepare("SELECT 1 FROM KhuyenMai WHERE MaKM = ?");
        $check->execute([$maKM]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã khuyến mãi '{$maKM}' đã tồn tại!");
        }

        $sql = "INSERT INTO KhuyenMai (MaKM, TenKM, LoaiKM, GiaTriGiam, SoDemToiThieu, NgayBatDau, NgayKetThuc, TrangThai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $maKM,
            trim($data['TenKM'] ?? ''),
            $data['LoaiKM'] ?? 'PhanTram',
            (float)($data['GiaTriGiam'] ?? 0),
            (int)($data['SoDemToiThieu'] ?? 0),
            $data['NgayBatDau'] ?? date('Y-m-d'),
            $data['NgayKetThuc'] ?? date('Y-m-d', strtotime('+30 days')),
            $data['TrangThai'] ?? 'HoatDong'
        ]);

        return $this->findKhuyenMaiById($maKM);
    }

    public function updateKhuyenMai(string $maKM, array $data): bool
    {
        $fields = [];
        $params = [];
        if (isset($data['TenKM'])) {
            $fields[] = 'TenKM = :ten';
            $params[':ten'] = trim($data['TenKM']);
        }
        if (isset($data['LoaiKM'])) {
            $fields[] = 'LoaiKM = :loai';
            $params[':loai'] = trim($data['LoaiKM']);
        }
        if (isset($data['GiaTriGiam'])) {
            $fields[] = 'GiaTriGiam = :giaTri';
            $params[':giaTri'] = (float)$data['GiaTriGiam'];
        }
        if (isset($data['SoDemToiThieu'])) {
            $fields[] = 'SoDemToiThieu = :soDem';
            $params[':soDem'] = (int)$data['SoDemToiThieu'];
        }
        if (isset($data['NgayBatDau'])) {
            $fields[] = 'NgayBatDau = :batDau';
            $params[':batDau'] = $data['NgayBatDau'];
        }
        if (isset($data['NgayKetThuc'])) {
            $fields[] = 'NgayKetThuc = :ketThuc';
            $params[':ketThuc'] = $data['NgayKetThuc'];
        }
        if (isset($data['TrangThai'])) {
            $fields[] = 'TrangThai = :trangThai';
            $params[':trangThai'] = $data['TrangThai'];
        }

        if (empty($fields)) return false;
        $params[':ma'] = $maKM;
        $stmt = $this->db->prepare("UPDATE KhuyenMai SET " . implode(', ', $fields) . " WHERE MaKM = :ma");
        return $stmt->execute($params);
    }

    public function toggleTrangThaiKhuyenMai(string $maKM): array
    {
        $item = $this->findKhuyenMaiById($maKM);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy khuyến mãi '{$maKM}'!");
        }

        $newStatus = ($item['TrangThai'] === 'NgungApDung') ? 'HoatDong' : 'NgungApDung';
        $stmt = $this->db->prepare("UPDATE KhuyenMai SET TrangThai = ? WHERE MaKM = ?");
        $stmt->execute([$newStatus, $maKM]);

        $item['TrangThai'] = $newStatus;
        return $item;
    }

}
