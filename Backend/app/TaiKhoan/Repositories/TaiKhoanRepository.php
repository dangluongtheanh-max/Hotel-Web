<?php

namespace App\TaiKhoan\Repositories;

use App\TaiKhoan\Models\TaiKhoan;
use Database\Database;

/**
 * Repository cho module TaiKhoan.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class TaiKhoanRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findTaiKhoanById(string $id): ?TaiKhoan
    {
        // TODO: SELECT * FROM taikhoan WHERE id = :id
        return null;
    }

    public function getAllTaiKhoan(array $filters = []): array
    {
        // TODO: SELECT * FROM taikhoan ... apply $filters
        return [];
    }

    public function saveTaiKhoan(TaiKhoan $taiKhoan): bool
    {
        // TODO: INSERT/UPDATE taikhoan
        return true;
    }

    public function deleteTaiKhoan(string $id): bool
    {
        // TODO: DELETE FROM taikhoan WHERE id = :id
        return true;
    }

}
