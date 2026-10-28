<?php

declare(strict_types=1);

namespace App\DichVu\Repositories;

use Database\Database;
use PDO;

/**
 * Facade Repository cho module DichVu.
 * Đóng vai trò lớp trung gian điều phối sang các Sub-Repositories chuyên biệt:
 * - DatDoAnRepository: Thực đơn món ăn, đặt món phục vụ tại phòng
 * - GiatUiRepository: Danh mục đồ giặt, đặt dịch vụ giặt ủi
 * - ThueXeMayRepository: Danh mục xe máy, đặt thuê xe máy
 */
class DichVuRepository
{
    private ?Database $db;
    private DatDoAnRepository $datDoAnRepo;
    private GiatUiRepository $giatUiRepo;
    private ThueXeMayRepository $thueXeMayRepo;

    public function __construct(
        ?Database $db = null,
        ?DatDoAnRepository $datDoAnRepo = null,
        ?GiatUiRepository $giatUiRepo = null,
        ?ThueXeMayRepository $thueXeMayRepo = null
    ) {
        $this->db = $db ?? new Database();
        $this->datDoAnRepo = $datDoAnRepo ?? new DatDoAnRepository($this->db);
        $this->giatUiRepo = $giatUiRepo ?? new GiatUiRepository($this->db);
        $this->thueXeMayRepo = $thueXeMayRepo ?? new ThueXeMayRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    // =========================================================================
    // GETTERS CHO CÁC SUB-REPOSITORIES
    // =========================================================================

    public function getDatDoAnRepository(): DatDoAnRepository
    {
        return $this->datDoAnRepo;
    }

    public function getGiatUiRepository(): GiatUiRepository
    {
        return $this->giatUiRepo;
    }

    public function getThueXeMayRepository(): ThueXeMayRepository
    {
        return $this->thueXeMayRepo;
    }

    // =========================================================================
    // DỊCH VỤ CHUNG & BẢNG GIÁ TẤT CẢ DỊCH VỤ
    // =========================================================================

    /**
     * Lấy danh sách toàn bộ dịch vụ kèm chi tiết dịch vụ đang cung cấp trong khách sạn.
     */
    public function getAllDichVu(?PDO $db = null): array
    {
        return $this->datDoAnRepo->getAllDichVu($db);
    }

    /**
     * Lấy thông tin phòng đang lưu trú của khách hàng.
     */
    public function timThongTinLuuTruHienTai(string $maKH, ?PDO $db = null): array
    {
        return $this->datDoAnRepo->timThongTinLuuTruHienTai($maKH, $db);
    }

    /**
     * Kiểm tra phòng được chọn có thực sự thuộc phiếu và đang lưu trú không.
     */
    public function kiemTraPhongCoDangLuuTru(string $maPhieu, string $maPhong, string $maKH, ?PDO $db = null): bool
    {
        return $this->datDoAnRepo->kiemTraPhongCoDangLuuTru($maPhieu, $maPhong, $maKH, $db);
    }

    // =========================================================================
    // PHÂN HỆ ĐẶT ĐỒ ĂN (DatDoAnRepo)
    // =========================================================================

    public function layDanhSachMonAn(?PDO $db = null): array
    {
        return $this->datDoAnRepo->layDanhSachMonAn($db);
    }

    public function timMonAnTheoMa(string $maCTDV, ?PDO $db = null): ?array
    {
        return $this->datDoAnRepo->timMonAnTheoMa($maCTDV, $db);
    }

    public function luuDatMonAn(
        string $maCTDV,
        string $maPhieu,
        string $maPhong,
        float $donGia,
        ?string $ghiChu,
        int $soLuong,
        ?PDO $db = null
    ): int {
        return $this->datDoAnRepo->luuDatMonAn(
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu,
            $soLuong,
            $db
        );
    }

    // =========================================================================
    // PHÂN HỆ GIẶT ỦI (GiatUiRepo)
    // =========================================================================

    public function layDanhSachGiatUi(?PDO $db = null): array
    {
        return $this->giatUiRepo->layDanhSachGiatUi($db);
    }

    public function timChiTietDichVuTheoDanhSach(array $maCTDVList, ?PDO $db = null): array
    {
        return $this->giatUiRepo->timChiTietDichVuTheoDanhSach($maCTDVList, $db);
    }

    public function luuDatDichVuNhieuMon(
        array $danhSachMon,
        string $maPhieu,
        string $maPhong,
        ?string $ghiChu = null,
        ?PDO $db = null
    ): array {
        return $this->giatUiRepo->luuDatDichVuGiatUi(
            $danhSachMon,
            $maPhieu,
            $maPhong,
            $ghiChu,
            $db
        );
    }

    // =========================================================================
    // PHÂN HỆ THUÊ XE MÁY (ThueXeMayRepo)
    // =========================================================================

    public function layDanhSachXeMay(?PDO $db = null): array
    {
        return $this->thueXeMayRepo->layDanhSachXeMay($db);
    }

    public function timXeMayTheoMa(string $maCTDV, ?PDO $db = null): ?array
    {
        return $this->thueXeMayRepo->timXeMayTheoMa($maCTDV, $db);
    }
}
