<?php

namespace App\DichVu\Models;

/**
 * Model DichVu (Nhom danh muc dich vu cha)
 */
class DichVu
{
    private string $MaDV;
    private string $TenDV;
    private ?string $MoTa;
    private float $DonGia;
    private string $TrangThai;

    public function __construct(
        string $MaDV,
        string $TenDV,
        ?string $MoTa = null,
        float $DonGia = 0.0,
        string $TrangThai = 'DangCungCap'
    ) {
        $this->MaDV = $MaDV;
        $this->TenDV = $TenDV;
        $this->MoTa = $MoTa;
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

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function toArray(): array
    {
        return [
            'MaDV' => $this->MaDV,
            'TenDV' => $this->TenDV,
            'MoTa' => $this->MoTa,
            'DonGia' => $this->DonGia,
            'TrangThai' => $this->TrangThai,
        ];
    }
}
