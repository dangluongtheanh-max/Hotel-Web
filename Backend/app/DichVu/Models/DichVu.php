<?php

declare(strict_types=1);

namespace App\DichVu\Models;

class DichVu
{
    private string $MaDV;
    private string $TenDV;
    private ?string $MoTa;
    private ?string $MoTaChiTiet;
    private float $DonGia;
    private string $TrangThai;

    public function __construct(
        string $MaDV = '',
        string $TenDV = '',
        ?string $MoTa = null,
        ?string $MoTaChiTiet = null,
        float $DonGia = 0.0,
        string $TrangThai = 'DangCungCap'
    ) {
        $this->MaDV = $MaDV;
        $this->TenDV = $TenDV;
        $this->MoTa = $MoTa;
        $this->MoTaChiTiet = $MoTaChiTiet;
        $this->DonGia = $DonGia;
        $this->TrangThai = $TrangThai;
    }

    public function getMaDV(): string
    {
        return $this->MaDV;
    }

    public function getTenDV(): string
    {
        return $this->TenDV;
    }

    public function getMoTa(): ?string
    {
        return $this->MoTa;
    }

    public function getMoTaChiTiet(): ?string
    {
        return $this->MoTaChiTiet;
    }

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function setMaDV(string $MaDV): void
    {
        $this->MaDV = $MaDV;
    }

    public function setTenDV(string $TenDV): void
    {
        $this->TenDV = $TenDV;
    }

    public function setMoTa(?string $MoTa): void
    {
        $this->MoTa = $MoTa;
    }

    public function setMoTaChiTiet(?string $MoTaChiTiet): void
    {
        $this->MoTaChiTiet = $MoTaChiTiet;
    }

    public function setDonGia(float $DonGia): void
    {
        $this->DonGia = $DonGia;
    }

    public function setTrangThai(string $TrangThai): void
    {
        $this->TrangThai = $TrangThai;
    }

    public function toArray(): array
    {
        return [
            'MaDV' => $this->MaDV,
            'TenDV' => $this->TenDV,
            'MoTa' => $this->MoTa,
            'MoTaChiTiet' => $this->MoTaChiTiet,
            'DonGia' => $this->DonGia,
            'TrangThai' => $this->TrangThai,
        ];
    }
}
