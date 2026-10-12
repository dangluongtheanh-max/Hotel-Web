<?php

namespace App\LeTan\Repositories;

use App\LeTan\Models\LeTan;
use Database\Database;
use PDO;

/**
 * Repository cho module LeTan.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class LeTanRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findLeTanById(string $id): ?LeTan
    {
        // TODO: SELECT * FROM letan WHERE id = :id
        return null;
    }

    public function getAllLeTan(array $filters = []): array
    {
        // TODO: SELECT * FROM letan ... apply $filters
        return [];
    }

    public function saveLeTan(LeTan $leTan): bool
    {
        // TODO: INSERT/UPDATE letan
        return true;
    }

    public function deleteLeTan(string $id): bool
    {
        // TODO: DELETE FROM letan WHERE id = :id
        return true;
    }

}
