<?php

namespace App\HoaDon\Models;

class HoaDon
{
    private string $MaHD;
    private string $MaPhieu;
    private float $TongTienSauVAT;

    public function __construct(
        string $MaHD,
        string $MaPhieu,
        float $TongTienSauVAT
    ) {
        $this->MaHD = $MaHD;
        $this->MaPhieu = $MaPhieu;
        $this->TongTienSauVAT = $TongTienSauVAT;
    }

    public function getMaHD(): string
    {
        return $this->MaHD;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getTongTienSauVAT(): float
    {
        return $this->TongTienSauVAT;
    }

    public function setMaHD(string $MaHD): void
    {
        $this->MaHD = $MaHD;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function setTongTienSauVAT(float $TongTienSauVAT): void
    {
        $this->TongTienSauVAT = $TongTienSauVAT;
    }

    public function toArray(): array
    {
        return [
            'MaHD' => $this->MaHD,
            'MaPhieu' => $this->MaPhieu,
            'TongTienSauVAT' => $this->TongTienSauVAT,
        ];
    }
}
