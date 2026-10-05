<?php

namespace App\DatPhong\Models;

class KhuyenMai
{
    private string $MaKM;
    private string $TenKM;
    private string $LoaiKM;
    private float $GiaTriGiam;
    private int $SoDemToiThieu;
    private \DateTimeInterface $NgayBatDau;
    private \DateTimeInterface $NgayKetThuc;
    private string $TrangThai;

    public function __construct(
        string $MaKM,
        string $TenKM,
        string $LoaiKM,
        float $GiaTriGiam,
        int $SoDemToiThieu,
        \DateTimeInterface $NgayBatDau,
        \DateTimeInterface $NgayKetThuc,
        string $TrangThai = 'HoatDong'
    ) {
        $this->MaKM = $MaKM;
        $this->TenKM = $TenKM;
        $this->LoaiKM = $LoaiKM;
        $this->GiaTriGiam = $GiaTriGiam;
        $this->SoDemToiThieu = $SoDemToiThieu;
        $this->NgayBatDau = $NgayBatDau;
        $this->NgayKetThuc = $NgayKetThuc;
        $this->TrangThai = $TrangThai;
    }

    public function getMaKM(): string
    {
        return $this->MaKM;
    }

    public function getTenKM(): string
    {
        return $this->TenKM;
    }

    public function getLoaiKM(): string
    {
        return $this->LoaiKM;
    }

    public function getGiaTriGiam(): float
    {
        return $this->GiaTriGiam;
    }

    public function getSoDemToiThieu(): int
    {
        return $this->SoDemToiThieu;
    }

    public function getNgayBatDau(): \DateTimeInterface
    {
        return $this->NgayBatDau;
    }

    public function getNgayKetThuc(): \DateTimeInterface
    {
        return $this->NgayKetThuc;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function setMaKM(string $MaKM): void
    {
        $this->MaKM = $MaKM;
    }

    public function setTenKM(string $TenKM): void
    {
        $this->TenKM = $TenKM;
    }

    public function setLoaiKM(string $LoaiKM): void
    {
        $this->LoaiKM = $LoaiKM;
    }

    public function setGiaTriGiam(float $GiaTriGiam): void
    {
        $this->GiaTriGiam = $GiaTriGiam;
    }

    public function setSoDemToiThieu(int $SoDemToiThieu): void
    {
        $this->SoDemToiThieu = $SoDemToiThieu;
    }

    public function setNgayBatDau(\DateTimeInterface $NgayBatDau): void
    {
        $this->NgayBatDau = $NgayBatDau;
    }

    public function setNgayKetThuc(\DateTimeInterface $NgayKetThuc): void
    {
        $this->NgayKetThuc = $NgayKetThuc;
    }

    public function setTrangThai(string $TrangThai): void
    {
        $this->TrangThai = $TrangThai;
    }

    public function toArray(): array
    {
        return [
            'MaKM' => $this->MaKM,
            'TenKM' => $this->TenKM,
            'LoaiKM' => $this->LoaiKM,
            'GiaTriGiam' => $this->GiaTriGiam,
            'SoDemToiThieu' => $this->SoDemToiThieu,
            'NgayBatDau' => $this->NgayBatDau,
            'NgayKetThuc' => $this->NgayKetThuc,
            'TrangThai' => $this->TrangThai,
        ];
    }
}
