<?php

declare(strict_types=1);

namespace App\DichVu\Models;

class CTSuDungDichVu
{
    private int $MaCTSDDV;
    private string $MaCTDV;
    private string $MaPhieu;
    private string $MaPhong;
    private float $DonGia;
    private ?string $GhiChu;
    private int $SoLuong;
    private ?string $ThoiGianNhan;
    private ?string $ThoiGianTra;

    public function __construct(
        int $MaCTSDDV = 0,
        string $MaCTDV = '',
        string $MaPhieu = '',
        string $MaPhong = '',
        float $DonGia = 0.0,
        ?string $GhiChu = null,
        int $SoLuong = 1,
        ?string $ThoiGianNhan = null,
        ?string $ThoiGianTra = null
    ) {
        $this->MaCTSDDV = $MaCTSDDV;
        $this->MaCTDV = $MaCTDV;
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
        $this->DonGia = $DonGia;
        $this->GhiChu = $GhiChu;
        $this->SoLuong = $SoLuong;
        $this->ThoiGianNhan = $ThoiGianNhan;
        $this->ThoiGianTra = $ThoiGianTra;
    }

    public function getMaCTSDDV(): int
    {
        return $this->MaCTSDDV;
    }

    public function getMaCTDV(): string
    {
        return $this->MaCTDV;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function getGhiChu(): ?string
    {
        return $this->GhiChu;
    }

    public function getSoLuong(): int
    {
        return $this->SoLuong;
    }

    public function getThoiGianNhan(): ?string
    {
        return $this->ThoiGianNhan;
    }

    public function getThoiGianTra(): ?string
    {
        return $this->ThoiGianTra;
    }

    public function setMaCTSDDV(int $MaCTSDDV): void
    {
        $this->MaCTSDDV = $MaCTSDDV;
    }

    public function setMaCTDV(string $MaCTDV): void
    {
        $this->MaCTDV = $MaCTDV;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function setMaPhong(string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setDonGia(float $DonGia): void
    {
        $this->DonGia = $DonGia;
    }

    public function setGhiChu(?string $GhiChu): void
    {
        $this->GhiChu = $GhiChu;
    }

    public function setSoLuong(int $SoLuong): void
    {
        $this->SoLuong = $SoLuong;
    }

    public function setThoiGianNhan(?string $ThoiGianNhan): void
    {
        $this->ThoiGianNhan = $ThoiGianNhan;
    }

    public function setThoiGianTra(?string $ThoiGianTra): void
    {
        $this->ThoiGianTra = $ThoiGianTra;
    }

    public function toArray(): array
    {
        return [
            'MaCTSDDV' => $this->MaCTSDDV,
            'MaCTDV' => $this->MaCTDV,
            'MaPhieu' => $this->MaPhieu,
            'MaPhong' => $this->MaPhong,
            'DonGia' => $this->DonGia,
            'GhiChu' => $this->GhiChu,
            'SoLuong' => $this->SoLuong,
            'ThoiGianNhan' => $this->ThoiGianNhan,
            'ThoiGianTra' => $this->ThoiGianTra,
        ];
    }
}
