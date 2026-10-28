<?php

declare(strict_types=1);

namespace App\KhachHang\Repositories;

use Database\Database;
use PDO;

/**
 * Repository Facade cho module KhachHang.
 * Duy trì 100% tương thích ngược cho toàn bộ codebase và các bài test.
 * Ủy quyền toàn bộ xử lý nghiệp vụ CSDL sang KhachHangXuLyRepository.
 */
class KhachHangRepository
{
    private ?Database $db;
    private KhachHangXuLyRepository $xuLyRepo;

    public function __construct(?Database $db = null, ?KhachHangXuLyRepository $xuLyRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->xuLyRepo = $xuLyRepo ?? new KhachHangXuLyRepository($this->db);
    }

    public function getXuLyRepository(): KhachHangXuLyRepository
    {
        return $this->xuLyRepo;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & KIỂM TRA TỒN TẠI (READ / QUERY)
    // =========================================================================

    public function findByMaTK(string $maTK, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->findByMaTK($maTK, $db);
    }

    public function findBySDT(string $sdt, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->findBySDT($sdt, $db);
    }

    public function findById(string $maKH, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->findById($maKH, $db);
    }

    public function getThongTinCaNhan(string $identifier): ?array
    {
        return $this->xuLyRepo->getThongTinCaNhan($identifier);
    }

    public function checkEmailExistsForOther(string $email, string $maTK): bool
    {
        return $this->xuLyRepo->checkEmailExistsForOther($email, $maTK);
    }

    public function checkSDTExistsForOther(string $sdt, string $maTK): bool
    {
        return $this->xuLyRepo->checkSDTExistsForOther($sdt, $maTK);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC, SINH MÃ & CẬP NHẬT HỒ SƠ (WRITE / COMMAND)
    // =========================================================================

    public function generateMaKH(?PDO $db = null): string
    {
        return $this->xuLyRepo->generateMaKH($db);
    }

    public function themKhachHang(
        string $maKH,
        string $hoTen,
        ?string $sdt = null,
        ?string $maTK = null,
        ?string $ngaySinh = null,
        string $quocTich = 'Việt Nam',
        string $loaiGiayTo = 'CCCD',
        ?string $soGiayTo = null,
        ?PDO $db = null
    ): bool {
        return $this->xuLyRepo->themKhachHang(
            $maKH,
            $hoTen,
            $sdt,
            $maTK,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $db
        );
    }

    public function capNhatThongTinCaNhan(
        string $maTK,
        string $hoTen,
        ?string $sdt,
        string $email,
        ?string $ngaySinh = null,
        string $quocTich = 'Việt Nam',
        string $loaiGiayTo = 'CCCD',
        ?string $soGiayTo = null
    ): bool {
        return $this->xuLyRepo->capNhatThongTinCaNhan(
            $maTK,
            $hoTen,
            $sdt,
            $email,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo
        );
    }

    public function capNhatThongTinDinhDanh(
        string $maKH,
        ?string $ngaySinh,
        ?string $quocTich,
        ?string $loaiGiayTo,
        ?string $soGiayTo,
        ?PDO $db = null
    ): bool {
        return $this->xuLyRepo->capNhatThongTinDinhDanh(
            $maKH,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $db
        );
    }

    public function doiMatKhau(string $maTK, string $matKhauCu, string $matKhauMoi): bool
    {
        return $this->xuLyRepo->doiMatKhau($maTK, $matKhauCu, $matKhauMoi);
    }
}
