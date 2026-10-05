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
    public function __construct(
        string $MaPhong,
        string $TrangThaiPhong,
        string $MaLoaiPhong
    ) {
        $this->MaPhong = $MaPhong;
        $this->TrangThaiPhong = $TrangThaiPhong;
        $this->MaLoaiPhong = $MaLoaiPhong;
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

    public function toArray(): array
    {
        return [
            'MaPhong' => $this->MaPhong,
            'TrangThaiPhong' => $this->TrangThaiPhong,
            'MaLoaiPhong' => $this->MaLoaiPhong,
        ];
    }
}
