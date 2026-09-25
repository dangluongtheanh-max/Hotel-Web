<?php

namespace App\KhachHang\Models;

/**
 * Model KhachHang
 * Sinh ra tu so do lop (class diagram) - module KhachHang.
 */
class KhachHang
{
    /** @var string (PK) */
    private string $MaKH;
    /** @var string */
    private string $HoTen;
    /** @var string */
    private string $SDT;
    /** @var string */
    private string $Email;
    /** @var string (FK) */
    private string $MaTK;

    public function __construct(
        string $MaKH,
        string $HoTen,
        string $SDT,
        string $Email,
        string $MaTK
    ) {
        $this->MaKH = $MaKH;
        $this->HoTen = $HoTen;
        $this->SDT = $SDT;
        $this->Email = $Email;
        $this->MaTK = $MaTK;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
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

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
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

    public function toArray(): array
    {
        return [
            'MaKH' => $this->MaKH,
            'HoTen' => $this->HoTen,
            'SDT' => $this->SDT,
            'Email' => $this->Email,
            'MaTK' => $this->MaTK,
        ];
    }
}
