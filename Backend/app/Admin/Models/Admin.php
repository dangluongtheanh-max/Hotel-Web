<?php

namespace App\Admin\Models;

/**
 * Model Admin
 * Sinh ra tu so do lop (class diagram) - module Admin.
 */
class Admin
{
    /** @var string (PK) */
    private string $MaAdmin;
    /** @var string */
    private string $HoTen;
    /** @var string */
    private string $SDT;
    /** @var string */
    private string $Email;
    /** @var string (FK) */
    private string $MaTK;

    public function __construct(
        string $MaAdmin,
        string $HoTen,
        string $SDT,
        string $Email,
        string $MaTK
    ) {
        $this->MaAdmin = $MaAdmin;
        $this->HoTen = $HoTen;
        $this->SDT = $SDT;
        $this->Email = $Email;
        $this->MaTK = $MaTK;
    }

    public function getMaAdmin(): string
    {
        return $this->MaAdmin;
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

    public function setMaAdmin(string $MaAdmin): void
    {
        $this->MaAdmin = $MaAdmin;
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
            'MaAdmin' => $this->MaAdmin,
            'HoTen' => $this->HoTen,
            'SDT' => $this->SDT,
            'Email' => $this->Email,
            'MaTK' => $this->MaTK,
        ];
    }
}
