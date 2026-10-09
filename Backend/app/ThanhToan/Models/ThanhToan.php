<?php

namespace App\ThanhToan\Models;

/**
 * Model biểu diễn giao dịch thanh toán và thông tin hoàn tiền.
 */
class ThanhToan
{
    private string $maThanhToan;
    private float $soTien;
    private string $thoiGianThanhToan;
    private string $hinhThuc;
    private string $trangThaiThanhToan;
    private string $maHD;
    private float $soTienHoan;
    private ?string $nganHangHoan;
    private ?string $stkHoan;
    private ?string $tenChuTKHoan;
    private ?string $lyDoHoan;
    private ?string $thoiGianHoan;

    public function __construct(
        string $maThanhToan = '',
        float $soTien = 0.0,
        string $thoiGianThanhToan = '',
        string $hinhThuc = 'ChuyenKhoan',
        string $trangThaiThanhToan = 'ChoThanhToan',
        string $maHD = '',
        float $soTienHoan = 0.0,
        ?string $nganHangHoan = null,
        ?string $stkHoan = null,
        ?string $tenChuTKHoan = null,
        ?string $lyDoHoan = null,
        ?string $thoiGianHoan = null
    ) {
        $this->maThanhToan = $maThanhToan;
        $this->soTien = $soTien;
        $this->thoiGianThanhToan = $thoiGianThanhToan ?: date('Y-m-d H:i:s');
        $this->hinhThuc = $hinhThuc;
        $this->trangThaiThanhToan = $trangThaiThanhToan;
        $this->maHD = $maHD;
        $this->soTienHoan = $soTienHoan;
        $this->nganHangHoan = $nganHangHoan;
        $this->stkHoan = $stkHoan;
        $this->tenChuTKHoan = $tenChuTKHoan;
        $this->lyDoHoan = $lyDoHoan;
        $this->thoiGianHoan = $thoiGianHoan;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['MaThanhToan'] ?? '',
            (float)($data['SoTien'] ?? 0),
            $data['ThoiGianThanhToan'] ?? '',
            $data['HinhThuc'] ?? 'ChuyenKhoan',
            $data['TrangThaiThanhToan'] ?? 'ChoThanhToan',
            $data['MaHD'] ?? '',
            (float)($data['SoTienHoan'] ?? 0),
            $data['NganHangHoan'] ?? null,
            $data['STKHoan'] ?? null,
            $data['TenChuTKHoan'] ?? null,
            $data['LyDoHoan'] ?? null,
            $data['ThoiGianHoan'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'MaThanhToan' => $this->maThanhToan,
            'SoTien' => $this->soTien,
            'ThoiGianThanhToan' => $this->thoiGianThanhToan,
            'HinhThuc' => $this->hinhThuc,
            'TrangThaiThanhToan' => $this->trangThaiThanhToan,
            'MaHD' => $this->maHD,
            'SoTienHoan' => $this->soTienHoan,
            'NganHangHoan' => $this->nganHangHoan,
            'STKHoan' => $this->stkHoan,
            'TenChuTKHoan' => $this->tenChuTKHoan,
            'LyDoHoan' => $this->lyDoHoan,
            'ThoiGianHoan' => $this->thoiGianHoan,
        ];
    }

    public function getMaThanhToan(): string { return $this->maThanhToan; }
    public function getSoTien(): float { return $this->soTien; }
    public function getThoiGianThanhToan(): string { return $this->thoiGianThanhToan; }
    public function getHinhThuc(): string { return $this->hinhThuc; }
    public function getTrangThaiThanhToan(): string { return $this->trangThaiThanhToan; }
    public function getMaHD(): string { return $this->maHD; }
}
