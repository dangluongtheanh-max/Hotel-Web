<?php

namespace App\HoaDon\Models;

class CTHD
{
    private ?int $MaCTHD;
    private string $MaHD;
    private string $MaPhieu;
    private ?string $MaPhong;
    private ?string $MaCTDV;
    private int $SoDem;
    private int $SoLuong;
    private float $DonGia;

    public function __construct(
        ?int $MaCTHD,
        string $MaHD,
        string $MaPhieu,
        ?string $MaPhong,
        ?string $MaCTDV,
        int $SoDem,
        int $SoLuong,
        float $DonGia
    ) {
        $this->MaCTHD = $MaCTHD;
        $this->MaHD = $MaHD;
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
        $this->MaCTDV = $MaCTDV;
        $this->SoDem = $SoDem;
        $this->SoLuong = $SoLuong;
        $this->DonGia = $DonGia;
    }

    public function getMaCTHD(): ?int
    {
        return $this->MaCTHD;
    }

    public function getMaHD(): string
    {
        return $this->MaHD;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getMaPhong(): ?string
    {
        return $this->MaPhong;
    }

    public function getMaCTDV(): ?string
    {
        return $this->MaCTDV;
    }

    public function getMaDV(): ?string
    {
        return $this->MaCTDV;
    }

    public function getSoDem(): int
    {
        return $this->SoDem;
    }

    public function getSoLuong(): int
    {
        return $this->SoLuong;
    }

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function setMaCTHD(?int $MaCTHD): void
    {
        $this->MaCTHD = $MaCTHD;
    }

    public function setMaHD(string $MaHD): void
    {
        $this->MaHD = $MaHD;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function setMaPhong(?string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setMaCTDV(?string $MaCTDV): void
    {
        $this->MaCTDV = $MaCTDV;
    }

    public function setMaDV(?string $MaDV): void
    {
        $this->MaCTDV = $MaDV;
    }

    public function setSoDem(int $SoDem): void
    {
        $this->SoDem = $SoDem;
    }

    public function setSoLuong(int $SoLuong): void
    {
        $this->SoLuong = $SoLuong;
    }

    public function setDonGia(float $DonGia): void
    {
        $this->DonGia = $DonGia;
    }

    public function toArray(): array
    {
        return [
            'MaCTHD' => $this->MaCTHD,
            'MaHD' => $this->MaHD,
            'MaPhieu' => $this->MaPhieu,
            'MaPhong' => $this->MaPhong,
            'MaCTDV' => $this->MaCTDV,
            'SoDem' => $this->SoDem,
            'SoLuong' => $this->SoLuong,
            'DonGia' => $this->DonGia,
            'ThanhTien' => $this->MaPhong !== null
                ? ($this->SoDem * $this->DonGia)
                : ($this->SoLuong * $this->DonGia),
        ];
    }
}
