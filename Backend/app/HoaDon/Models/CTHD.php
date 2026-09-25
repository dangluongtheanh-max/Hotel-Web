<?php

namespace App\HoaDon\Models;

/**
 * Model CTHD
 * Sinh ra tu so do lop (class diagram) - module HoaDon.
 */
class CTHD
{
    /** @var int (PK) */
    private int $MaCTHD;
    /** @var string (FK) */
    private string $MaHD;
    /** @var string (FK) */
    private string $MaChiNhanh;
    /** @var string (FK) */
    private string $MaPhong;
    /** @var int */
    private int $SoDem;
    /** @var float */
    private float $DonGia;

    public function __construct(
        int $MaCTHD,
        string $MaHD,
        string $MaChiNhanh,
        string $MaPhong,
        int $SoDem,
        float $DonGia
    ) {
        $this->MaCTHD = $MaCTHD;
        $this->MaHD = $MaHD;
        $this->MaChiNhanh = $MaChiNhanh;
        $this->MaPhong = $MaPhong;
        $this->SoDem = $SoDem;
        $this->DonGia = $DonGia;
    }

    public function getMaCTHD(): int
    {
        return $this->MaCTHD;
    }

    public function getMaHD(): string
    {
        return $this->MaHD;
    }

    public function getMaChiNhanh(): string
    {
        return $this->MaChiNhanh;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getSoDem(): int
    {
        return $this->SoDem;
    }

    public function getDonGia(): float
    {
        return $this->DonGia;
    }

    public function setMaCTHD(int $MaCTHD): void
    {
        $this->MaCTHD = $MaCTHD;
    }

    public function setMaHD(string $MaHD): void
    {
        $this->MaHD = $MaHD;
    }

    public function setMaChiNhanh(string $MaChiNhanh): void
    {
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function setMaPhong(string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setSoDem(int $SoDem): void
    {
        $this->SoDem = $SoDem;
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
            'MaChiNhanh' => $this->MaChiNhanh,
            'MaPhong' => $this->MaPhong,
            'SoDem' => $this->SoDem,
            'DonGia' => $this->DonGia,
        ];
    }
}
