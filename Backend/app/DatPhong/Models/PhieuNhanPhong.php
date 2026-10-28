<?php

declare(strict_types=1);

namespace App\DatPhong\Models;

class PhieuNhanPhong
{
    private string $MaPhieu;
    private \DateTimeInterface $NgayLap;
    private string $KenhDat;
    private string $TrangThai;
    private ?\DateTimeInterface $ThoiGianHetHan;
    private string $MaKH;
    private ?string $MaLeTan;
    private ?string $MaKM;

    public function __construct(
        string $MaPhieu,
        \DateTimeInterface $NgayLap,
        string $KenhDat,
        string $TrangThai,
        string $MaKH,
        ?string $MaLeTan = null,
        ?string $MaKM = null,
        ?\DateTimeInterface $ThoiGianHetHan = null
    ) {
        $this->MaPhieu = $MaPhieu;
        $this->NgayLap = $NgayLap;
        $this->KenhDat = $KenhDat;
        $this->TrangThai = $TrangThai;
        $this->MaKH = $MaKH;
        $this->MaLeTan = $MaLeTan;
        $this->MaKM = $MaKM;
        $this->ThoiGianHetHan = $ThoiGianHetHan;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function getNgayLap(): \DateTimeInterface
    {
        return $this->NgayLap;
    }

    public function getKenhDat(): string
    {
        return $this->KenhDat;
    }

    public function getTrangThai(): string
    {
        return $this->TrangThai;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
    }

    public function getMaLeTan(): ?string
    {
        return $this->MaLeTan;
    }

    public function getMaKM(): ?string
    {
        return $this->MaKM;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function setNgayLap(\DateTimeInterface $NgayLap): void
    {
        $this->NgayLap = $NgayLap;
    }

    public function setKenhDat(string $KenhDat): void
    {
        $this->KenhDat = $KenhDat;
    }

    public function setTrangThai(string $TrangThai): void
    {
        $this->TrangThai = $TrangThai;
    }

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
    }

    public function setMaLeTan(?string $MaLeTan): void
    {
        $this->MaLeTan = $MaLeTan;
    }

    public function setMaKM(?string $MaKM): void
    {
        $this->MaKM = $MaKM;
    }

    public function getThoiGianHetHan(): ?\DateTimeInterface
    {
        return $this->ThoiGianHetHan;
    }

    public function setThoiGianHetHan(?\DateTimeInterface $ThoiGianHetHan): void
    {
        $this->ThoiGianHetHan = $ThoiGianHetHan;
    }

    public function toArray(): array
    {
        return [
            'MaPhieu' => $this->MaPhieu,
            'NgayLap' => $this->NgayLap,
            'KenhDat' => $this->KenhDat,
            'TrangThai' => $this->TrangThai,
            'ThoiGianHetHan' => $this->ThoiGianHetHan ? $this->ThoiGianHetHan->format('Y-m-d H:i:s') : null,
            'MaKH' => $this->MaKH,
            'MaLeTan' => $this->MaLeTan,
            'MaKM' => $this->MaKM,
        ];
    }
}
