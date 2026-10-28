<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;

/**
 * Facade Repository cho module LeTan.
 * Điều phối các sub-repositories chuyên trách:
 * - LeTanPhieuRepository: Quản lý danh sách phiếu, chi tiết phiếu, check-in, hủy phiếu.
 * - LeTanCheckoutRepository: Check-out và quyết toán trả phòng.
 * - LeTanPhongVaKhachRepository: Sơ đồ phòng, bảo trì, thông tin người lưu trú, dịch vụ nhanh.
 *
 * Giữ 100% tương thích ngược (Backward Compatibility) với các controller và service cũ.
 */
class LeTanRepository
{
    private ?Database $db;
    private LeTanPhieuRepository $phieuRepo;
    private LeTanCheckoutRepository $checkoutRepo;
    private LeTanPhongVaKhachRepository $phongVaKhachRepo;

    public function __construct(
        ?Database $db = null,
        ?LeTanPhieuRepository $phieuRepo = null,
        ?LeTanCheckoutRepository $checkoutRepo = null,
        ?LeTanPhongVaKhachRepository $phongVaKhachRepo = null
    ) {
        $this->db = $db ?? new Database();
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository($this->db);
        $this->checkoutRepo = $checkoutRepo ?? new LeTanCheckoutRepository($this->db);
        $this->phongVaKhachRepo = $phongVaKhachRepo ?? new LeTanPhongVaKhachRepository($this->db, $this->phieuRepo);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function getPhieuRepo(): LeTanPhieuRepository
    {
        return $this->phieuRepo;
    }

    public function getCheckoutRepo(): LeTanCheckoutRepository
    {
        return $this->checkoutRepo;
    }

    public function getPhongVaKhachRepo(): LeTanPhongVaKhachRepository
    {
        return $this->phongVaKhachRepo;
    }

    /**
     * Tự động quét và chuyển sang trạng thái DaHuy khi quá 24h tính từ ThoiGianNhanPhong
     */
    public function tuDongHuyPhieuQuaHan24h(): int
    {
        return $this->phieuRepo->tuDongHuyPhieuQuaHan24h();
    }

    /**
     * Lấy danh sách phiếu đặt phòng theo bộ lọc trạng thái hoặc từ khóa
     */
    public function getDanhSachPhieu(array $filters = []): array
    {
        return $this->phieuRepo->getDanhSachPhieu($filters);
    }

    /**
     * Lấy đầy đủ chi tiết của 1 phiếu nhận phòng phục vụ đối soát Check-in / Check-out
     */
    public function getChiTietPhieu(string $maPhieu): ?array
    {
        return $this->phieuRepo->getChiTietPhieu($maPhieu);
    }

    /**
     * Lấy các dịch vụ đã sử dụng của phiếu (từ CTSuDungDichVu)
     */
    public function getDichVuSuDung(string $maPhieu): array
    {
        return $this->phieuRepo->getDichVuSuDung($maPhieu);
    }

    /**
     * Cập nhật trạng thái Check-in thành công: DaDatPhong -> DangLuuTru
     */
    public function capNhatCheckin(string $maPhieu, string $maLeTan = 'LT001', ?string $maPhong = null): bool
    {
        return $this->phieuRepo->capNhatCheckin($maPhieu, $maLeTan, $maPhong);
    }

    /**
     * Cập nhật Hủy phiếu / Hủy từng phòng lẻ
     */
    public function capNhatHuy(string $maPhieu, ?string $maLeTan = null, ?string $maPhong = null, ?string $lyDo = null): bool
    {
        return $this->phieuRepo->capNhatHuy($maPhieu, $maLeTan, $maPhong, $lyDo);
    }

    /**
     * Cập nhật Check-out hoàn tất: DangLuuTru -> DaTraPhong
     */
    public function capNhatCheckout(string $maPhieu, ?string $maLeTan = 'LT001', ?string $maPhong = null): bool
    {
        return $this->checkoutRepo->capNhatCheckout($maPhieu, $maLeTan, $maPhong);
    }

    /**
     * Kiểm tra phòng vật lý có đang ở trạng thái Trống hay không
     */
    public function kiemTraPhongCoTrong(string $maPhong): bool
    {
        return $this->phongVaKhachRepo->kiemTraPhongCoTrong($maPhong);
    }

    /**
     * Cập nhật chuyển phòng cho người lưu trú
     */
    public function capNhatPhongNguoiLuuTru(string $maPhieu, string $maNLT, string $maPhongMoi): bool
    {
        return $this->phongVaKhachRepo->capNhatPhongNguoiLuuTru($maPhieu, $maNLT, $maPhongMoi);
    }

    /**
     * Cập nhật thông tin giấy tờ, họ tên của người lưu trú
     */
    public function capNhatThongTinNLT(string $maPhieu, string $maNLT, array $data): bool
    {
        return $this->phongVaKhachRepo->capNhatThongTinNLT($maPhieu, $maNLT, $data);
    }

    /**
     * Lấy thống kê KPI toàn khách sạn, danh sách các loại phòng và danh sách chi tiết các phòng
     */
    public function getThongKeLoaiPhongVaPhong(?string $maLoaiPhong = null): array
    {
        return $this->phongVaKhachRepo->getThongKeLoaiPhongVaPhong($maLoaiPhong);
    }

    /**
     * Cập nhật chuyển đổi trạng thái phòng giữa 'Trong' và 'BaoTri'
     */
    public function capNhatTrangThaiBaoTri(string $maPhong, string $trangThaiMoi): array
    {
        return $this->phongVaKhachRepo->capNhatTrangThaiBaoTri($maPhong, $trangThaiMoi);
    }

    /**
     * Lấy danh mục dịch vụ khả dụng trong khách sạn
     */
    public function layDanhSachDichVuKhaDung(): array
    {
        return $this->phongVaKhachRepo->layDanhSachDichVuKhaDung();
    }

    /**
     * Đặt dịch vụ nhanh cho phòng đang lưu trú
     */
    public function datDichVuNhanhChoPhong(array $data): array
    {
        return $this->phongVaKhachRepo->datDichVuNhanhChoPhong($data);
    }
}
