<?php

namespace App\DatPhong\Repositories;

use App\DatPhong\Models\PhieuNhanPhong;
use App\DatPhong\Models\KhuyenMai;
use Database\Database;

/**
 * Repository cho module DatPhong.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class DatPhongRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findPhieuNhanPhongById(string $id): ?PhieuNhanPhong
    {
        // TODO: SELECT * FROM phieunhanphong WHERE id = :id
        return null;
    }

    public function getAllPhieuNhanPhong(array $filters = []): array
    {
        // TODO: SELECT * FROM phieunhanphong ... apply $filters
        return [];
    }

    public function savePhieuNhanPhong(PhieuNhanPhong $phieuNhanPhong): bool
    {
        // TODO: INSERT/UPDATE phieunhanphong
        return true;
    }

    public function deletePhieuNhanPhong(string $id): bool
    {
        // TODO: DELETE FROM phieunhanphong WHERE id = :id
        return true;
    }

    public function findKhuyenMaiById(string $id): ?KhuyenMai
    {
        // TODO: SELECT * FROM khuyenmai WHERE id = :id
        return null;
    }

    public function getAllKhuyenMai(array $filters = []): array
    {
        // TODO: SELECT * FROM khuyenmai ... apply $filters
        return [];
    }

    public function saveKhuyenMai(KhuyenMai $khuyenMai): bool
    {
        // TODO: INSERT/UPDATE khuyenmai
        return true;
    }

    public function deleteKhuyenMai(string $id): bool
    {
        // TODO: DELETE FROM khuyenmai WHERE id = :id
        return true;
    }

}
