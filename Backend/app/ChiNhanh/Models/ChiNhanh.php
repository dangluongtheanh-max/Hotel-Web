<?php

namespace App\ChiNhanh\Models;

/**
 * Model ChiNhanh
 * Sinh ra tu so do lop (class diagram) - module ChiNhanh.
 */
class ChiNhanh
{
    /** @var string (PK) */
    private string $MaChiNhanh;
    /** @var string */
    private string $TenChiNhanh;
    /** @var string */
    private string $KhuVuc;
    /** @var string */
    private string $DiaChi;
    /** @var string (FK) */
    private string $MaAdmin;

    public function __construct(
        string $MaChiNhanh,
        string $TenChiNhanh,
        string $KhuVuc,
        string $DiaChi,
        string $MaAdmin
    ) {
        $this->MaChiNhanh = $MaChiNhanh;
        $this->TenChiNhanh = $TenChiNhanh;
        $this->KhuVuc = $KhuVuc;
        $this->DiaChi = $DiaChi;
        $this->MaAdmin = $MaAdmin;
    }

    public function getMaChiNhanh(): string
    {
        return $this->MaChiNhanh;
    }

    public function getTenChiNhanh(): string
    {
        return $this->TenChiNhanh;
    }

    public function getKhuVuc(): string
    {
        return $this->KhuVuc;
    }

    public function getDiaChi(): string
    {
        return $this->DiaChi;
    }

    public function getMaAdmin(): string
    {
        return $this->MaAdmin;
    }

    public function setMaChiNhanh(string $MaChiNhanh): void
    {
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function setTenChiNhanh(string $TenChiNhanh): void
    {
        $this->TenChiNhanh = $TenChiNhanh;
    }

    public function setKhuVuc(string $KhuVuc): void
    {
        $this->KhuVuc = $KhuVuc;
    }

    public function setDiaChi(string $DiaChi): void
    {
        $this->DiaChi = $DiaChi;
    }

    public function setMaAdmin(string $MaAdmin): void
    {
        $this->MaAdmin = $MaAdmin;
    }

    public function toArray(): array
    {
        return [
            'MaChiNhanh' => $this->MaChiNhanh,
            'TenChiNhanh' => $this->TenChiNhanh,
            'KhuVuc' => $this->KhuVuc,
            'DiaChi' => $this->DiaChi,
            'MaAdmin' => $this->MaAdmin,
        ];
    }
}
