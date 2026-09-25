<?php

namespace App\ThanhToan\Models;

/**
 * Model ThanhToan
 * Sinh ra tu so do lop (class diagram) - module ThanhToan.
 */
class ThanhToan
{
    /** @var string (PK) */
    private string $MaThanhToan;
    /** @var float */
    private float $SoTien;
    /** @var \DateTimeInterface */
    private \DateTimeInterface $ThoiGianThanhToan;
    /** @var string */
    private string $TrangThaiThanhToan;
    /** @var string (FK) */
    private string $MaHD;

    public function __construct(
        string $MaThanhToan,
        float $SoTien,
        \DateTimeInterface $ThoiGianThanhToan,
        string $TrangThaiThanhToan,
        string $MaHD
    ) {
        $this->MaThanhToan = $MaThanhToan;
        $this->SoTien = $SoTien;
        $this->ThoiGianThanhToan = $ThoiGianThanhToan;
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
            'TrangThaiThanhToan' => $this->TrangThaiThanhToan,
            'MaHD' => $this->MaHD,
        ];
    }
}
