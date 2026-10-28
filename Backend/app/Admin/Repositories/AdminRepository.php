<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use App\Admin\Models\Admin;
use Database\Database;
use PDO;

/**
 * Facade Repository cho module Admin.
 * Điều phối toàn bộ các câu truy vấn cơ sở dữ liệu qua các Sub-Repository chuyên biệt:
 * - AdminThongKeRepository: Báo cáo, Thống kê doanh thu, công suất phòng, đánh giá, KPI
 * - AdminNhanVienRepository: Nhân sự, Lễ tân, Tài khoản
 * - AdminPhongRepository: Phòng vật lý, Loại phòng
 * - AdminDichVuRepository: Bảng giá, Chi tiết dịch vụ
 * - AdminKhuyenMaiRepository: Chương trình khuyến mãi
 * - AdminTraCuuRepository: Khách hàng, Phiếu nhận phòng, Hóa đơn
 */
class AdminRepository
{
    private PDO $db;
    private AdminThongKeRepository $thongKeRepo;
    private AdminNhanVienRepository $nhanVienRepo;
    private AdminPhongRepository $phongRepo;
    private AdminDichVuRepository $dichVuRepo;
    private AdminKhuyenMaiRepository $khuyenMaiRepo;
    private AdminTraCuuRepository $traCuuRepo;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->thongKeRepo = new AdminThongKeRepository($this->db);
        $this->nhanVienRepo = new AdminNhanVienRepository($this->db);
        $this->phongRepo = new AdminPhongRepository($this->db);
        $this->dichVuRepo = new AdminDichVuRepository($this->db);
        $this->khuyenMaiRepo = new AdminKhuyenMaiRepository($this->db);
        $this->traCuuRepo = new AdminTraCuuRepository($this->db);
    }

    public function getThongKeRepo(): AdminThongKeRepository { return $this->thongKeRepo; }
    public function getNhanVienRepo(): AdminNhanVienRepository { return $this->nhanVienRepo; }
    public function getPhongRepo(): AdminPhongRepository { return $this->phongRepo; }
    public function getDichVuRepo(): AdminDichVuRepository { return $this->dichVuRepo; }
    public function getKhuyenMaiRepo(): AdminKhuyenMaiRepository { return $this->khuyenMaiRepo; }
    public function getTraCuuRepo(): AdminTraCuuRepository { return $this->traCuuRepo; }

    // --- Delegate Thống Kê ---
    public function thongKeDoanhThu(array $filters = []): array { return $this->thongKeRepo->thongKeDoanhThu($filters); }
    public function thongKeDoanhThuLoaiPhong(array $filters = []): array { return $this->thongKeRepo->thongKeDoanhThuLoaiPhong($filters); }
    public function thongKeDoanhThuDichVu(array $filters = []): array { return $this->thongKeRepo->thongKeDoanhThuDichVu($filters); }
    public function thongKeCongSuatPhong(array $filters = []): array { return $this->thongKeRepo->thongKeCongSuatPhong($filters); }
    public function thongKeDanhGiaTheoLoaiPhong(array $filters = []): array { return $this->thongKeRepo->thongKeDanhGiaTheoLoaiPhong($filters); }
    public function thongKeDanhGiaTheoPhong(array $filters = []): array { return $this->thongKeRepo->thongKeDanhGiaTheoPhong($filters); }
    public function danhSachChiTietDanhGia(array $filters = []): array { return $this->thongKeRepo->danhSachChiTietDanhGia($filters); }
    public function thongKeSoLuongPhong(): array { return $this->thongKeRepo->thongKeSoLuongPhong(); }
    public function thongKeSoLuongPhieu(): array { return $this->thongKeRepo->thongKeSoLuongPhieu(); }
    public function thongKeDichVu(): array { return $this->thongKeRepo->thongKeDichVu(); }
    public function thongKeTongQuan(): array { return $this->thongKeRepo->thongKeTongQuan(); }
    public function thongKeSoLuongNhanVien(): array { return $this->thongKeRepo->thongKeSoLuongNhanVien(); }

    // --- Delegate Nhân Viên & Lễ Tân ---
    public function getAllNhanVien(array $filters = []): array { return $this->nhanVienRepo->getAllNhanVien($filters); }
    public function findNhanVienById(string $id): ?array { return $this->nhanVienRepo->findNhanVienById($id); }
    public function updateTrangThaiTaiKhoan(string $maTK, string $trangThai): bool { return $this->nhanVienRepo->updateTrangThaiTaiKhoan($maTK, $trangThai); }
    public function getAllLeTan(array $filters = []): array { return $this->nhanVienRepo->getAllLeTan($filters); }
    public function findLeTanById(string $id): ?array { return $this->nhanVienRepo->findLeTanById($id); }
    public function leTanAccountValueExists(string $column, string $value): bool { return $this->nhanVienRepo->leTanAccountValueExists($column, $value); }
    public function generateMaLeTan(): string { return $this->nhanVienRepo->generateMaLeTan(); }
    public function generateMaTaiKhoan(): string { return $this->nhanVienRepo->generateMaTaiKhoan(); }
    public function createLeTan(array $data): array { return $this->nhanVienRepo->createLeTan($data); }
    public function updateLeTan(string $id, array $data): bool { return $this->nhanVienRepo->updateLeTan($id, $data); }
    public function toggleTrangThaiLeTan(string $maLeTan): array { return $this->nhanVienRepo->toggleTrangThaiLeTan($maLeTan); }

    // --- Delegate Tra Cứu Vận Hành ---
    public function getAllKhachHang(array $filters = []): array { return $this->traCuuRepo->getAllKhachHang($filters); }
    public function getAllPhieuNhanPhong(array $filters = []): array { return $this->traCuuRepo->getAllPhieuNhanPhong($filters); }
    public function getAllHoaDon(array $filters = []): array { return $this->traCuuRepo->getAllHoaDon($filters); }

    // --- Delegate Dịch Vụ ---
    public function getAllDichVu(array $filters = []): array { return $this->dichVuRepo->getAllDichVu($filters); }
    public function findChiTietDichVuById(string $maCTDV): ?array { return $this->dichVuRepo->findChiTietDichVuById($maCTDV); }
    public function generateMaChiTietDichVu(string $maDV): string { return $this->dichVuRepo->generateMaChiTietDichVu($maDV); }
    public function createChiTietDichVu(array $data): array { return $this->dichVuRepo->createChiTietDichVu($data); }
    public function updateChiTietDichVu(string $maCTDV, array $data): bool { return $this->dichVuRepo->updateChiTietDichVu($maCTDV, $data); }
    public function toggleTrangThaiChiTietDichVu(string $maCTDV): array { return $this->dichVuRepo->toggleTrangThaiChiTietDichVu($maCTDV); }

    // --- Delegate Phòng & Loại Phòng ---
    public function getPhongTheoLoai(array $filters = []): array { return $this->phongRepo->getPhongTheoLoai($filters); }
    public function generateMaLoaiPhong(): string { return $this->phongRepo->generateMaLoaiPhong(); }
    public function findLoaiPhongById(string $maLoaiPhong): ?array { return $this->phongRepo->findLoaiPhongById($maLoaiPhong); }
    public function createLoaiPhong(array $data): array { return $this->phongRepo->createLoaiPhong($data); }
    public function updateLoaiPhong(string $maLoaiPhong, array $data): bool { return $this->phongRepo->updateLoaiPhong($maLoaiPhong, $data); }
    public function toggleTrangThaiLoaiPhong(string $maLoaiPhong): array { return $this->phongRepo->toggleTrangThaiLoaiPhong($maLoaiPhong); }
    public function findPhongById(string $maPhong): ?array { return $this->phongRepo->findPhongById($maPhong); }
    public function createPhong(array $data): array { return $this->phongRepo->createPhong($data); }
    public function updatePhong(string $maPhong, array $data): bool { return $this->phongRepo->updatePhong($maPhong, $data); }
    public function toggleTrangThaiPhong(string $maPhong): array { return $this->phongRepo->toggleTrangThaiPhong($maPhong); }

    // --- Delegate Khuyến Mãi ---
    public function getAllKhuyenMai(array $filters = []): array { return $this->khuyenMaiRepo->getAllKhuyenMai($filters); }
    public function generateMaKhuyenMai(): string { return $this->khuyenMaiRepo->generateMaKhuyenMai(); }
    public function findKhuyenMaiById(string $maKM): ?array { return $this->khuyenMaiRepo->findKhuyenMaiById($maKM); }
    public function createKhuyenMai(array $data): array { return $this->khuyenMaiRepo->createKhuyenMai($data); }
    public function updateKhuyenMai(string $maKM, array $data): bool { return $this->khuyenMaiRepo->updateKhuyenMai($maKM, $data); }
    public function toggleTrangThaiKhuyenMai(string $maKM): array { return $this->khuyenMaiRepo->toggleTrangThaiKhuyenMai($maKM); }

    // --- Admin Entity Methods ---
    public function findAdminById(string $id): ?Admin
    {
        $stmt = $this->db->prepare("SELECT * FROM Admin WHERE MaAdmin = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return new Admin($row['MaAdmin'], $row['HoTen'], $row['SDT'], $row['Email'], $row['MaTK']);
    }

    public function getAllAdmin(array $filters = []): array
    {
        $stmt = $this->db->query("SELECT * FROM Admin");
        $list = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $list[] = (new Admin($row['MaAdmin'], $row['HoTen'], $row['SDT'], $row['Email'], $row['MaTK']))->toArray();
        }
        return $list;
    }

    public function saveAdmin(Admin $admin): bool
    {
        $data = $admin->toArray();
        $stmt = $this->db->prepare("REPLACE INTO Admin (MaAdmin, HoTen, SDT, Email, MaTK) VALUES (:MaAdmin, :HoTen, :SDT, :Email, :MaTK)");
        return $stmt->execute($data);
    }

    public function deleteAdmin(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Admin WHERE MaAdmin = :id");
        return $stmt->execute([':id' => $id]);
    }
}