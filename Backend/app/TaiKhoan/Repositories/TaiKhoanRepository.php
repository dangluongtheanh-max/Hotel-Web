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
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findByTenDangNhap(string $tenDangNhap): ?TaiKhoan
{
    $sql = "SELECT * FROM TaiKhoan WHERE TenDangNhap = ?";
    $db = Database::getConnection();

    $stmt = $db->prepare($sql);// chuẩn bị 1 câu lệnh để đưa vào SQL, giúp tránh SQL Injection 
    $stmt->execute([$tenDangNhap]); // thực thi câu lệnh SQL với tham số truyền vào

    $taiKhoan = $stmt->fetch(PDO::FETCH_ASSOC); // lấy kết quả trả về dưới dạng mảng kết hợp (associative array)

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
