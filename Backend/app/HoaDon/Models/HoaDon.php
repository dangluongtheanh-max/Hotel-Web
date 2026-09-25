<?php

namespace App\HoaDon\Models;

/**
 * Model HoaDon
 * Sinh ra tu so do lop (class diagram) - module HoaDon.
 */
class HoaDon
{
    /** @var string (PK) */
    private string $MaHD;
    /** @var string (FK) */
    private string $MaKH;
    /** @var float */
    private float $TongTienSauVAT;

    public function __construct(
        string $MaHD,
        string $MaKH,
        float $TongTienSauVAT
    ) {
        $this->MaHD = $MaHD;
        $this->MaKH = $MaKH;
        $this->TongTienSauVAT = $TongTienSauVAT;
    }

    public function getMaHD(): string
    {
        return $this->MaHD;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
    }

    public function getTongTienSauVAT(): float
    {
        return $this->TongTienSauVAT;
    }

    public function setMaHD(string $MaHD): void
    {
        $this->MaHD = $MaHD;
    }

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
    }

    public function setTongTienSauVAT(float $TongTienSauVAT): void
    {
        $this->TongTienSauVAT = $TongTienSauVAT;
    }

    public function toArray(): array
    {
        return [
            'MaHD' => $this->MaHD,
            'MaKH' => $this->MaKH,
            'TongTienSauVAT' => $this->TongTienSauVAT,
        ];
    }
}
