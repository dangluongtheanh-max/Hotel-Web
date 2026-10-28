<?php

declare(strict_types=1);

namespace App\KhuyenMai\Repositories;

use App\KhuyenMai\Models\KhuyenMai;

/**
 * Repository Facade cho module KhuyenMai.
 * Duy trì 100% tương thích ngược cho toàn bộ codebase và các module liên quan (DatPhong, Admin).
 * Ủy quyền toàn bộ xử lý nghiệp vụ CSDL sang KhuyenMaiXuLyRepository.
 */
class KhuyenMaiRepository
{
    private KhuyenMaiXuLyRepository $xuLyRepo;

    public function __construct($db = null, ?KhuyenMaiXuLyRepository $xuLyRepo = null)
    {
        $this->xuLyRepo = $xuLyRepo ?? new KhuyenMaiXuLyRepository($db);
    }

    public function getXuLyRepository(): KhuyenMaiXuLyRepository
    {
        return $this->xuLyRepo;
    }

    public function getConnection(): \PDO
    {
        return $this->xuLyRepo->getConnection();
    }

    public function tangSoLuongDaDung(string $maKM, ?\PDO $db = null): bool
    {
        return $this->xuLyRepo->tangSoLuongDaDung($maKM, $db);
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & KIỂM TRA HIỆU LỰC KHUYẾN MÃI (READ / QUERY)
    // =========================================================================

    public function getAll(): array
    {
        return $this->xuLyRepo->getAll();
    }

    public function getActivePromotions(?string $today = null): array
    {
        return $this->xuLyRepo->getActivePromotions($today);
    }

    public function getUpcomingPromotions(?string $today = null): array
    {
        return $this->xuLyRepo->getUpcomingPromotions($today);
    }

    public function findByMaKM(string $maKM): ?array
    {
        return $this->xuLyRepo->findByMaKM($maKM);
    }

    public function findKhuyenMaiById(string $maKM): ?KhuyenMai
    {
        return $this->xuLyRepo->findKhuyenMaiById($maKM);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC THÊM, CẬP NHẬT, ĐỔI TRẠNG THÁI & XÓA (WRITE / COMMAND)
    // =========================================================================

    public function create(array $data): bool
    {
        return $this->xuLyRepo->create($data);
    }

    public function update(string $maKM, array $data): bool
    {
        return $this->xuLyRepo->update($maKM, $data);
    }

    public function updateStatus(string $maKM, string $trangThai): bool
    {
        return $this->xuLyRepo->updateStatus($maKM, $trangThai);
    }

    public function delete(string $maKM): bool
    {
        return $this->xuLyRepo->delete($maKM);
    }
}