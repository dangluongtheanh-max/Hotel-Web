<?php

namespace App\Phong\Repositories;

use App\Phong\Models\Phong;
use App\Phong\Models\LoaiPhong;
use Database\Database;

/**
 * Repository cho module Phong.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class PhongRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findPhongById(string $id): ?Phong
    {
        // TODO: SELECT * FROM phong WHERE id = :id
        return null;
    }

    public function getAllPhong(array $filters = []): array
    {
        // TODO: SELECT * FROM phong ... apply $filters
        return [];
    }

    public function savePhong(Phong $phong): bool
    {
        // TODO: INSERT/UPDATE phong
        return true;
    }

    public function deletePhong(string $id): bool
    {
        // TODO: DELETE FROM phong WHERE id = :id
        return true;
    }

    public function findLoaiPhongById(string $id): ?LoaiPhong
    {
        // TODO: SELECT * FROM loaiphong WHERE id = :id
        return null;
    }

    public function getAllLoaiPhong(array $filters = []): array
    {
        // TODO: SELECT * FROM loaiphong ... apply $filters
        return [];
    }

    public function saveLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        // TODO: INSERT/UPDATE loaiphong
        return true;
    }

    public function deleteLoaiPhong(string $id): bool
    {
        // TODO: DELETE FROM loaiphong WHERE id = :id
        return true;
    }

}
