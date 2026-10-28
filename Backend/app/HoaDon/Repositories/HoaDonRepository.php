<?php

declare(strict_types=1);

namespace App\HoaDon\Repositories;

use Database\Database;
use PDO;

/**
 * Facade Repository cho module HoaDon.
 * Đóng vai trò lớp trung gian điều phối và chuyển tiếp các thao tác sang HoaDonXuLyRepository.
 * Giữ nguyên 100% public API để đảm bảo tương thích với DatPhongRepository, LeTanService,...
 */
class HoaDonRepository
{
    private ?Database $db;
    private HoaDonXuLyRepository $xuLyRepo;

    public function __construct(?Database $db = null, ?HoaDonXuLyRepository $xuLyRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->xuLyRepo = $xuLyRepo ?? new HoaDonXuLyRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function getXuLyRepository(): HoaDonXuLyRepository
    {
        return $this->xuLyRepo;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & TRUY VẤN HÓA ĐƠN (READ / QUERY)
    // =========================================================================

    public function findHoaDonById(string $id, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->findHoaDonById($id, $db);
    }

    public function findHoaDonByMaPhieu(string $maPhieu, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->findHoaDonByMaPhieu($maPhieu, $db);
    }

    public function getCTHDByMaHD(string $maHD, ?PDO $db = null): array
    {
        return $this->xuLyRepo->getCTHDByMaHD($maHD, $db);
    }

    public function getChiTietDayDuHoaDon(string $maHD, ?PDO $db = null): array
    {
        return $this->xuLyRepo->getChiTietDayDuHoaDon($maHD, $db);
    }

    public function getThongTinPhieuVaKhachHang(string $maPhieu, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->getThongTinPhieuVaKhachHang($maPhieu, $db);
    }

    public function getThanhToanByMaHD(string $maHD, ?PDO $db = null): array
    {
        return $this->xuLyRepo->getThanhToanByMaHD($maHD, $db);
    }

    public function getKhuyenMaiById(string $maKM, ?PDO $db = null): ?array
    {
        return $this->xuLyRepo->getKhuyenMaiById($maKM, $db);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC & LƯU TRỮ HÓA ĐƠN (WRITE / COMMAND)
    // =========================================================================

    public function generateMaHoaDon(?PDO $db = null): string
    {
        return $this->xuLyRepo->generateMaHoaDon($db);
    }

    public function luuHoaDon(
        string $maHD,
        string $maPhieu,
        float $tongTienSauVAT,
        PDO $db
    ): bool {
        return $this->xuLyRepo->luuHoaDon($maHD, $maPhieu, $tongTienSauVAT, $db);
    }

    public function luuCTHD(
        string $maHD,
        string $maPhieu,
        ?string $maPhong,
        ?string $maCTDV,
        int $soDem,
        int $soLuong,
        float $donGia,
        PDO $db
    ): bool {
        return $this->xuLyRepo->luuCTHD($maHD, $maPhieu, $maPhong, $maCTDV, $soDem, $soLuong, $donGia, $db);
    }
}
