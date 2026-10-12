<?php

namespace App\TaiKhoan\Repositories;

use App\TaiKhoan\Models\TaiKhoan;
use Database\Database;
use PDO;

/**
 * Repository cho module TaiKhoan.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class TaiKhoanRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findByTenDangNhap(string $tenDangNhap): ?TaiKhoan
    {
        $account = $this->findAccountForLogin($tenDangNhap);
        if ($account === null) {
            return null;
        }

        return new TaiKhoan(
            $account['MaTK'],
            $account['TenDangNhap'],
            $account['MatKhau'],
            $account['Email'],
            $account['TrangThaiTaiKhoan'],
            $account['VaiTro']
        );
    }

    public function findAccountForLogin(string $tenDangNhap): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro
             FROM TaiKhoan
             WHERE TenDangNhap = :tenDangNhap
             LIMIT 1'
        );
        $stmt->execute([':tenDangNhap' => $tenDangNhap]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    public function findAccountById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT MaTK, TenDangNhap, Email, TrangThaiTaiKhoan, VaiTro
             FROM TaiKhoan WHERE MaTK = :maTK'
        );
        $stmt->execute([':maTK' => $id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    public function findTaiKhoanById(string $id): ?array
    {
        return $this->findAccountById($id);
    }

    public function getAllTaiKhoan(array $filters = []): array
    {
        $sql = 'SELECT MaTK, TenDangNhap, Email, TrangThaiTaiKhoan, VaiTro
                FROM TaiKhoan';
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(MaTK LIKE :idKeyword OR TenDangNhap LIKE :usernameKeyword OR Email LIKE :emailKeyword)';
            $keyword = '%' . $filters['keyword'] . '%';
            $parameters[':idKeyword'] = $keyword;
            $parameters[':usernameKeyword'] = $keyword;
            $parameters[':emailKeyword'] = $keyword;
        }
        foreach (['VaiTro', 'TrangThaiTaiKhoan'] as $field) {
            if (!empty($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[":{$field}"] = $filters[$field];
            }
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY MaTK';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTaiKhoan(TaiKhoan $taiKhoan): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE TaiKhoan
             SET TenDangNhap = :tenDangNhap, MatKhau = :matKhau, Email = :email,
                 TrangThaiTaiKhoan = :trangThai, VaiTro = :vaiTro
             WHERE MaTK = :maTK'
        );

        return $stmt->execute([
            ':maTK' => $taiKhoan->getMaTK(),
            ':tenDangNhap' => $taiKhoan->getTenDangNhap(),
            ':matKhau' => $taiKhoan->getMatKhau(),
            ':email' => $taiKhoan->getEmail(),
            ':trangThai' => $taiKhoan->getTrangThaiTaiKhoan(),
            ':vaiTro' => $taiKhoan->getVaiTro(),
        ]);
    }

    public function deleteTaiKhoan(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM TaiKhoan WHERE MaTK = :maTK');

        return $stmt->execute([':maTK' => $id]);
    }

    public function updatePasswordHash(string $id, string $hash): bool
    {
        $stmt = $this->db->prepare('UPDATE TaiKhoan SET MatKhau = :hash WHERE MaTK = :maTK');

        return $stmt->execute([':hash' => $hash, ':maTK' => $id]);
    }

    public function updateAccountStatus(string $id, string $status): bool
    {
        $account = $this->findAccountById($id);
        if ($account === null) {
            return false;
        }
        if (
            $status === 'BiKhoa'
            && $account['VaiTro'] === 'Admin'
            && $account['TrangThaiTaiKhoan'] === 'HoatDong'
        ) {
            $activeAdmins = (int)$this->db->query(
                "SELECT COUNT(*) FROM TaiKhoan
                 WHERE VaiTro = 'Admin' AND TrangThaiTaiKhoan = 'HoatDong'"
            )->fetchColumn();
            if ($activeAdmins <= 1) {
                throw new \DomainException('Không thể khóa tài khoản Admin đang hoạt động cuối cùng.');
            }
        }

        $stmt = $this->db->prepare(
            'UPDATE TaiKhoan SET TrangThaiTaiKhoan = :status WHERE MaTK = :maTK'
        );

        return $stmt->execute([':status' => $status, ':maTK' => $id]);
    }

    public function updateAccountRole(string $id, string $role): bool
    {
        $account = $this->findAccountById($id);
        if ($account === null) {
            return false;
        }
        if (
            $account['VaiTro'] === 'Admin'
            && $account['TrangThaiTaiKhoan'] === 'HoatDong'
            && $role !== 'Admin'
        ) {
            $activeAdmins = (int)$this->db->query(
                "SELECT COUNT(*) FROM TaiKhoan
                 WHERE VaiTro = 'Admin' AND TrangThaiTaiKhoan = 'HoatDong'"
            )->fetchColumn();
            if ($activeAdmins <= 1) {
                throw new \DomainException('Không thể thay đổi vai trò của Admin đang hoạt động cuối cùng.');
            }
        }

        if (!in_array($role, ['Admin', 'LeTan', 'KhachHang'], true)) {
            throw new \DomainException('Vai trò không hợp lệ.');
        }

        $stmt = $this->db->prepare('UPDATE TaiKhoan SET VaiTro = :role WHERE MaTK = :maTK');

        return $stmt->execute([':role' => $role, ':maTK' => $id]);
    }

}
