<?php

namespace App\ThanhToan\Models;

class ThanhToan
{
    private string $MaThanhToan;
    private float $SoTien;
    private \DateTimeInterface $ThoiGianThanhToan;
    private string $HinhThuc;
    private string $TrangThaiThanhToan;
    private string $MaHD;

    public function __construct(
        string $MaThanhToan,
        float $SoTien,
        \DateTimeInterface $ThoiGianThanhToan,
        string $HinhThuc,
        string $TrangThaiThanhToan,
        string $MaHD
    ) {
        $this->MaThanhToan = $MaThanhToan;
        $this->SoTien = $SoTien;
        $this->ThoiGianThanhToan = $ThoiGianThanhToan;
        $this->HinhThuc = $HinhThuc;
        $this->TrangThaiThanhToan = $TrangThaiThanhToan;
        $this->MaHD = $MaHD;
    }

    public function getMaThanhToan(): string
    {
        return $this->MaThanhToan;
    }

    public function getSoTien(): float
    {
        return $this->SoTien;
    }

    public function getThoiGianThanhToan(): \DateTimeInterface
    {
        return $this->ThoiGianThanhToan;
    }

    public function getHinhThuc(): string
    {
        return $this->HinhThuc;
    }

    public function getTrangThaiThanhToan(): string
    {
        return $this->TrangThaiThanhToan;
    }

    public function getMaHD(): string
    {
        return $this->MaHD;
    }

    public function setMaThanhToan(string $MaThanhToan): void
    {
        $this->MaThanhToan = $MaThanhToan;
    }

    public function setSoTien(float $SoTien): void
    {
        $this->SoTien = $SoTien;
    }

    public function setThoiGianThanhToan(\DateTimeInterface $ThoiGianThanhToan): void
    {
        $this->ThoiGianThanhToan = $ThoiGianThanhToan;
    }

    public function setHinhThuc(string $HinhThuc): void
    {
        $this->HinhThuc = $HinhThuc;
    }

    public function setTrangThaiThanhToan(string $TrangThaiThanhToan): void
    {
        $this->TrangThaiThanhToan = $TrangThaiThanhToan;
    }

    public function setMaHD(string $MaHD): void
    {
        $this->MaHD = $MaHD;
    }

    public function toArray(): array
    {
        return [
            'MaThanhToan' => $this->MaThanhToan,
            'SoTien' => $this->SoTien,
            'ThoiGianThanhToan' => $this->ThoiGianThanhToan,
            'HinhThuc' => $this->HinhThuc,
            'TrangThaiThanhToan' => $this->TrangThaiThanhToan,
            'MaHD' => $this->MaHD,
        ];
    }
}
