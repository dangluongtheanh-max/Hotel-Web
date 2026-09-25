<?php

namespace App\DatPhong\Models;

/**
 * Model PhieuNhanPhong
 * Sinh ra tu so do lop (class diagram) - module DatPhong.
 */
class PhieuNhanPhong
{
    /** @var string (PK) */
    private string $MaPhieu;
    /** @var \DateTimeInterface */
    private \DateTimeInterface $NgayLap;
    /** @var \DateTimeInterface */
    private \DateTimeInterface $ThoiGianNhanPhong;
    /** @var \DateTimeInterface */
    private \DateTimeInterface $ThoiGianTraPhong;
    /** @var int */
    private int $SoNguoi;
    /** @var string */
    private string $TrangThai;
    /** @var string (FK) */
    private string $MaKH;
    /** @var string (FK) */
    private string $MaPhong;
    /** @var string (FK) */
    private string $MaLeTan;
    /** @var string (FK) */
    private string $MaKM;
    /** @var string (FK) */
    private string $MaChiNhanh;

    public function __construct(
        string $MaPhieu,
        \DateTimeInterface $NgayLap,
        \DateTimeInterface $ThoiGianNhanPhong,
        \DateTimeInterface $ThoiGianTraPhong,
        int $SoNguoi,
        string $TrangThai,
        string $MaKH,
        string $MaPhong,
        string $MaLeTan,
        string $MaKM,
        string $MaChiNhanh
    ) {
        $this->MaPhieu = $MaPhieu;
        $this->NgayLap = $NgayLap;
        $this->ThoiGianNhanPhong = $ThoiGianNhanPhong;
        $this->ThoiGianTraPhong = $ThoiGianTraPhong;
        $this->SoNguoi = $SoNguoi;
        $this->TrangThai = $TrangThai;
        $this->MaKH = $MaKH;
        $this->MaPhong = $MaPhong;
        $this->MaLeTan = $MaLeTan;
        $this->MaKM = $MaKM;
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getNgayLap(): \DateTimeInterface
    {
        return $this->NgayLap;
    }

    public function getThoiGianNhanPhong(): \DateTimeInterface
    {
        return $this->ThoiGianNhanPhong;
    }

    public function getThoiGianTraPhong(): \DateTimeInterface
    {
        return $this->ThoiGianTraPhong;
    }

    public function getSoNguoi(): int
    {
        return $this->SoNguoi;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getMaLeTan(): string
    {
        return $this->MaLeTan;
    }

    public function getMaKM(): string
    {
        return $this->MaKM;
    }

    public function getMaChiNhanh(): string
    {
        return $this->MaChiNhanh;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function setNgayLap(\DateTimeInterface $NgayLap): void
    {
        $this->NgayLap = $NgayLap;
    }

    public function setThoiGianNhanPhong(\DateTimeInterface $ThoiGianNhanPhong): void
    {
        $this->ThoiGianNhanPhong = $ThoiGianNhanPhong;
    }

    public function setThoiGianTraPhong(\DateTimeInterface $ThoiGianTraPhong): void
    {
        $this->ThoiGianTraPhong = $ThoiGianTraPhong;
    }

    public function setSoNguoi(int $SoNguoi): void
    {
        $this->SoNguoi = $SoNguoi;
    }

    public function setTrangThai(string $TrangThai): void
    {
        $this->TrangThai = $TrangThai;
    }

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
    }

    public function setMaPhong(string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setMaLeTan(string $MaLeTan): void
    {
        $this->MaLeTan = $MaLeTan;
    }

    public function setMaKM(string $MaKM): void
    {
        $this->MaKM = $MaKM;
    }

    public function setMaChiNhanh(string $MaChiNhanh): void
    {
        $this->MaChiNhanh = $MaChiNhanh;
    }

    public function toArray(): array
    {
        return [
            'MaPhieu' => $this->MaPhieu,
            'NgayLap' => $this->NgayLap,
            'ThoiGianNhanPhong' => $this->ThoiGianNhanPhong,
            'ThoiGianTraPhong' => $this->ThoiGianTraPhong,
            'SoNguoi' => $this->SoNguoi,
            'TrangThai' => $this->TrangThai,
            'MaKH' => $this->MaKH,
            'MaPhong' => $this->MaPhong,
            'MaLeTan' => $this->MaLeTan,
            'MaKM' => $this->MaKM,
            'MaChiNhanh' => $this->MaChiNhanh,
        ];
    }
}
