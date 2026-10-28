<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use App\Phong\Services\PhongService;
use App\ThanhToan\Repositories\ThanhToanRepository;
use Database\Database;

/**
 * Facade Service cho module DatPhong.
 * Điều phối và chuyển tiếp các nghiệp vụ sang các Sub-Services chuyên biệt:
 * - TimKiemPhongService: Tìm kiếm, quy đổi khách, tính số đêm
 * - ChiPhiDatPhongService: Tính toán chi phí, khuyến mãi, dịch vụ
 * - ThucHienDatPhongService: Đặt phòng và thanh toán (Transaction)
 * - ChiTietDatPhongService: Xem chi tiết phiếu & trạng thái đánh giá
 * - NguoiLuuTruService: Cập nhật thông tin người lưu trú
 */
class DatPhongService
{
    private DatPhongRepository $repository;
    private PhongService $phongService;
    private TimKiemPhongService $timKiemService;
    private ChiPhiDatPhongService $chiPhiService;
    private NguoiLuuTruValidator $validator;
    private KhachHangRepository $khachHangRepo;
    private HoaDonRepository $hoaDonRepo;
    private ThanhToanRepository $thanhToanRepo;
    private ?DanhGiaService $danhGiaService;
    private ThucHienDatPhongService $thucHienService;
    private ChiTietDatPhongService $chiTietService;
    private NguoiLuuTruService $nguoiLuuTruService;

    public function __construct(
        DatPhongRepository $repository,
        PhongService $phongService,
        ?TimKiemPhongService $timKiemService = null,
        ?ChiPhiDatPhongService $chiPhiService = null,
        ?NguoiLuuTruValidator $validator = null,
        ?KhachHangRepository $khachHangRepo = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanRepository $thanhToanRepo = null,
        ?DanhGiaService $danhGiaService = null,
        ?ThucHienDatPhongService $thucHienService = null,
        ?ChiTietDatPhongService $chiTietService = null,
        ?NguoiLuuTruService $nguoiLuuTruService = null
    ) {
        $this->repository = $repository;
        $this->phongService = $phongService;
        $this->timKiemService = $timKiemService ?? new TimKiemPhongService($phongService);
        $this->chiPhiService = $chiPhiService ?? new ChiPhiDatPhongService($phongService);
        $this->validator = $validator ?? new NguoiLuuTruValidator();
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository();
        $this->thanhToanRepo = $thanhToanRepo ?? new ThanhToanRepository();
        $this->danhGiaService = $danhGiaService ?? new DanhGiaService(new DanhGiaRepository());

        $this->thucHienService = $thucHienService ?? new ThucHienDatPhongService(
            $this->repository,
            $this->phongService,
            $this->chiPhiService,
            $this->validator,
            $this->khachHangRepo,
            $this->hoaDonRepo,
            $this->thanhToanRepo
        );

        $this->chiTietService = $chiTietService ?? new ChiTietDatPhongService(
            $this->repository,
            $this->khachHangRepo,
            $this->danhGiaService
        );

        $this->nguoiLuuTruService = $nguoiLuuTruService ?? new NguoiLuuTruService(
            $this->repository,
            $this->khachHangRepo,
            $this->validator
        );
    }

    // =========================================================================
    // GETTERS CHO CÁC SUB-SERVICES
    // =========================================================================

    public function getTimKiemService(): TimKiemPhongService
    {
        return $this->timKiemService;
    }

    public function getChiPhiService(): ChiPhiDatPhongService
    {
        return $this->chiPhiService;
    }

    public function getThucHienService(): ThucHienDatPhongService
    {
        return $this->thucHienService;
    }

    public function getChiTietService(): ChiTietDatPhongService
    {
        return $this->chiTietService;
    }

    public function getNguoiLuuTruService(): NguoiLuuTruService
    {
        return $this->nguoiLuuTruService;
    }

    // =========================================================================
    // TÌM KIẾM VÀ GỢI Ý PHÒNG (TimKiemService)
    // =========================================================================

    public function quyDoiSoKhach(int $soNguoiLon, int $soTreEm): int
    {
        return $this->timKiemService->quyDoiSoKhach($soNguoiLon, $soTreEm);
    }

    public function tinhSoDem(string $ngayNhan, string $ngayTra): int
    {
        return $this->timKiemService->tinhSoDem($ngayNhan, $ngayTra);
    }

    public function timKiemVaGoiY(array $params = []): array
    {
        return $this->timKiemService->timKiemVaGoiY($params);
    }

    // =========================================================================
    // CHI PHÍ, DỊCH VỤ VÀ KHUYẾN MÃI (ChiPhiService)
    // =========================================================================

    public function layDanhSachDichVu(): array
    {
        return $this->chiPhiService->layDanhSachDichVu();
    }

    public function kiemTraKhuyenMai(string $maKM, int $soDem, float $tienPhong): array
    {
        return $this->chiPhiService->kiemTraKhuyenMai($maKM, $soDem, $tienPhong);
    }

    public function tinhChiPhiTamTinh(array $params = []): array
    {
        return $this->chiPhiService->tinhChiPhiTamTinh($params);
    }

    // =========================================================================
    // ĐẶT PHÒNG VÀ THANH TOÁN (ThucHienService)
    // =========================================================================

    public function datPhongVaThanhToan(array $params = []): array
    {
        return $this->thucHienService->datPhongVaThanhToan($params);
    }

    // =========================================================================
    // CHI TIẾT PHIẾU ĐẶT PHÒNG (ChiTietService)
    // =========================================================================

    public function xemChiTietPhieu(string $maPhieu, ?string $maTK = null): ?array
    {
        return $this->chiTietService->xemChiTietPhieu($maPhieu, $maTK);
    }

    // =========================================================================
    // CẬP NHẬT THÔNG TIN NGƯỜI LƯU TRÚ (NguoiLuuTruService)
    // =========================================================================

    public function capNhatNguoiLuuTru(array $params, ?string $maTK = null): array
    {
        return $this->nguoiLuuTruService->capNhatNguoiLuuTru($params, $maTK);
    }
}
