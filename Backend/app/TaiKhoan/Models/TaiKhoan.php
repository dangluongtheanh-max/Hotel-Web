<?php

namespace App\TaiKhoan\Models;

/**
 * Model TaiKhoan
 * Sinh ra tu so do lop (class diagram) - module TaiKhoan.
 */
class TaiKhoan
{
    /** @var string (PK) */
    private string $MaTK;
    /** @var string */
    private string $TenDangNhap;
    /** @var string */
    private string $MatKhau;
    /** @var string */
    private string $Email;
    /** @var string */
    private string $TrangThaiTaiKhoan;
    /** @var string */
    private string $VaiTro;

    public function __construct(
        string $MaTK,
        string $TenDangNhap,
        string $MatKhau,
        string $Email,
        string $TrangThaiTaiKhoan,
        string $VaiTro
    ) {
        $this->MaTK = $MaTK;
        $this->TenDangNhap = $TenDangNhap;
        $this->MatKhau = $MatKhau;
        $this->Email = $Email;
        $this->TrangThaiTaiKhoan = $TrangThaiTaiKhoan;
        $this->VaiTro = $VaiTro;
    }

    public function getMaTK(): string
    {
        return $this->MaTK;
    }

    public function getTenDangNhap(): string
    {
        return $this->TenDangNhap;
    }

    public function getMatKhau(): string
    {
        return $this->MatKhau;
    }

    public function getEmail(): string
    {
        return $this->Email;
    }

    public function getTrangThaiTaiKhoan(): string
    {
        return $this->TrangThaiTaiKhoan;
    }

    public function getVaiTro(): string
    {
        return $this->VaiTro;
    }

    public function setMaTK(string $MaTK): void
    {
        $this->MaTK = $MaTK;
    }

    public function setTenDangNhap(string $TenDangNhap): void
    {
        $this->TenDangNhap = $TenDangNhap;
    }

    public function setMatKhau(string $MatKhau): void
    {
        $this->MatKhau = $MatKhau;
    }

    public function setEmail(string $Email): void
    {
        $this->Email = $Email;
    }

    public function setTrangThaiTaiKhoan(string $TrangThaiTaiKhoan): void
    {
        $this->TrangThaiTaiKhoan = $TrangThaiTaiKhoan;
    }

    public function setVaiTro(string $VaiTro): void
    {
        $this->VaiTro = $VaiTro;
    }

    public function toArray(): array
    {
        return [
            'MaTK' => $this->MaTK,
            'TenDangNhap' => $this->TenDangNhap,
            'MatKhau' => $this->MatKhau,
            'Email' => $this->Email,
            'TrangThaiTaiKhoan' => $this->TrangThaiTaiKhoan,
            'VaiTro' => $this->VaiTro,
        ];
    }
}
