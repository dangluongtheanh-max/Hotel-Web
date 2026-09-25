<?php

namespace App\ThanhToan\Repositories;

use App\ThanhToan\Models\ThanhToan;
use Database\Database;

/**
 * Repository cho module ThanhToan.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class ThanhToanRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findThanhToanById(string $id): ?ThanhToan
    {
        // TODO: SELECT * FROM thanhtoan WHERE id = :id
        return null;
    }

    public function getAllThanhToan(array $filters = []): array
    {
        // TODO: SELECT * FROM thanhtoan ... apply $filters
        return [];
    }

    public function saveThanhToan(ThanhToan $thanhToan): bool
    {
        // TODO: INSERT/UPDATE thanhtoan
        return true;
    }

    public function deleteThanhToan(string $id): bool
    {
        // TODO: DELETE FROM thanhtoan WHERE id = :id
        return true;
    }

}
