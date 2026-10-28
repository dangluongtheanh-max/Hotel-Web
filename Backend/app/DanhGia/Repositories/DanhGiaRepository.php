<?php

declare(strict_types=1);

namespace App\DanhGia\Repositories;

use App\DanhGia\Models\DanhGia;
use Database\Database;

/**
 * Facade Repository cho module DanhGia.
 * Đóng vai trò làm lớp trung gian (Facade / Delegator) gọi lại DanhGiaXuLyRepository.
 * Giữ nguyên 100% public API để đảm bảo tính tương thích ngược toàn hệ thống.
 */
class DanhGiaRepository
{
    private ?Database $db;
    private DanhGiaXuLyRepository $xuLyRepo;

    public function __construct(?Database $db = null, ?DanhGiaXuLyRepository $xuLyRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->xuLyRepo = $xuLyRepo ?? new DanhGiaXuLyRepository($this->db);
    }

    public function getXuLyRepository(): DanhGiaXuLyRepository
    {
        return $this->xuLyRepo;
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 1: TRA CỨU & TRUY VẤN ĐÁNH GIÁ (READ / QUERY)
    // =========================================================================

    public function findDanhGiaById(string $id): ?DanhGia
    {
        return $this->xuLyRepo->findDanhGiaById($id);
    }

    public function findByPhieuVaPhong(string $maPhieu, string $maPhong): ?DanhGia
    {
        return $this->xuLyRepo->findByPhieuVaPhong($maPhieu, $maPhong);
    }

    public function getDanhGiaByPhieu(string $maPhieu): array
    {
        return $this->xuLyRepo->getDanhGiaByPhieu($maPhieu);
    }

    public function getAllDanhGia(array $filters = []): array
    {
        return $this->xuLyRepo->getAllDanhGia($filters);
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 2: THAO TÁC & KIỂM TRA ĐIỀU KIỆN (WRITE / COMMAND)
    // =========================================================================

    public function generateMaDanhGia(): string
    {
        return $this->xuLyRepo->generateMaDanhGia();
    }

    public function kiemTraDieuKienDanhGia(string $maPhieu, string $maPhong): ?array
    {
        return $this->xuLyRepo->kiemTraDieuKienDanhGia($maPhieu, $maPhong);
    }

    public function saveDanhGia(DanhGia $danhGia): bool
    {
        return $this->xuLyRepo->saveDanhGia($danhGia);
    }

    public function deleteDanhGia(string $id): bool
    {
        return $this->xuLyRepo->deleteDanhGia($id);
    }
}
