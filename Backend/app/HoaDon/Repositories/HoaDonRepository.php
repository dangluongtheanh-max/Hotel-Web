<?php

namespace App\HoaDon\Repositories;

use App\HoaDon\Models\HoaDon;
use App\HoaDon\Models\CTHD;
use Database\Database;

/**
 * Repository cho module HoaDon.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class HoaDonRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findHoaDonById(string $id): ?HoaDon
    {
        // TODO: SELECT * FROM hoadon WHERE id = :id
        return null;
    }

    public function getAllHoaDon(array $filters = []): array
    {
        // TODO: SELECT * FROM hoadon ... apply $filters
        return [];
    }

    public function saveHoaDon(HoaDon $hoaDon): bool
    {
        // TODO: INSERT/UPDATE hoadon
        return true;
    }

    public function deleteHoaDon(string $id): bool
    {
        // TODO: DELETE FROM hoadon WHERE id = :id
        return true;
    }

    public function findCTHDById(string $id): ?CTHD
    {
        // TODO: SELECT * FROM cthd WHERE id = :id
        return null;
    }

    public function getAllCTHD(array $filters = []): array
    {
        // TODO: SELECT * FROM cthd ... apply $filters
        return [];
    }

    public function saveCTHD(CTHD $cTHD): bool
    {
        // TODO: INSERT/UPDATE cthd
        return true;
    }

    public function deleteCTHD(string $id): bool
    {
        // TODO: DELETE FROM cthd WHERE id = :id
        return true;
    }

}
