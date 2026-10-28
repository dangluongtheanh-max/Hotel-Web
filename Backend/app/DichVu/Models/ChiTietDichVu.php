<?php

declare(strict_types=1);

namespace App\DichVu\Models;

class ChiTietDichVu
{
    private string $MaCTDV;
    private string $MaDV;
    private string $TenCTDV;
    private ?string $MoTa;
    private ?string $MoTaChiTiet;
    private float $DonGia;
    private int $SoLuongTon;
    private string $TrangThai;

    public function __construct(
        string $MaCTDV = '',
        string $MaDV = '',
        string $TenCTDV = '',
        ?string $MoTa = null,
        ?string $MoTaChiTiet = null,
        float $DonGia = 0.0,
        int $SoLuongTon = 0,
        string $TrangThai = 'DangCungCap'
    ) {
        $this->MaCTDV = $MaCTDV;
        $this->MaDV = $MaDV;
        $this->TenCTDV = $TenCTDV;
        $this->MoTa = $MoTa;
        $this->MoTaChiTiet = $MoTaChiTiet;
        $this->DonGia = $DonGia;
        $this->SoLuongTon = $SoLuongTon;
        $this->TrangThai = $TrangThai;
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

    public function getSoLuongTon(): int
    {
        return $this->SoLuongTon;
    }

    public function setMaCTDV(string $MaCTDV): void
    {
        $this->MaCTDV = $MaCTDV;
    }

    public function setMaDV(string $MaDV): void
    {
        $this->MaDV = $MaDV;
    }

    public function setTenCTDV(string $TenCTDV): void
    {
        $this->TenCTDV = $TenCTDV;
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

    public function setSoLuongTon(int $SoLuongTon): void
    {
        $this->SoLuongTon = $SoLuongTon;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function setTrangThai(string $TrangThai): void
    {
        $this->TrangThai = $TrangThai;
    }

    public function toArray(): array
    {
        return [
            'MaCTDV' => $this->MaCTDV,
            'MaDV' => $this->MaDV,
            'TenCTDV' => $this->TenCTDV,
            'MoTa' => $this->MoTa,
            'MoTaChiTiet' => $this->MoTaChiTiet,
            'DonGia' => $this->DonGia,
            'SoLuongTon' => $this->SoLuongTon,
            'TrangThai' => $this->TrangThai,
        ];
    }
}
