<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminRepository;

/**
 * Facade Service cho module Admin & Báo Cáo Thống Kê (BE4).
 * Điều phối logic nghiệp vụ qua các Sub-Services chuyên biệt:
 * - AdminThongKeService: Báo cáo, Thống kê doanh thu, công suất phòng, đánh giá, KPI
 * - AdminNhanVienService: Quản lý nhân sự, lễ tân, tài khoản
 * - AdminPhongService: Quản lý phòng vật lý và loại phòng
 * - AdminDichVuService: Quản lý danh mục & bảng giá chi tiết dịch vụ
 * - AdminKhuyenMaiService: Quản lý chương trình khuyến mãi
 * - AdminTraCuuService: Tra cứu khách hàng, phiếu nhận phòng, hóa đơn
 */
class AdminService
{
    private AdminRepository $repository;
    private AdminThongKeService $thongKeService;
    private AdminNhanVienService $nhanVienService;
    private AdminPhongService $phongService;
    private AdminDichVuService $dichVuService;
    private AdminKhuyenMaiService $khuyenMaiService;
    private AdminTraCuuService $traCuuService;

    public function __construct(?AdminRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminRepository();
        $this->thongKeService = new AdminThongKeService($this->repository->getThongKeRepo());
        $this->nhanVienService = new AdminNhanVienService($this->repository->getNhanVienRepo());
        $this->phongService = new AdminPhongService($this->repository->getPhongRepo());
        $this->dichVuService = new AdminDichVuService($this->repository->getDichVuRepo());
        $this->khuyenMaiService = new AdminKhuyenMaiService($this->repository->getKhuyenMaiRepo());
        $this->traCuuService = new AdminTraCuuService($this->repository->getTraCuuRepo());
    }

    public function getRepository(): AdminRepository { return $this->repository; }
    public function getThongKeService(): AdminThongKeService { return $this->thongKeService; }
    public function getNhanVienService(): AdminNhanVienService { return $this->nhanVienService; }
    public function getPhongService(): AdminPhongService { return $this->phongService; }
    public function getDichVuService(): AdminDichVuService { return $this->dichVuService; }
    public function getKhuyenMaiService(): AdminKhuyenMaiService { return $this->khuyenMaiService; }
    public function getTraCuuService(): AdminTraCuuService { return $this->traCuuService; }

    // --- Delegate Thống Kê ---
    public function thongKeDoanhThu(array $params = []): array { return $this->thongKeService->thongKeDoanhThu($params); }
    public function thongKeDoanhThuLoaiPhong(array $params = []): array { return $this->thongKeService->thongKeDoanhThuLoaiPhong($params); }
    public function thongKeDoanhThuDichVu(array $params = []): array { return $this->thongKeService->thongKeDoanhThuDichVu($params); }
    public function thongKeCongSuatPhong(array $params = []): array { return $this->thongKeService->thongKeCongSuatPhong($params); }
    public function thongKeDanhGia(array $params = []): array { return $this->thongKeService->thongKeDanhGia($params); }
    public function xemChiTietDanhGia(array $params = []): array { return $this->thongKeService->xemChiTietDanhGia($params); }
    public function thongKeDoanhThuChiNhanh(array $params = []): array { return $this->thongKeService->thongKeDoanhThuChiNhanh($params); }
    public function thongKeSoLuongPhong(array $params = []): array { return $this->thongKeService->thongKeSoLuongPhong($params); }
    public function thongKeSoLuongPhieu(array $params = []): array { return $this->thongKeService->thongKeSoLuongPhieu($params); }
    public function thongKeDichVu(array $params = []): array { return $this->thongKeService->thongKeDichVu($params); }
    public function thongKeTongQuan(array $params = []): array { return $this->thongKeService->thongKeTongQuan($params); }
    public function thongKeSoLuongNhanVien(array $params = []): array { return $this->thongKeService->thongKeSoLuongNhanVien($params); }

