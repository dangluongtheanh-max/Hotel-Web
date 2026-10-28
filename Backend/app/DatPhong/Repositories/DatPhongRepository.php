<?php

declare(strict_types=1);

namespace App\DatPhong\Repositories;

use App\DatPhong\Models\PhieuNhanPhong;
use App\DichVu\Repositories\DichVuRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\KhuyenMai\Models\KhuyenMai;
use App\KhuyenMai\Repositories\KhuyenMaiRepository;
use App\ThanhToan\Repositories\ThanhToanRepository;
use Database\Database;
use PDO;

/**
 * Facade Repository cho module DatPhong.
 * Điều phối và chuyển tiếp các thao tác sang các Sub-Repositories chuyên biệt:
 * - DatPhongGiaoDichRepository: Ghi dữ liệu đặt phòng & Transaction
 * - DatPhongTraCuuRepository: Truy vấn danh sách & chi tiết phiếu
 * - NguoiLuuTruRepository: Quản lý thông tin người lưu trú
 * Và các repository liên kết (HoaDon, ThanhToan, KhuyenMai, DichVu).
 */
class DatPhongRepository
{
    private ?Database $db;
    private DatPhongGiaoDichRepository $giaoDichRepo;
    private DatPhongTraCuuRepository $traCuuRepo;
    private NguoiLuuTruRepository $nguoiLuuTruRepo;
    private HoaDonRepository $hoaDonRepo;
    private ThanhToanRepository $thanhToanRepo;
    private KhuyenMaiRepository $khuyenMaiRepo;
    private DichVuRepository $dichVuRepo;

