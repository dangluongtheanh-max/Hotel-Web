<?php

declare(strict_types=1);

namespace App\DanhGia\Services;

use App\DanhGia\Repositories\DanhGiaRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

/**
 * Facade Service cho module DanhGia.
 * Đóng vai trò làm lớp trung gian (Facade / Delegator) gọi lại DanhGiaXuLyService.
 * Giữ nguyên 100% public API để đảm bảo tương thích với Controller và hệ thống.
 */
class DanhGiaService
{
    private DanhGiaRepository $repository;
    private KhachHangRepository $khachHangRepo;
    private DanhGiaXuLyService $xuLyService;

    public function __construct(
        DanhGiaRepository $repository,
        ?KhachHangRepository $khachHangRepo = null,
        ?DanhGiaXuLyService $xuLyService = null
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
        $this->xuLyService = $xuLyService ?? new DanhGiaXuLyService($this->repository, $this->khachHangRepo);
    }

    public function getXuLyService(): DanhGiaXuLyService
    {
        return $this->xuLyService;
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 1: TRA CỨU & HIỂN THỊ ĐÁNH GIÁ (READ / QUERY)
    // =========================================================================

    public function xemDanhGiaTheoPhieu(array $params = []): array
    {
        return $this->xuLyService->xemDanhGiaTheoPhieu($params);
    }

    public function xemChiTietDanhGia(string $maDG): array
    {
        return $this->xuLyService->xemChiTietDanhGia($maDG);
    }

    public function layDanhSachDanhGia(array $filters = []): array
    {
        return $this->xuLyService->layDanhSachDanhGia($filters);
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 2: THAO TÁC & XÁC THỰC NGHIỆP VỤ (WRITE / MUTATION)
    // =========================================================================

    public function themDanhGia(array $params = []): array
    {
        return $this->xuLyService->themDanhGia($params);
    }

    public function xoaDanhGia(array $params = []): array
    {
        return $this->xuLyService->xoaDanhGia($params);
    }
}