    // --- Delegate Nhân Viên & Lễ Tân ---
    public function xemDSNhanVien(array $params = []): array { return $this->nhanVienService->xemDSNhanVien($params); }
    public function xemDSLeTan(array $params = []): array { return $this->nhanVienService->xemDSLeTan($params); }
    public function timKiemLeTan(array $params = []): array { return $this->nhanVienService->timKiemLeTan($params); }
    public function xemCTLeTan(array $params = []): array { return $this->nhanVienService->xemCTLeTan($params); }
    public function themLeTan(array $params = []): array { return $this->nhanVienService->themLeTan($params); }
    public function capNhatThongTinLeTan(array $params = []): array { return $this->nhanVienService->capNhatThongTinLeTan($params); }
    public function timKiemNhanVien(array $params = []): array { return $this->nhanVienService->timKiemNhanVien($params); }
    public function xemCTNhanVien(array $params = []): array { return $this->nhanVienService->xemCTNhanVien($params); }
    public function capNhatThongTinNhanVien(array $params = []): array { return $this->nhanVienService->capNhatThongTinNhanVien($params); }
    public function themNhanVien(array $params = []): array { return $this->nhanVienService->themNhanVien($params); }
    public function sinhMaLeTan(): array { return $this->nhanVienService->sinhMaLeTan(); }
    public function doiTrangThaiLeTan(string $maLeTan): array { return $this->nhanVienService->doiTrangThaiLeTan($maLeTan); }

    // --- Delegate Tra Cứu Vận Hành ---
    public function xemDSKhachHang(array $params = []): array { return $this->traCuuService->xemDSKhachHang($params); }
    public function xemDSPhieuNhanPhong(array $params = []): array { return $this->traCuuService->xemDSPhieuNhanPhong($params); }
    public function xemDSHoaDon(array $params = []): array { return $this->traCuuService->xemDSHoaDon($params); }

    // --- Delegate Dịch Vụ ---
    public function xemDSDichVu(array $params = []): array { return $this->dichVuService->xemDSDichVu($params); }
    public function sinhMaChiTietDichVu(string $maDV): array { return $this->dichVuService->sinhMaChiTietDichVu($maDV); }
    public function themChiTietDichVu(array $data): array { return $this->dichVuService->themChiTietDichVu($data); }
    public function suaChiTietDichVu(string $maCTDV, array $data): array { return $this->dichVuService->suaChiTietDichVu($maCTDV, $data); }
    public function doiTrangThaiChiTietDichVu(string $maCTDV): array { return $this->dichVuService->doiTrangThaiChiTietDichVu($maCTDV); }

    // --- Delegate Phòng & Loại Phòng ---
    public function xemDSPhongTheoLoai(array $params = []): array { return $this->phongService->xemDSPhongTheoLoai($params); }
    public function sinhMaLoaiPhong(): array { return $this->phongService->sinhMaLoaiPhong(); }
    public function themLoaiPhong(array $data): array { return $this->phongService->themLoaiPhong($data); }
    public function suaLoaiPhong(string $maLoaiPhong, array $data): array { return $this->phongService->suaLoaiPhong($maLoaiPhong, $data); }
    public function doiTrangThaiLoaiPhong(string $maLoaiPhong): array { return $this->phongService->doiTrangThaiLoaiPhong($maLoaiPhong); }
    public function themPhong(array $data): array { return $this->phongService->themPhong($data); }
    public function suaPhong(string $maPhong, array $data): array { return $this->phongService->suaPhong($maPhong, $data); }
    public function doiTrangThaiPhong(string $maPhong): array { return $this->phongService->doiTrangThaiPhong($maPhong); }

    // --- Delegate Khuyến Mãi ---
    public function xemDSKhuyenMai(array $params = []): array { return $this->khuyenMaiService->xemDSKhuyenMai($params); }
    public function sinhMaKhuyenMai(): array { return $this->khuyenMaiService->sinhMaKhuyenMai(); }
    public function themKhuyenMai(array $data): array { return $this->khuyenMaiService->themKhuyenMai($data); }
    public function suaKhuyenMai(string $maKM, array $data): array { return $this->khuyenMaiService->suaKhuyenMai($maKM, $data); }
    public function doiTrangThaiKhuyenMai(string $maKM): array { return $this->khuyenMaiService->doiTrangThaiKhuyenMai($maKM); }
}