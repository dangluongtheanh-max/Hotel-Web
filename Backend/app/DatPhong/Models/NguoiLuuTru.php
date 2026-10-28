<?php

declare(strict_types=1);

namespace App\DatPhong\Models;

/**
 * Model NguoiLuuTru
 * Thong tin khach o tai phong vat ly trong PhieuNhanPhong.
 */
class NguoiLuuTru
{
    private string $MaNguoiLuuTru;
    private string $HoTen;
    private \DateTimeInterface $NgaySinh;
    private string $QuocTich;
    private string $LoaiGiayTo;
    private string $SoGiayTo;
    private ?string $GhiChu;
    private string $MaPhieu;
    private string $MaPhong;

    public function __construct(
        string $MaNguoiLuuTru,
        string $HoTen,
        \DateTimeInterface $NgaySinh,
        string $QuocTich,
        string $LoaiGiayTo,
        string $SoGiayTo,
        ?string $GhiChu,
        string $MaPhieu,
        string $MaPhong
    ) {
        $this->MaNguoiLuuTru = $MaNguoiLuuTru;
        $this->HoTen = $HoTen;
        $this->NgaySinh = $NgaySinh;
        $this->QuocTich = $QuocTich;
        $this->LoaiGiayTo = $LoaiGiayTo;
        $this->SoGiayTo = $SoGiayTo;
        $this->GhiChu = $GhiChu;
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
    }

    public function getMaNguoiLuuTru(): string
    {
        return $this->MaNguoiLuuTru;
    }

    public function getHoTen(): string
    {
        return $this->HoTen;
    }

    public function getNgaySinh(): \DateTimeInterface
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

    public function getSoGiayTo(): string
    {
        return $this->SoGiayTo;
    }

    public function getGhiChu(): ?string
    {
        return $this->GhiChu;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function toArray(): array
    {
        return [
            'MaNguoiLuuTru' => $this->MaNguoiLuuTru,
            'HoTen' => $this->HoTen,
            'NgaySinh' => $this->NgaySinh->format('Y-m-d'),
            'QuocTich' => $this->QuocTich,
            'LoaiGiayTo' => $this->LoaiGiayTo,
            'SoGiayTo' => $this->SoGiayTo,
            'GhiChu' => $this->GhiChu,
            'MaPhieu' => $this->MaPhieu,
            'MaPhong' => $this->MaPhong,
        ];
    }
}
