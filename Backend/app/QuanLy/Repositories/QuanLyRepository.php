<?php

namespace App\QuanLy\Repositories;

use App\QuanLy\Models\QuanLy;
use Database\Database;

/**
 * Repository cho module QuanLy.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class QuanLyRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findQuanLyById(string $id): ?QuanLy
    {
        // TODO: SELECT * FROM quanly WHERE id = :id
        return null;
    }

    public function getAllQuanLy(array $filters = []): array
    {
        // TODO: SELECT * FROM quanly ... apply $filters
        return [];
    }

    public function saveQuanLy(QuanLy $quanLy): bool
    {
        // TODO: INSERT/UPDATE quanly
        return true;
    }

    public function deleteQuanLy(string $id): bool
    {
        // TODO: DELETE FROM quanly WHERE id = :id
        return true;
    }

}
