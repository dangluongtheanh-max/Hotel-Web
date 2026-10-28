<?php

declare(strict_types=1);

namespace App\ThanhToan\Repositories;

use Database\Database;
use PDO;

/**
 * Facade Repository cho module ThanhToan.
 * Điều phối các sub-repositories chuyên trách:
 * - ThanhToanGiaoDichRepository: Thao tác bảng ThanhToan, sinh mã, hoàn tiền, danh sách giao dịch.
 * - ThanhToanDoiSoatRepository: Đồng bộ trạng thái phiếu, chi tiết phiếu và phòng khi thanh toán/quá hạn.
 *
 * Giữ tương thích ngược 100% với toàn bộ hệ thống.
 */
class ThanhToanRepository
{
    private ?Database $db;
    private ThanhToanGiaoDichRepository $giaoDichRepo;
    private ThanhToanDoiSoatRepository $doiSoatRepo;

    public function __construct(
        ?Database $db = null,
        ?ThanhToanGiaoDichRepository $giaoDichRepo = null,
        ?ThanhToanDoiSoatRepository $doiSoatRepo = null
    ) {
        $this->db = $db ?? new Database();
        $this->giaoDichRepo = $giaoDichRepo ?? new ThanhToanGiaoDichRepository($this->db);
        $this->doiSoatRepo = $doiSoatRepo ?? new ThanhToanDoiSoatRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function getGiaoDichRepo(): ThanhToanGiaoDichRepository
    {
        return $this->giaoDichRepo;
    }

    public function getDoiSoatRepo(): ThanhToanDoiSoatRepository
    {
        return $this->doiSoatRepo;
    }

    /**
     * Sinh mã thanh toán tự động theo định dạng TT001, TT002,...
     */
    public function generateMaThanhToan(?PDO $db = null): string
    {
        return $this->giaoDichRepo->generateMaThanhToan($db);
    }

    /**
     * Lưu bản ghi thanh toán vào bảng ThanhToan
     */
    public function luuThanhToan(
        string $maTT,
        float $soTien,
        string $thoiGian,
        string $hinhThuc,
        string $trangThai,
        string $maHD,
        PDO $db
    ): bool {
        return $this->giaoDichRepo->luuThanhToan($maTT, $soTien, $thoiGian, $hinhThuc, $trangThai, $maHD, $db);
    }

    public function findThanhToanById(string $id, ?PDO $db = null): ?array
    {
        return $this->giaoDichRepo->findThanhToanById($id, $db);
    }

    public function findThanhToanByMaHD(string $maHD, ?PDO $db = null): array
    {
        return $this->giaoDichRepo->findThanhToanByMaHD($maHD, $db);
    }

    public function capNhatTrangThai(string $maTT, string $trangThai, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->capNhatTrangThai($maTT, $trangThai, $db);
    }

    public function getAll(?PDO $db = null): array
    {
        return $this->giaoDichRepo->getAll($db);
    }

    public function saveRefund(string $maTT, array $refundData, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->saveRefund($maTT, $refundData, $db);
    }

    // Các hàm tương thích
    public function findByMaHD(string $maHD, ?PDO $db = null): ?array
    {
        return $this->giaoDichRepo->findByMaHD($maHD, $db);
    }

    public function findById(string $id, ?PDO $db = null): ?array
    {
        return $this->giaoDichRepo->findById($id, $db);
    }

    public function updateStatus(string $maTT, string $trangThai, ?string $time = null, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->updateStatus($maTT, $trangThai, $time, $db);
    }

    public function generateNextMaTT(?PDO $db = null): string
    {
        return $this->giaoDichRepo->generateNextMaTT($db);
    }

    public function create(array $data, ?PDO $db = null): bool
    {
        return $this->giaoDichRepo->create($data, $db);
    }

    // Delegate phương thức đối soát
    public function capNhatHoanTatDatPhong(string $maHD, ?PDO $db = null): void
    {
        $this->doiSoatRepo->capNhatHoanTatDatPhong($maHD, $db);
    }

    public function xuLyHetHanThanhToan(string $maHD, string $lyDo = 'Quá hạn thanh toán 20 phút', ?PDO $db = null): void
    {
        $this->doiSoatRepo->xuLyHetHanThanhToan($maHD, $lyDo, $db);
    }

    public function quetDanhSachPhieuQuaHan(?PDO $db = null): array
    {
        return $this->doiSoatRepo->quetDanhSachPhieuQuaHan($db);
    }
}
