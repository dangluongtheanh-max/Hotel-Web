<?php

declare(strict_types=1);

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use PDO;

/**
 * Facade Service cho module ThanhToan.
 * Điều phối 2 sub-services chuyên trách:
 * - ThanhToanGiaoDichService: Xử lý giao dịch VietQR Napas 247 MBBank, tra cứu, hủy và hoàn tiền.
 * - ThanhToanDoiSoatService: Đối soát SePay MBBank API, Webhook Napas 247 và lazy cleanup giải phóng phòng quá hạn 20 phút.
 *
 * Giữ tương thích ngược 100% với Controller, Route và toàn bộ hệ thống.
 */
class ThanhToanService
{
    private ThanhToanGiaoDichService $giaoDichService;
    private ThanhToanDoiSoatService $doiSoatService;

    // Cấu hình tài khoản MBBank chính thức của khách sạn
    public const BANK_ID = ThanhToanGiaoDichService::BANK_ID;
    public const BANK_NAME = ThanhToanGiaoDichService::BANK_NAME;
    public const ACCOUNT_NO = ThanhToanGiaoDichService::ACCOUNT_NO;
    public const ACCOUNT_NAME = ThanhToanGiaoDichService::ACCOUNT_NAME;
    public const QR_EXPIRE_SECONDS = ThanhToanGiaoDichService::QR_EXPIRE_SECONDS;

    public function __construct(
        ?ThanhToanRepository $repo = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanGiaoDichService $giaoDichService = null,
        ?ThanhToanDoiSoatService $doiSoatService = null
    ) {
        $r = $repo ?? new ThanhToanRepository();
        $hdRepo = $hoaDonRepo ?? new HoaDonRepository();

        $this->doiSoatService = $doiSoatService ?? new ThanhToanDoiSoatService($r->getGiaoDichRepo(), $r->getDoiSoatRepo());
        $this->giaoDichService = $giaoDichService ?? new ThanhToanGiaoDichService($r->getGiaoDichRepo(), $hdRepo, $this->doiSoatService);
        $this->giaoDichService->setDoiSoatService($this->doiSoatService);
    }

    public function getGiaoDichService(): ThanhToanGiaoDichService
    {
        return $this->giaoDichService;
    }

    public function getDoiSoatService(): ThanhToanDoiSoatService
    {
        return $this->doiSoatService;
    }

    // =========================================================================
    // NHÓM 1: GIAO DỊCH, TRA CỨU, HỦY & HOÀN TIỀN
    // =========================================================================

    public function taoThanhToan(array $params): array
    {
        return $this->giaoDichService->taoThanhToan($params);
    }

    public function xemThanhToan(array $params): array
    {
        return $this->giaoDichService->xemThanhToan($params);
    }

    public function huyThanhToan(array $params): array
    {
        return $this->giaoDichService->huyThanhToan($params);
    }

    public function yeuCauHoanTien(array $params): array
    {
        return $this->giaoDichService->yeuCauHoanTien($params);
    }

    public function layDanhSachThanhToan(): array
    {
        return $this->giaoDichService->layDanhSachThanhToan();
    }

    // =========================================================================
    // NHÓM 2: ĐỐI SOÁT SEPAY MBBANK, WEBHOOK & TỰ ĐỘNG HỦY QUÁ HẠN 20 PHÚT
    // =========================================================================

    public function dongBoNganHangThat(string $maHD, ?string $customApiKey = null): array
    {
        return $this->doiSoatService->dongBoNganHangThat($maHD, $customApiKey);
    }

    public function xuLyWebhook(array $payload): array
    {
        return $this->doiSoatService->xuLyWebhook($payload);
    }

    public function capNhatHoanTatDatPhong(string $maHD, ?PDO $db = null): void
    {
        $this->doiSoatService->capNhatHoanTatDatPhong($maHD, $db);
    }

    public function xuLyHetHanThanhToan(string $maHD, string $lyDo = 'Quá hạn thanh toán 20 phút', ?PDO $db = null): void
    {
        $this->doiSoatService->xuLyHetHanThanhToan($maHD, $lyDo, $db);
    }

    public function quetVaXuLyTatCaPhieuHetHan(?PDO $db = null): int
    {
        return $this->doiSoatService->quetVaXuLyTatCaPhieuHetHan($db);
    }
}