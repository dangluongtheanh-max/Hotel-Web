<?php

declare(strict_types=1);

namespace App\DatPhong\Models;

/**
 * Model ChiTietPhieuNhanPhong
 * Dai dien cho 1 phong vat ly duoc dat trong PhieuNhanPhong.
 */
class ChiTietPhieuNhanPhong
{
    private string $MaPhieu;
    private string $MaPhong;
    private \DateTimeInterface $ThoiGianNhanPhong;
    private \DateTimeInterface $ThoiGianTraPhong;
    private int $SoNguoi;
    private string $TrangThai;
    private ?string $MaLeTanCheckin = null;
    private ?string $MaLeTanCheckout = null;
    private ?string $MaLeTanHuy = null;

    public function __construct(
        string $MaPhieu,
        string $MaPhong,
        \DateTimeInterface $ThoiGianNhanPhong,
        \DateTimeInterface $ThoiGianTraPhong,
        int $SoNguoi,
        string $TrangThai = 'DaDatPhong',
        ?string $MaLeTanCheckin = null,
        ?string $MaLeTanCheckout = null,
        ?string $MaLeTanHuy = null
    ) {
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
        $this->ThoiGianNhanPhong = $ThoiGianNhanPhong;
        $this->ThoiGianTraPhong = $ThoiGianTraPhong;
        $this->SoNguoi = $SoNguoi;
        $this->TrangThai = $TrangThai;
        $this->MaLeTanCheckin = $MaLeTanCheckin;
        $this->MaLeTanCheckout = $MaLeTanCheckout;
        $this->MaLeTanHuy = $MaLeTanHuy;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getThoiGianNhanPhong(): \DateTimeInterface
    {
        return $this->ThoiGianNhanPhong;
    }

    public function getThoiGianTraPhong(): \DateTimeInterface
    {
        return $this->ThoiGianTraPhong;
    }

    public function getSoNguoi(): int
    {
        return $this->SoNguoi;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function getMaLeTanCheckin(): ?string
    {
        return $this->MaLeTanCheckin;
    }

    public function getMaLeTanCheckout(): ?string
    {
        return $this->MaLeTanCheckout;
    }

    public function getMaLeTanHuy(): ?string
    {
        return $this->MaLeTanHuy;
    }

    public function toArray(): array
    {
        return [
            'MaPhieu' => $this->MaPhieu,
            'MaPhong' => $this->MaPhong,
            'ThoiGianNhanPhong' => $this->ThoiGianNhanPhong->format('Y-m-d H:i:s'),
            'ThoiGianTraPhong' => $this->ThoiGianTraPhong->format('Y-m-d H:i:s'),
            'SoNguoi' => $this->SoNguoi,
            'TrangThai' => $this->TrangThai,
            'MaLeTanCheckin' => $this->MaLeTanCheckin,
            'MaLeTanCheckout' => $this->MaLeTanCheckout,
            'MaLeTanHuy' => $this->MaLeTanHuy,
        ];
    }
}
