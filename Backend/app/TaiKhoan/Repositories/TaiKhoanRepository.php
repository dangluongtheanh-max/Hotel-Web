<?php

declare(strict_types=1);

namespace App\TaiKhoan\Repositories;

use App\TaiKhoan\Models\TaiKhoan;
use Database\Database;
use PDO;

/**
 * Repository cho module TaiKhoan.
 * Chịu trách nhiệm toàn bộ truy vấn CSDL của module TaiKhoan.
 */
class TaiKhoanRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Tìm kiếm tài khoản theo tên đăng nhập
     */
    public function findByTenDangNhap(string $tenDangNhap): ?TaiKhoan
    {
        $sql = "SELECT * FROM TaiKhoan WHERE TenDangNhap = ?";
        $db = $this->getConnection();

        $stmt = $db->prepare($sql);
        $stmt->execute([$tenDangNhap]);

        $taiKhoan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$taiKhoan) {
            return null;
        }

        return new TaiKhoan(
            $taiKhoan['MaTK'],
            $taiKhoan['TenDangNhap'],
            $taiKhoan['MatKhau'],
            $taiKhoan['Email'],
            $taiKhoan['TrangThaiTaiKhoan'],
            $taiKhoan['VaiTro']
        );
    }

    /**
     * Tìm kiếm tài khoản theo email
     */
    public function findByEmail(string $email): ?TaiKhoan
    {
        $sql = "SELECT * FROM TaiKhoan WHERE Email = ?";
        $db = $this->getConnection();

        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);

        $taiKhoan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$taiKhoan) {
            return null;
        }

        return new TaiKhoan(
            $taiKhoan['MaTK'],
            $taiKhoan['TenDangNhap'],
            $taiKhoan['MatKhau'],
            $taiKhoan['Email'],
            $taiKhoan['TrangThaiTaiKhoan'],
            $taiKhoan['VaiTro']
        );
    }

    /**
     * Kiểm tra số điện thoại đã tồn tại trong hệ thống (bảng KhachHang) hay chưa
     */
    public function checkTonTaiSDT(string $sdt): bool
    {
        $sql = "SELECT 1 FROM KhachHang WHERE SDT = ? LIMIT 1";
        $db = $this->getConnection();

        $stmt = $db->prepare($sql);
        $stmt->execute([$sdt]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Sinh mã tài khoản tự động theo định dạng TK001, TK002,... dựa trên dữ liệu trong CSDL
     */
    public function generateMaTK(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaTK FROM TaiKhoan WHERE MaTK LIKE 'TK%' ORDER BY CAST(SUBSTRING(MaTK, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['MaTK'])) {
            $lastNumber = (int) substr($row['MaTK'], 2);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('TK%03d', $nextNumber);
    }

    /**
     * Thêm tài khoản mới vào cơ sở dữ liệu (SDT được quản lý ở KhachHang/LeTan/Admin)
     */
    public function themTaiKhoan(
        string $maTK,
        string $tenDangNhap,
        string $matKhau,
        string $email,
        string $vaiTro = 'KhachHang',
        ?PDO $db = null
    ): bool {
        $db = $db ?? $this->getConnection();
        $trangThai = 'HoatDong';

        $sql = "INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            $maTK,
            $tenDangNhap,
            $matKhau,
            $email,
            $trangThai,
            $vaiTro
        ]);
    }
}
