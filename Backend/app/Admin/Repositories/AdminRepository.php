<?php

namespace App\Admin\Repositories;

use App\Admin\Models\Admin;
use Database\Database;

/**
 * Repository cho module Admin.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class AdminRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findAdminById(string $id): ?Admin
    {
        // TODO: SELECT * FROM admin WHERE id = :id
        return null;
    }

    public function getAllAdmin(array $filters = []): array
    {
        // TODO: SELECT * FROM admin ... apply $filters
        return [];
    }

    public function saveAdmin(Admin $admin): bool
    {
        // TODO: INSERT/UPDATE admin
        return true;
    }

    public function deleteAdmin(string $id): bool
    {
        // TODO: DELETE FROM admin WHERE id = :id
        return true;
    }

}