    public function __construct(
        ?Database $db = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanRepository $thanhToanRepo = null,
        ?KhuyenMaiRepository $khuyenMaiRepo = null,
        ?DichVuRepository $dichVuRepo = null,
        ?DatPhongGiaoDichRepository $giaoDichRepo = null,
        ?DatPhongTraCuuRepository $traCuuRepo = null,
        ?NguoiLuuTruRepository $nguoiLuuTruRepo = null
    ) {
        $this->db = $db ?? new Database();
        $this->giaoDichRepo = $giaoDichRepo ?? new DatPhongGiaoDichRepository($this->db);
        $this->traCuuRepo = $traCuuRepo ?? new DatPhongTraCuuRepository($this->db);
        $this->nguoiLuuTruRepo = $nguoiLuuTruRepo ?? new NguoiLuuTruRepository($this->db);
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository($this->db);
        $this->thanhToanRepo = $thanhToanRepo ?? new ThanhToanRepository($this->db);
        $this->khuyenMaiRepo = $khuyenMaiRepo ?? new KhuyenMaiRepository();
        $this->dichVuRepo = $dichVuRepo ?? new DichVuRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    // =========================================================================
    // GETTERS CHO CÁC SUB-REPOSITORIES
    // =========================================================================

    public function getGiaoDichRepository(): DatPhongGiaoDichRepository
    {
        return $this->giaoDichRepo;
    }

    public function getTraCuuRepository(): DatPhongTraCuuRepository
    {
        return $this->traCuuRepo;
    }

    public function getNguoiLuuTruRepository(): NguoiLuuTruRepository
    {
        return $this->nguoiLuuTruRepo;
    }

    // =========================================================================
    // TRANSACTION & GHI NHẬN ĐẶT PHÒNG (GiaoDichRepo)
    // =========================================================================

    public function transaction(callable $callback)
    {
        return $this->giaoDichRepo->transaction($callback);
    }

    public function generateMaPhieu(?PDO $db = null): string
    {
        return $this->giaoDichRepo->generateMaPhieu($db);
    }

    public function generateMaNguoiLuuTru(?PDO $db = null): string
    {
        return $this->giaoDichRepo->generateMaNguoiLuuTru($db);
    }

    public function luuPhieuNhanPhong(
        string $maPhieu,
        string $ngayLap,
        string $kenhDat,
        string $trangThai,
        string $maKH,
        ?string $maLeTan,
        ?string $maKM,
        ?string $thoiGianHetHan,
        PDO $db
    ): bool {
        return $this->giaoDichRepo->luuPhieuNhanPhong(
            $maPhieu,
            $ngayLap,
            $kenhDat,
            $trangThai,
            $maKH,
            $maLeTan,
            $maKM,
            $thoiGianHetHan,
            $db
        );
    }

    public function luuChiTietPhieuNhanPhong(
        string $maPhieu,
        string $maPhong,
        string $nhanPhong,
        string $traPhong,
        int $soNguoi,
        string $trangThai,
        PDO $db
    ): bool {
        return $this->giaoDichRepo->luuChiTietPhieuNhanPhong(
            $maPhieu,
            $maPhong,
            $nhanPhong,
            $traPhong,
            $soNguoi,
            $trangThai,
            $db
        );
    }

    public function luuNguoiLuuTru(
        string $maNLT,
        string $hoTen,
        string $ngaySinh,
        string $quocTich,
        string $loaiGiayTo,
        string $soGiayTo,
        ?string $ghiChu,
        string $maPhieu,
        string $maPhong,
        PDO $db
    ): bool {
        return $this->giaoDichRepo->luuNguoiLuuTru(
            $maNLT,
            $hoTen,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $ghiChu,
            $maPhieu,
            $maPhong,
            $db
        );
    }

    public function luuCTSuDungDichVu(
        string $maCTDV,
        string $maPhieu,
        string $maPhong,
        float $donGia,
        string $ghiChu,
        int $soLuong,
        PDO $db
    ): bool {
        return $this->giaoDichRepo->luuCTSuDungDichVu(
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu,
            $soLuong,
            $db
        );
    }

    public function capNhatTrangThaiPhieu(string $maPhieu, string $trangThai, ?string $lyDoHuy = null, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->capNhatTrangThaiPhieu($maPhieu, $trangThai, $lyDoHuy, $db);
    }

    public function capNhatTrangThaiChiTiet(string $maPhieu, string $trangThai, ?string $lyDoHuy = null, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->capNhatTrangThaiChiTiet($maPhieu, $trangThai, $lyDoHuy, $db);
    }

    // =========================================================================
    // TRA CỨU & DANH SÁCH & CHI TIẾT PHIẾU (TraCuuRepo)
    // =========================================================================

    public function findPhieuNhanPhongById(string $id): ?PhieuNhanPhong
    {
        return $this->traCuuRepo->findPhieuNhanPhongById($id);
    }

    public function getDanhSachPhieuCuaKhach(string $maKH, int $limit = 20, int $offset = 0): array
    {
        return $this->traCuuRepo->getDanhSachPhieuCuaKhach($maKH, $limit, $offset);
    }

    public function getChiTietPhieu(string $maPhieu, ?string $maKH = null)
    {
        return $this->traCuuRepo->getChiTietPhieu($maPhieu, $maKH);
    }

    public function getDanhSachPhongCuaPhieu(string $maPhieu, ?PDO $db = null): array
    {
        return $this->traCuuRepo->getDanhSachPhongCuaPhieu($maPhieu, $db);
    }

    // =========================================================================
    // QUẢN LÝ NGƯỜI LƯU TRÚ (NguoiLuuTruRepo)
    // =========================================================================

    public function findNguoiLuuTruById(string $maNLT): ?array
    {
        return $this->nguoiLuuTruRepo->findNguoiLuuTruById($maNLT);
    }

    public function capNhatNguoiLuuTru(
        string $maNLT,
        string $hoTen,
        string $ngaySinh,
        string $quocTich,
        string $loaiGiayTo,
        string $soGiayTo,
        ?string $ghiChu = null
    ): bool {
        return $this->nguoiLuuTruRepo->capNhatNguoiLuuTru(
            $maNLT,
            $hoTen,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $ghiChu
        );
    }

    // =========================================================================
    // ỦY QUYỀN SANG CÁC REPOSITORY KHÁC (Tương thích ngược)
    // =========================================================================

    public function findKhuyenMaiById(string $id): ?KhuyenMai
    {
        return $this->khuyenMaiRepo->findKhuyenMaiById($id);
    }

    public function getAllDichVu(): array
    {
        return $this->dichVuRepo->getAllDichVu();
    }

    public function generateMaHoaDon(?PDO $db = null): string
    {
        return $this->hoaDonRepo->generateMaHoaDon($db);
    }

    public function luuHoaDon(string $maHD, string $maPhieu, float $tongTienSauVAT, ?string $maKM, PDO $db): bool
    {
        return $this->hoaDonRepo->luuHoaDon($maHD, $maPhieu, $tongTienSauVAT, $maKM, $db);
    }

    public function luuCTHD(string $maHD, string $maPhieu, ?string $maPhong, ?string $maCTDV, int $soDem, int $soLuong, float $donGia, PDO $db): bool
    {
        return $this->hoaDonRepo->luuCTHD($maHD, $maPhieu, $maPhong, $maCTDV, $soDem, $soLuong, $donGia, $db);
    }

    public function generateMaThanhToan(?PDO $db = null): string
    {
        return $this->thanhToanRepo->generateMaThanhToan($db);
    }

    public function luuThanhToan(string $maTT, float $soTien, string $thoiGian, string $hinhThuc, string $trangThai, string $maHD, PDO $db): bool
    {
        return $this->thanhToanRepo->luuThanhToan($maTT, $soTien, $thoiGian, $hinhThuc, $trangThai, $maHD, $db);
    }
}
