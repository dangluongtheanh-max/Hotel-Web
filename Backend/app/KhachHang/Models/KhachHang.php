<?php

declare(strict_types=1);

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
    /** @var string|null */
    private ?string $SDT;
    /** @var string|null (FK) */
    private ?string $MaTK;
    /** @var string|null */
    private ?string $NgaySinh;
    /** @var string */
    private string $QuocTich;
    /** @var string */
    private string $LoaiGiayTo;
    /** @var string|null */
    private ?string $SoGiayTo;

    public function __construct(
        string $MaKH,
        string $HoTen,
        ?string $SDT = null,
        ?string $MaTK = null,
        ?string $NgaySinh = null,
        string $QuocTich = 'Việt Nam',
        string $LoaiGiayTo = 'CCCD',
        ?string $SoGiayTo = null
    ) {
        $this->MaKH = $MaKH;
        $this->HoTen = $HoTen;
        $this->SDT = $SDT;
        $this->MaTK = $MaTK;
        $this->NgaySinh = $NgaySinh;
        $this->QuocTich = $QuocTich;
        $this->LoaiGiayTo = $LoaiGiayTo;
        $this->SoGiayTo = $SoGiayTo;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
    }

    public function getHoTen(): string
    {
        return $this->HoTen;
    }

    public function getSDT(): ?string
    {
        return $this->SDT;
    }

    public function getMaTK(): ?string
    {
        return $this->MaTK;
    }

    public function getNgaySinh(): ?string
    {
        return $this->NgaySinh;
    }

    public function getQuocTich(): string
    {
        return $this->QuocTich;
    }

    public function getLoaiGiayTo(): string
    {
        return $this->LoaiGiayTo;
    }

    public function getSoGiayTo(): ?string
    {
        return $this->SoGiayTo;
    }

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
    }

    public function setHoTen(string $HoTen): void
    {
        $this->HoTen = $HoTen;
    }

    public function setSDT(?string $SDT): void
    {
        $this->SDT = $SDT;
    }

    public function setMaTK(?string $MaTK): void
    {
        $this->MaTK = $MaTK;
    }

    public function setNgaySinh(?string $NgaySinh): void
    {
        $this->NgaySinh = $NgaySinh;
    }

    public function setQuocTich(string $QuocTich): void
    {
        $this->QuocTich = $QuocTich;
    }

    public function setLoaiGiayTo(string $LoaiGiayTo): void
    {
        $this->LoaiGiayTo = $LoaiGiayTo;
    }

    public function setSoGiayTo(?string $SoGiayTo): void
    {
        $this->SoGiayTo = $SoGiayTo;
    }

    public function toArray(): array
    {
        return [
            'MaKH' => $this->MaKH,
            'HoTen' => $this->HoTen,
            'SDT' => $this->SDT,
            'MaTK' => $this->MaTK,
            'NgaySinh' => $this->NgaySinh,
            'QuocTich' => $this->QuocTich,
            'LoaiGiayTo' => $this->LoaiGiayTo,
            'SoGiayTo' => $this->SoGiayTo,
        ];
    }
}
