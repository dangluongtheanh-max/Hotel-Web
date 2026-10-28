<?php

declare(strict_types=1);

namespace App\KhachHang\Services;

use App\KhachHang\Repositories\KhachHangRepository;
use App\TaiKhoan\Repositories\TaiKhoanRepository;

/**
 * Service Facade cho module KhachHang.
 * Duy trì 100% tương thích ngược cho toàn bộ codebase và các bài test.
 * Ủy quyền toàn bộ xử lý nghiệp vụ logic sang KhachHangXuLyService.
 */
class KhachHangService
{
    private KhachHangRepository $khachHangRepo;
    private KhachHangXuLyService $xuLyService;

    public function __construct(
        ?KhachHangRepository $khachHangRepo = null,
        ?TaiKhoanRepository $taiKhoanRepo = null,
        ?KhachHangXuLyService $xuLyService = null
    ) {
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository();
        $xuLyRepo = $this->khachHangRepo->getXuLyRepository();
        $this->xuLyService = $xuLyService ?? new KhachHangXuLyService($xuLyRepo, $taiKhoanRepo);
    }

    public function getXuLyService(): KhachHangXuLyService
    {
        return $this->xuLyService;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & VALIDATION ĐỊNH DẠNG (READ / VALIDATION)
    // =========================================================================

    public function xemThongTinCaNhan(array $params = []): array
    {
        return $this->xuLyService->xemThongTinCaNhan($params);
    }

    public function timTheoSDT(string $sdt): ?array
    {
        return $this->xuLyService->timTheoSDT($sdt);
    }

    public function validateSDT(string $sdt, string $prefix = ''): void
    {
        $this->xuLyService->validateSDT($sdt, $prefix);
    }

    public function validateSoGiayTo(?string $loaiGiayTo, ?string $soGiayTo, string $prefix = ''): void
    {
        $this->xuLyService->validateSoGiayTo($loaiGiayTo, $soGiayTo, $prefix);
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC ĐĂNG KÝ, CẬP NHẬT HỒ SƠ & MẬT KHẨU (WRITE / COMMAND)
    // =========================================================================

    public function dangKy(array $params = []): array
    {
        return $this->xuLyService->dangKy($params);
    }

    public function capNhatThongTinCaNhan(array $params = []): array
    {
        return $this->xuLyService->capNhatThongTinCaNhan($params);
    }

    public function doiMatKhau(array $params = []): array
    {
        return $this->xuLyService->doiMatKhau($params);
    }
}
