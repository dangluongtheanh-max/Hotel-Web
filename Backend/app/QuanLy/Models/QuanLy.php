<?php

namespace App\QuanLy\Models;

/**
 * Model QuanLy
 * Sinh ra tu so do lop (class diagram) - module QuanLy.
 */
class QuanLy
{
    /** @var string (PK) */
    private string $MaQuanLy;
    /** @var string */
    private string $HoTen;
    /** @var string */
    private string $SDT;
    /** @var string */
    private string $Email;
    /** @var string (FK) */
    private string $MaTK;
    /** @var string (FK) */
    private string $MaChiNhanh;

    public function __construct(
        string $MaQuanLy,
        string $HoTen,
        string $SDT,
        string $Email,
        string $MaTK,
        string $MaChiNhanh
    ) {
        $this->MaQuanLy = $MaQuanLy;
        $this->HoTen = $HoTen;
        $this->SDT = $SDT;
        $this->Email = $Email;
        $this->MaTK = $MaTK;
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function getMaQuanLy(): string
    {
        return $this->MaQuanLy;
    }

    public function getHoTen(): string
    {
        return $this->HoTen;
    }

    public function getSDT(): string
    {
        return $this->SDT;
    }

    public function getEmail(): string
    {
        return $this->Email;
    }

    public function getMaTK(): string
    {
        return $this->MaTK;
    }

    public function getMaChiNhanh(): string
    {
        return $this->MaChiNhanh;
    }

    public function setMaQuanLy(string $MaQuanLy): void
    {
        $this->MaQuanLy = $MaQuanLy;
    }

    public function setHoTen(string $HoTen): void
    {
        $this->HoTen = $HoTen;
    }

    public function setSDT(string $SDT): void
    {
        $this->SDT = $SDT;
    }

    public function setEmail(string $Email): void
    {
        $this->Email = $Email;
    }

    public function setMaTK(string $MaTK): void
    {
        $this->MaTK = $MaTK;
    }

    public function setMaChiNhanh(string $MaChiNhanh): void
    {
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function toArray(): array
    {
        return [
            'MaQuanLy' => $this->MaQuanLy,
            'HoTen' => $this->HoTen,
            'SDT' => $this->SDT,
            'Email' => $this->Email,
            'MaTK' => $this->MaTK,
            'MaChiNhanh' => $this->MaChiNhanh,
        ];
    }
}
