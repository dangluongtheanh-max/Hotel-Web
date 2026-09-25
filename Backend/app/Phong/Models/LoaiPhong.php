<?php

namespace App\Phong\Models;

/**
 * Model LoaiPhong
 * Sinh ra tu so do lop (class diagram) - module Phong.
 */
class LoaiPhong
{
    /** @var string (PK) */
    private string $MaLoaiPhong;
    /** @var string */
    private string $TenLoaiPhong;
    /** @var float */
    private float $Gia;
    /** @var int */
    private int $SoNguoiToiDa;
    /** @var string */
    private string $MoTa;
    /** @var string */
    private string $TienNghi;

    public function __construct(
        string $MaLoaiPhong,
        string $TenLoaiPhong,
        float $Gia,
        int $SoNguoiToiDa,
        string $MoTa,
        string $TienNghi
    ) {
        $this->MaLoaiPhong = $MaLoaiPhong;
        $this->TenLoaiPhong = $TenLoaiPhong;
        $this->Gia = $Gia;
        $this->SoNguoiToiDa = $SoNguoiToiDa;
        $this->MoTa = $MoTa;
        $this->TienNghi = $TienNghi;
    }

    public function getMaLoaiPhong(): string
    {
        return $this->MaLoaiPhong;
    }

    public function getTenLoaiPhong(): string
    {
        return $this->TenLoaiPhong;
    }

    public function getGia(): float
    {
        return $this->Gia;
    }

    public function getSoNguoiToiDa(): int
    {
        return $this->SoNguoiToiDa;
    }

    public function getMoTa(): string
    {
        return $this->MoTa;
    }

    public function getTienNghi(): string
    {
        return $this->TienNghi;
    }

    public function setMaLoaiPhong(string $MaLoaiPhong): void
    {
        $this->MaLoaiPhong = $MaLoaiPhong;
    }

    public function setTenLoaiPhong(string $TenLoaiPhong): void
    {
        $this->TenLoaiPhong = $TenLoaiPhong;
    }

    public function setGia(float $Gia): void
    {
        $this->Gia = $Gia;
    }

    public function setSoNguoiToiDa(int $SoNguoiToiDa): void
    {
        $this->SoNguoiToiDa = $SoNguoiToiDa;
    }

    public function setMoTa(string $MoTa): void
    {
        $this->MoTa = $MoTa;
    }

    public function setTienNghi(string $TienNghi): void
    {
        $this->TienNghi = $TienNghi;
    }

    public function toArray(): array
    {
        return [
            'MaLoaiPhong' => $this->MaLoaiPhong,
            'TenLoaiPhong' => $this->TenLoaiPhong,
            'Gia' => $this->Gia,
            'SoNguoiToiDa' => $this->SoNguoiToiDa,
            'MoTa' => $this->MoTa,
            'TienNghi' => $this->TienNghi,
        ];
    }
}
