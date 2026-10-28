<?php

declare(strict_types=1);

namespace App\HoaDon\Services;

use App\HoaDon\Repositories\HoaDonRepository;

/**
 * Facade Service cho module HoaDon.
 * Đóng vai trò lớp trung gian điều phối và chuyển tiếp sang HoaDonXuLyService.
 * Giữ nguyên 100% public API để đảm bảo Controller và hệ thống hoạt động ổn định.
 */
class HoaDonService
{
    private HoaDonRepository $repository;
    private HoaDonXuLyService $xuLyService;

    public function __construct(
        ?HoaDonRepository $repository = null,
        ?HoaDonXuLyService $xuLyService = null
    ) {
        $this->repository = $repository ?? new HoaDonRepository();
        $this->xuLyService = $xuLyService ?? new HoaDonXuLyService($this->repository);
    }

    public function getXuLyService(): HoaDonXuLyService
    {
        return $this->xuLyService;
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 1: TRA CỨU & TÍNH TOÁN CHI PHÍ (READ / QUERY)
    // =========================================================================

    public function xemHoaDon(array $params = []): array
    {
        return $this->xuLyService->xemHoaDon($params);
    }

    public function xemCTHD(array $params = []): array
    {
        return $this->xuLyService->xemCTHD($params);
    }

    public function timHoaDon(array $params = []): array
    {
        return $this->xuLyService->timHoaDon($params);
    }

    // =========================================================================
    // DELEGATE SANG PHẦN 2: KHỞI TẠO & LƯU TRỮ HÓA ĐƠN (WRITE / COMMAND)
    // =========================================================================

    public function taoHoaDon(array $params = []): array
    {
        return $this->xuLyService->taoHoaDon($params);
    }
}
