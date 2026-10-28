<?php

declare(strict_types=1);

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\ThanhToan\Repositories\ThanhToanRepository;

/**
 * Facade Service cho module LeTan.
 * Điều phối các sub-services chuyên trách:
 * - LeTanPhieuService: Quản lý danh sách phiếu, chi tiết phiếu, check-in, tính tiền hoàn hủy & hủy phiếu.
 * - LeTanCheckoutService: Phụ phí trễ giờ, tính tiền trả phòng và check-out quyết toán.
 * - LeTanPhongVaKhachService: Sơ đồ phòng, đổi phòng, cập nhật giấy tờ lưu trú, bảo trì, dịch vụ nhanh.
 *
 * Giữ 100% tương thích ngược (Backward Compatibility) với các Controller và Route hiện hành.
 */
class LeTanService
{
    private LeTanPhieuService $phieuService;
    private LeTanCheckoutService $checkoutService;
    private LeTanPhongVaKhachService $phongVaKhachService;

    public function __construct(
        ?LeTanRepository $repository = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanRepository $thanhToanRepo = null,
        ?LeTanPhieuService $phieuService = null,
        ?LeTanCheckoutService $checkoutService = null,
        ?LeTanPhongVaKhachService $phongVaKhachService = null
    ) {
        $repo = $repository ?? new LeTanRepository();
        $hdRepo = $hoaDonRepo ?? new HoaDonRepository();
        $ttRepo = $thanhToanRepo ?? new ThanhToanRepository();

        $this->phieuService = $phieuService ?? new LeTanPhieuService($repo->getPhieuRepo(), $repo->getPhongVaKhachRepo());
        $this->checkoutService = $checkoutService ?? new LeTanCheckoutService($repo->getCheckoutRepo(), $repo->getPhieuRepo(), $hdRepo, $ttRepo);
        $this->phongVaKhachService = $phongVaKhachService ?? new LeTanPhongVaKhachService($repo->getPhongVaKhachRepo(), $repo->getPhieuRepo());
    }

    public function getPhieuService(): LeTanPhieuService
    {
        return $this->phieuService;
    }

    public function getCheckoutService(): LeTanCheckoutService
    {
        return $this->checkoutService;
    }

    public function getPhongVaKhachService(): LeTanPhongVaKhachService
    {
        return $this->phongVaKhachService;
    }

    // ==========================================
    // NHÓM 1: QUẢN LÝ PHIẾU VÀ CHECK-IN / HỦY PHIẾU
    // ==========================================

    public function xemDSPhieu(array $params = []): array
    {
        return $this->phieuService->xemDSPhieu($params);
    }

    public function xemCTPhieu(array $params = []): array
    {
        return $this->phieuService->xemCTPhieu($params);
    }

    public function timKiemPhieu(array $params = []): array
    {
        return $this->phieuService->timKiemPhieu($params);
    }

    public function checkin(array $params = []): array
    {
        return $this->phieuService->checkin($params);
    }

    public function tinhTienHoanHuyPhong(array $params = []): array
    {
        return $this->phieuService->tinhTienHoanHuyPhong($params);
    }

    public function huyPhieu(array $params = []): array
    {
        return $this->phieuService->huyPhieu($params);
    }

    public function resolveMaLeTan(string $identifier = 'LT001'): string
    {
        return $this->phieuService->resolveMaLeTan($identifier);
    }

    // ==========================================
    // NHÓM 2: CHECK-OUT VÀ QUYẾT TOÁN TRẢ PHÒNG
    // ==========================================

    public function tinhPhuPhiTreGio(string $thoiGianTraDuKien, float $donGiaPhong, ?int $thoiGianThucTeTs = null): array
    {
        return $this->checkoutService->tinhPhuPhiTreGio($thoiGianTraDuKien, $donGiaPhong, $thoiGianThucTeTs);
    }

    public function tinhTienCheckout(array $params = []): array
    {
        return $this->checkoutService->tinhTienCheckout($params);
    }

    public function checkout(array $params = []): array
    {
        return $this->checkoutService->checkout($params);
    }

    // ==========================================
    // NHÓM 3: SƠ ĐỒ PHÒNG, BẢO TRÌ, KHÁCH & DỊCH VỤ NHANH
    // ==========================================

    public function doiPhongNguoiLuuTru(array $params = []): array
    {
        return $this->phongVaKhachService->doiPhongNguoiLuuTru($params);
    }

    public function capNhatThongTinKhach(array $params = []): array
    {
        return $this->phongVaKhachService->capNhatThongTinKhach($params);
    }

    public function xemTrangThaiLoaiPhongVaPhong(array $params = []): array
    {
        return $this->phongVaKhachService->xemTrangThaiLoaiPhongVaPhong($params);
    }

    public function xemDSPhong(array $params = []): array
    {
        return $this->phongVaKhachService->xemDSPhong($params);
    }

    public function xemCTPhong(array $params = []): ?array
    {
        return $this->phongVaKhachService->xemCTPhong($params);
    }

    public function timKiemPhong(array $params = []): array
    {
        return $this->phongVaKhachService->timKiemPhong($params);
    }

    public function timHoaDon(array $params = [])
    {
        return $this->phongVaKhachService->timHoaDon($params);
    }

    public function xemHoaDon(array $params = [])
    {
        return $this->phongVaKhachService->xemHoaDon($params);
    }

    public function xemCTHD(array $params = [])
    {
        return $this->phongVaKhachService->xemCTHD($params);
    }

    public function capNhatBaoTriPhong(array $params = []): array
    {
        return $this->phongVaKhachService->capNhatBaoTriPhong($params);
    }

    public function layDanhSachDichVu(array $params = []): array
    {
        return $this->phongVaKhachService->layDanhSachDichVu($params);
    }

    public function datDichVuNhanh(array $params = []): array
    {
        return $this->phongVaKhachService->datDichVuNhanh($params);
    }
}
