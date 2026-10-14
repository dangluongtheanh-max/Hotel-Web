<?php

namespace App\DichVu\Models;

/**
 * Model ChiTietDichVu (Tung mat hang / goi dich vu cu the)
 */
class ChiTietDichVu
{
    private string $MaCTDV;
    private string $MaDV;
    private string $TenCTDV;
    private float $DonGia;
    private int $SoLuongTon;

    public function __construct(
        string $MaCTDV,
        string $MaDV,
        string $TenCTDV,
        float $DonGia,
        int $SoLuongTon = 0
    ) {
        $this->MaCTDV = $MaCTDV;
        $this->MaDV = $MaDV;
        $this->TenCTDV = $TenCTDV;
        $this->DonGia = $DonGia;
        $this->SoLuongTon = $SoLuongTon;
    }

    public function getMaCTDV(): string
    {
        return $this->MaCTDV;
    }

    public function getMaDV(): string
    {
        return $this->MaDV;
    }

    public function getTenCTDV(): string
    {
        return $this->TenCTDV;
    }

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function getSoLuongTon(): int
    {
        return $this->SoLuongTon;
    }

    public function setDonGia(float $DonGia): void
    {
        $this->DonGia = $DonGia;
    }

    public function setSoLuongTon(int $SoLuongTon): void
    {
        $this->SoLuongTon = $SoLuongTon;
    }

    public function toArray(): array
    {
        return [
            'MaCTDV' => $this->MaCTDV,
            'MaDV' => $this->MaDV,
            'TenCTDV' => $this->TenCTDV,
            'DonGia' => $this->DonGia,
            'SoLuongTon' => $this->SoLuongTon,
        ];
    }
}
