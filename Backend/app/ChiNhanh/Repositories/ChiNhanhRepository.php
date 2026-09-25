<?php

namespace App\ChiNhanh\Repositories;

use App\ChiNhanh\Models\ChiNhanh;
use Database\Database;

/**
 * Repository cho module ChiNhanh.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class ChiNhanhRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findChiNhanhById(string $id): ?ChiNhanh
    {
        // TODO: SELECT * FROM chinhanh WHERE id = :id
        return null;
    }

    public function getAllChiNhanh(array $filters = []): array
    {
        // TODO: SELECT * FROM chinhanh ... apply $filters
        return [];
    }

    public function saveChiNhanh(ChiNhanh $chiNhanh): bool
    {
        // TODO: INSERT/UPDATE chinhanh
        return true;
    }

    public function deleteChiNhanh(string $id): bool
    {
        // TODO: DELETE FROM chinhanh WHERE id = :id
        return true;
    }

}
