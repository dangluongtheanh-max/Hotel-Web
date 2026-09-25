<?php

namespace App\DanhGia\Repositories;

use App\DanhGia\Models\DanhGia;
use Database\Database;

/**
 * Repository cho module DanhGia.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class DanhGiaRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findDanhGiaById(string $id): ?DanhGia
    {
        // TODO: SELECT * FROM danhgia WHERE id = :id
        return null;
    }

    public function getAllDanhGia(array $filters = []): array
    {
        // TODO: SELECT * FROM danhgia ... apply $filters
        return [];
    }

    public function saveDanhGia(DanhGia $danhGia): bool
    {
        // TODO: INSERT/UPDATE danhgia
        return true;
    }

    public function deleteDanhGia(string $id): bool
    {
        // TODO: DELETE FROM danhgia WHERE id = :id
        return true;
    }

}
