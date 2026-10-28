<?php

declare(strict_types=1);

namespace App\KhuyenMai\Services;

use App\KhuyenMai\Repositories\KhuyenMaiRepository;

/**
 * Service Facade cho module KhuyenMai.
 * Duy trì 100% tương thích ngược cho Controller, Admin và DatPhong.
 * Ủy quyền toàn bộ xử lý nghiệp vụ logic sang KhuyenMaiXuLyService.
 */
class KhuyenMaiService
{
    private KhuyenMaiRepository $repo;
    private KhuyenMaiXuLyService $xuLyService;

    public function __construct(
        ?KhuyenMaiRepository $repo = null,
        ?KhuyenMaiXuLyService $xuLyService = null
    ) {
        $this->repo = $repo ?? new KhuyenMaiRepository();
        $xuLyRepo = $this->repo->getXuLyRepository();
        $this->xuLyService = $xuLyService ?? new KhuyenMaiXuLyService($xuLyRepo);
    }

    public function getXuLyService(): KhuyenMaiXuLyService
    {
        return $this->xuLyService;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU, TÍNH TOÁN VÀ ĐỘNG CƠ ÁP DỤNG MÃ (READ / ENGINE)
    // =========================================================================

    public function layDanhSachClient(array $params = []): array
    {
        return $this->xuLyService->layDanhSachClient($params);
    }

    public function timKhuyenMaiTotNhat(int $soDem, int $soNguoi, float $tienPhong, ?string $ngayApDung = null): ?array
    {
        return $this->xuLyService->timKhuyenMaiTotNhat($soDem, $soNguoi, $tienPhong, $ngayApDung);
    }

    public function layDanhSachAdmin(): array
    {
        return $this->xuLyService->layDanhSachAdmin();
    }

    public function apDungKhuyenMai(array $data): array
    {
        return $this->xuLyService->apDungKhuyenMai($data);
    }

    // =========================================================================
    // PHẦN 2: QUẢN TRỊ KHUYẾN MÃI (WRITE / ADMIN COMMAND)
    // =========================================================================

    public function themKhuyenMai(array $data): array
    {
        return $this->xuLyService->themKhuyenMai($data);
    }

    public function capNhatKhuyenMai(string $maKM, array $data): array
    {
        return $this->xuLyService->capNhatKhuyenMai($maKM, $data);
    }

    public function doiTrangThai(string $maKM, string $trangThai): array
    {
        return $this->xuLyService->doiTrangThai($maKM, $trangThai);
    }

    public function xoaKhuyenMai(string $maKM): array
    {
        return $this->xuLyService->xoaKhuyenMai($maKM);
    }
}