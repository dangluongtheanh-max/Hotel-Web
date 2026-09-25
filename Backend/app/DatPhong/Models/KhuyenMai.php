<?php

namespace App\DatPhong\Models;

/**
 * Model KhuyenMai
 * Sinh ra tu so do lop (class diagram) - module DatPhong.
 */
class KhuyenMai
{
    /** @var string (PK) */
    private string $MaKM;
    /** @var string */
    private string $TenKM;
    /** @var string */
    private string $LoaiKM;
    /** @var float */
    private float $GiaTriGiam;
    /** @var int */
    private int $SoDemToiThieu;

    public function __construct(
        string $MaKM,
        string $TenKM,
        string $LoaiKM,
        float $GiaTriGiam,
        int $SoDemToiThieu
    ) {
        $this->MaKM = $MaKM;
        $this->TenKM = $TenKM;
        $this->LoaiKM = $LoaiKM;
        $this->GiaTriGiam = $GiaTriGiam;
        $this->SoDemToiThieu = $SoDemToiThieu;
    }

    public function getMaKM(): string
    {
        return $this->MaKM;
    }

    public function getTenKM(): string
    {
        return $this->TenKM;
    }

    public function getLoaiKM(): string
    {
        return $this->LoaiKM;
    }

    public function getGiaTriGiam(): float
    {
        return $this->GiaTriGiam;
    }

    public function getSoDemToiThieu(): int
    {
        return $this->SoDemToiThieu;
    }

    public function setMaKM(string $MaKM): void
    {
        $this->MaKM = $MaKM;
    }

    public function setTenKM(string $TenKM): void
    {
        $this->TenKM = $TenKM;
    }

    public function setLoaiKM(string $LoaiKM): void
    {
        $this->LoaiKM = $LoaiKM;
    }

    public function setGiaTriGiam(float $GiaTriGiam): void
    {
        $this->GiaTriGiam = $GiaTriGiam;
    }

    public function setSoDemToiThieu(int $SoDemToiThieu): void
    {
        $this->SoDemToiThieu = $SoDemToiThieu;
    }

    public function toArray(): array
    {
        return [
            'MaKM' => $this->MaKM,
            'TenKM' => $this->TenKM,
            'LoaiKM' => $this->LoaiKM,
            'GiaTriGiam' => $this->GiaTriGiam,
            'SoDemToiThieu' => $this->SoDemToiThieu,
        ];
    }
}
