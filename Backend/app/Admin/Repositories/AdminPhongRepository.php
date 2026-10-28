<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository quản lý phòng vật lý và loại phòng.
 */
class AdminPhongRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * QUẢN LÝ PHÒNG THEO TỪNG LOẠI PHÒNG (Admin)
     * ========================================================================= */

    public function getPhongTheoLoai(): array
    {
        $sqlLoai = "SELECT MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi, TinhTrang
                    FROM LoaiPhong 
                    ORDER BY Gia ASC";
        $dsLoai = $this->db->query($sqlLoai)->fetchAll(PDO::FETCH_ASSOC);

        $sqlPhong = "SELECT MaPhong, TrangThaiPhong, MaLoaiPhong FROM Phong ORDER BY MaPhong ASC";
        $dsPhong = $this->db->query($sqlPhong)->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($dsLoai as $loai) {
            $maLoai = $loai['MaLoaiPhong'];
            $phongThuocLoai = array_values(array_filter($dsPhong, function($p) use ($maLoai) {
                return $p['MaLoaiPhong'] === $maLoai;
            }));

            $loai['danh_sach_phong'] = $phongThuocLoai;
            $loai['tong_phong'] = count($phongThuocLoai);
            $loai['phong_trong'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'Trong'));
            $loai['phong_dang_dung'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'DangSuDung'));
            $loai['phong_da_dat'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'DaDat'));
            $loai['phong_bao_tri'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'BaoTri'));
            $loai['phong_cho_thanh_toan'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'ChoThanhToan'));

            $result[] = $loai;
        }

        return $result;
    }

    /* =========================================================================
     * QUẢN LÝ LOẠI PHÒNG (Admin CRUD)
     * ========================================================================= */

    public function generateMaLoaiPhong(): string
    {
        $stmt = $this->db->query("SELECT MaLoaiPhong FROM LoaiPhong WHERE MaLoaiPhong LIKE 'LP%' ORDER BY MaLoaiPhong DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, 2);
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        return sprintf('LP%03d', $maxNum + 1);
    }

    public function findLoaiPhongById(string $maLoaiPhong): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM LoaiPhong WHERE MaLoaiPhong = ? LIMIT 1");
        $stmt->execute([$maLoaiPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createLoaiPhong(array $data): array
    {
        $maLoai = !empty($data['MaLoaiPhong']) ? trim($data['MaLoaiPhong']) : $this->generateMaLoaiPhong();
        $check = $this->db->prepare("SELECT 1 FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $check->execute([$maLoai]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã loại phòng '{$maLoai}' đã tồn tại!");
        }

        $sql = "INSERT INTO LoaiPhong (MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi, TinhTrang)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $maLoai,
            trim($data['TenLoaiPhong'] ?? ''),
            (float)($data['Gia'] ?? 0),
            (int)($data['SoNguoiToiDa'] ?? 2),
            $data['MoTa'] ?? null,
            $data['TienNghi'] ?? null,
            $data['TinhTrang'] ?? 'ConPhong'
        ]);

        return $this->findLoaiPhongById($maLoai);
    }

    public function updateLoaiPhong(string $maLoaiPhong, array $data): bool
    {
        $fields = [];
        $params = [];
        if (isset($data['TenLoaiPhong'])) {
            $fields[] = 'TenLoaiPhong = :ten';
            $params[':ten'] = trim($data['TenLoaiPhong']);
        }
        if (isset($data['Gia'])) {
            $fields[] = 'Gia = :gia';
            $params[':gia'] = (float)$data['Gia'];
        }
        if (isset($data['SoNguoiToiDa'])) {
            $fields[] = 'SoNguoiToiDa = :soNguoi';
            $params[':soNguoi'] = (int)$data['SoNguoiToiDa'];
        }
        if (array_key_exists('MoTa', $data)) {
            $fields[] = 'MoTa = :moTa';
            $params[':moTa'] = $data['MoTa'];
        }
        if (array_key_exists('TienNghi', $data)) {
            $fields[] = 'TienNghi = :tienNghi';
            $params[':tienNghi'] = $data['TienNghi'];
        }
        if (isset($data['TinhTrang'])) {
            $fields[] = 'TinhTrang = :tinhTrang';
            $params[':tinhTrang'] = $data['TinhTrang'];
        }

        if (empty($fields)) return false;
        $params[':ma'] = $maLoaiPhong;
        $stmt = $this->db->prepare("UPDATE LoaiPhong SET " . implode(', ', $fields) . " WHERE MaLoaiPhong = :ma");
        return $stmt->execute($params);
    }

    public function toggleTrangThaiLoaiPhong(string $maLoaiPhong): array
    {
        $item = $this->findLoaiPhongById($maLoaiPhong);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy loại phòng '{$maLoaiPhong}'!");
        }

        $newStatus = ($item['TinhTrang'] === 'NgungKinhDoanh') ? 'ConPhong' : 'NgungKinhDoanh';
        $stmt = $this->db->prepare("UPDATE LoaiPhong SET TinhTrang = ? WHERE MaLoaiPhong = ?");
        $stmt->execute([$newStatus, $maLoaiPhong]);

        $item['TinhTrang'] = $newStatus;
        return $item;
    }

    /* =========================================================================
     * QUẢN LÝ PHÒNG CỤ THỂ (Admin CRUD)
     * ========================================================================= */

    public function findPhongById(string $maPhong): ?array
    {
        $stmt = $this->db->prepare("SELECT p.*, lp.TenLoaiPhong, lp.Gia FROM Phong p JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong WHERE p.MaPhong = ? LIMIT 1");
        $stmt->execute([$maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPhong(array $data): array
    {
        $maPhong = trim($data['MaPhong'] ?? '');
        if (empty($maPhong)) {
            throw new \InvalidArgumentException("Mã phòng không được để trống!");
        }

        $check = $this->db->prepare("SELECT 1 FROM Phong WHERE MaPhong = ?");
        $check->execute([$maPhong]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã phòng '{$maPhong}' đã tồn tại!");
        }

        $sql = "INSERT INTO Phong (MaPhong, MaLoaiPhong, TrangThaiPhong) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $maPhong,
            trim($data['MaLoaiPhong'] ?? 'LP001'),
            $data['TrangThaiPhong'] ?? 'Trong'
        ]);

        return $this->findPhongById($maPhong);
    }

    public function updatePhong(string $maPhong, array $data): bool
    {
        $fields = [];
        $params = [];
        if (isset($data['MaLoaiPhong'])) {
            $fields[] = 'MaLoaiPhong = :maLoai';
            $params[':maLoai'] = trim($data['MaLoaiPhong']);
        }
        if (isset($data['TrangThaiPhong'])) {
            $fields[] = 'TrangThaiPhong = :trangThai';
            $params[':trangThai'] = trim($data['TrangThaiPhong']);
        }
        if (empty($fields)) return false;

        $params[':ma'] = $maPhong;
        $stmt = $this->db->prepare("UPDATE Phong SET " . implode(', ', $fields) . " WHERE MaPhong = :ma");
        return $stmt->execute($params);
    }

    public function toggleTrangThaiPhong(string $maPhong): array
    {
        $item = $this->findPhongById($maPhong);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy phòng '{$maPhong}'!");
        }

        if ($item['TrangThaiPhong'] === 'DangSuDung') {
            throw new \RuntimeException("Phòng '{$maPhong}' đang có khách lưu trú! Không thể ngừng hoạt động phòng lúc này.");
        }

        $newStatus = ($item['TrangThaiPhong'] === 'BaoTri') ? 'Trong' : 'BaoTri';
        $stmt = $this->db->prepare("UPDATE Phong SET TrangThaiPhong = ? WHERE MaPhong = ?");
        $stmt->execute([$newStatus, $maPhong]);

        $item['TrangThaiPhong'] = $newStatus;
        return $item;
    }

}
