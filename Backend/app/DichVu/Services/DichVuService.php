<?php

declare(strict_types=1);

namespace App\DichVu\Services;

use App\DichVu\Repositories\DichVuRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

/**
 * Facade Service cho module DichVu.
 * Điều phối các nghiệp vụ chung và chuyển tiếp sang các sub-services chuyên biệt:
 * - DatDoAnService: Thực đơn món ăn, đặt món tại phòng
 * - GiatUiService: Danh mục đồ giặt, đặt dịch vụ giặt ủi
 * - ThueXeMayService: Danh mục xe máy, đặt thuê xe máy
 */
class DichVuService
{
    private DichVuRepository $repository;
    private DatDoAnService $datDoAnService;
    private GiatUiService $giatUiService;
    private ThueXeMayService $thueXeMayService;

    public function __construct(
        ?DichVuRepository $repository = null,
        ?DatDoAnService $datDoAnService = null,
        ?GiatUiService $giatUiService = null,
        ?ThueXeMayService $thueXeMayService = null
    ) {
        $this->repository = $repository ?? new DichVuRepository();
        $khachHangRepo = new KhachHangRepository(new Database());

        $this->datDoAnService = $datDoAnService ?? new DatDoAnService(
            $this->repository->getDatDoAnRepository(),
            $khachHangRepo
        );

        $this->giatUiService = $giatUiService ?? new GiatUiService(
            $this->repository->getGiatUiRepository(),
            $khachHangRepo
        );

        $this->thueXeMayService = $thueXeMayService ?? new ThueXeMayService(
            $this->repository->getThueXeMayRepository(),
            $khachHangRepo
        );
    }

    public function getDatDoAnService(): DatDoAnService
    {
        return $this->datDoAnService;
    }

    public function getGiatUiService(): GiatUiService
    {
        return $this->giatUiService;
    }

    public function getThueXeMayService(): ThueXeMayService
    {
        return $this->thueXeMayService;
    }

    // =========================================================================
    // DỊCH VỤ CHUNG
    // =========================================================================

    public function layTatCaDichVu(): array
    {
        $danhSach = $this->repository->getAllDichVu();
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Lấy danh sách tất cả dịch vụ thành công.',
            'data' => $danhSach
        ];
    }

    public function layPhongDangLuuTru(?string $maTK = null, ?string $maKH = null): array
    {
        return $this->datDoAnService->layPhongDangLuuTru($maTK, $maKH);
    }

    // =========================================================================
    // ỦY QUYỀN SANG CÁC PHÂN HỆ CHUYÊN BIỆT
    // =========================================================================

    public function layDanhSachMonAn(): array
    {
        return $this->datDoAnService->layDanhSachMonAn();
    }

    public function datMonAn(array $params = []): array
    {
        return $this->datDoAnService->datMonAn($params);
    }

    public function layDanhSachGiatUi(): array
    {
        return $this->giatUiService->layDanhSachGiatUi();
    }

    public function datDichVuGiatUi(array $params = []): array
    {
        return $this->giatUiService->datDichVuGiatUi($params);
    }

    public function layDanhSachXeMay(): array
    {
        return $this->thueXeMayService->layDanhSachXeMay();
    }

    public function datThueXeMay(array $params = []): array
    {
        return $this->thueXeMayService->datThueXeMay($params);
    }
}
