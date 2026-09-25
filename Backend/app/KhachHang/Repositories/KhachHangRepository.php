<?php

namespace App\KhachHang\Repositories;

use App\KhachHang\Models\KhachHang;
use Database\Database;

/**
 * Repository cho module KhachHang.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class KhachHangRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findKhachHangById(string $id): ?KhachHang
    {
        // TODO: SELECT * FROM khachhang WHERE id = :id
        return null;
    }

    public function getAllKhachHang(array $filters = []): array
    {
        // TODO: SELECT * FROM khachhang ... apply $filters
        return [];
    }

    public function saveKhachHang(KhachHang $khachHang): bool
    {
        // TODO: INSERT/UPDATE khachhang
        return true;
    }

    public function deleteKhachHang(string $id): bool
    {
        // TODO: DELETE FROM khachhang WHERE id = :id
        return true;
    }

}
