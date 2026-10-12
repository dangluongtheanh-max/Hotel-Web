<?php

namespace App\Phong\Repositories;

use App\Phong\Models\Phong;
use Database\Database;
use PDO;

/**
 * Repository cho module Phong.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class PhongRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findPhongById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.MaPhong, p.TrangThaiPhong, p.MaLoaiPhong,
                    lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa, lp.MoTa, lp.TienNghi
             FROM Phong p
             JOIN LoaiPhong lp ON lp.MaLoaiPhong = p.MaLoaiPhong
             WHERE p.MaPhong = :maPhong'
        );
        $stmt->execute([':maPhong' => $id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        return $room ?: null;
    }

    public function getAllPhong(array $filters = []): array
    {
        $sql = 'SELECT p.MaPhong, p.TrangThaiPhong, p.MaLoaiPhong,
                       lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa, lp.MoTa, lp.TienNghi
                FROM Phong p
                JOIN LoaiPhong lp ON lp.MaLoaiPhong = p.MaLoaiPhong';
        $conditions = [];
        $parameters = [];

        foreach (['MaLoaiPhong' => 'p.MaLoaiPhong', 'TrangThaiPhong' => 'p.TrangThaiPhong'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $conditions[] = "{$column} = :{$key}";
                $parameters[":{$key}"] = $filters[$key];
            }
        }

        if (!empty($filters['MaPhong'])) {
            $conditions[] = 'p.MaPhong = :maPhong';
            $parameters[':maPhong'] = $filters['MaPhong'];
        }

        if (!empty($filters['keyword'])) {
            $conditions[] = '(p.MaPhong LIKE :roomKeyword OR p.MaLoaiPhong LIKE :typeKeyword OR lp.TenLoaiPhong LIKE :typeNameKeyword)';
            $keyword = '%' . $filters['keyword'] . '%';
            $parameters[':roomKeyword'] = $keyword;
            $parameters[':typeKeyword'] = $keyword;
            $parameters[':typeNameKeyword'] = $keyword;
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.MaPhong ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPhong(Phong $phong): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO Phong (MaPhong, TrangThaiPhong, MaLoaiPhong)
             VALUES (:maPhong, :trangThaiPhong, :maLoaiPhong)'
        );

        return $stmt->execute([
            ':maPhong' => $phong->getMaPhong(),
            ':trangThaiPhong' => $phong->getTrangThaiPhong(),
            ':maLoaiPhong' => $phong->getMaLoaiPhong(),
        ]);
    }

    public function updatePhong(Phong $phong): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE Phong
             SET TrangThaiPhong = :trangThaiPhong, MaLoaiPhong = :maLoaiPhong
             WHERE MaPhong = :maPhong'
        );

        return $stmt->execute([
            ':maPhong' => $phong->getMaPhong(),
            ':trangThaiPhong' => $phong->getTrangThaiPhong(),
            ':maLoaiPhong' => $phong->getMaLoaiPhong(),
        ]);
    }

    public function updateTrangThaiPhong(string $id, string $trangThai): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE Phong SET TrangThaiPhong = :trangThai WHERE MaPhong = :maPhong'
        );

        return $stmt->execute([
            ':trangThai' => $trangThai,
            ':maPhong' => $id,
        ]);
    }

    public function existsLoaiPhong(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM LoaiPhong WHERE MaLoaiPhong = :maLoaiPhong');
        $stmt->execute([':maLoaiPhong' => $id]);

        return $stmt->fetchColumn() !== false;
    }

}
