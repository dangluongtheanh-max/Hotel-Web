<?php

namespace App\Phong\Models;

/**
 * Model Phong
 * Sinh ra tu so do lop (class diagram) - module Phong.
 */
class Phong
{
    /** @var string (PK) */
    private string $MaPhong;
    /** @var string */
    private string $TrangThaiPhong;
    /** @var string (FK) */
    private string $MaLoaiPhong;
    /** @var string (PK/FK) */
    private string $MaChiNhanh;

    public function __construct(
        string $MaPhong,
        string $TrangThaiPhong,
        string $MaLoaiPhong,
        string $MaChiNhanh
    ) {
        $this->MaPhong = $MaPhong;
        $this->TrangThaiPhong = $TrangThaiPhong;
        $this->MaLoaiPhong = $MaLoaiPhong;
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getTrangThaiPhong(): string
    {
        return $this->TrangThaiPhong;
    }

    public function getMaLoaiPhong(): string
    {
        return $this->MaLoaiPhong;
    }

    public function getMaChiNhanh(): string
    {
        return $this->MaChiNhanh;
    }

    public function setMaPhong(string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setTrangThaiPhong(string $TrangThaiPhong): void
    {
        $this->TrangThaiPhong = $TrangThaiPhong;
    }

    public function setMaLoaiPhong(string $MaLoaiPhong): void
    {
        $this->MaLoaiPhong = $MaLoaiPhong;
    }

    public function setMaChiNhanh(string $MaChiNhanh): void
    {
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function toArray(): array
    {
        return [
            'MaPhong' => $this->MaPhong,
            'TrangThaiPhong' => $this->TrangThaiPhong,
            'MaLoaiPhong' => $this->MaLoaiPhong,
            'MaChiNhanh' => $this->MaChiNhanh,
        ];
    }
}
