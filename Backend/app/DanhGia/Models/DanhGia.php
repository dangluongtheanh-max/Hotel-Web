<?php

namespace App\DanhGia\Models;

/**
 * Model DanhGia
 * Sinh ra tu so do lop (class diagram) - module DanhGia.
 */
class DanhGia
{
    /** @var string (PK) */
    private string $MaDanhGia;
    /** @var int */
    private int $SoSao;
    /** @var string */
    private string $NoiDung;
    /** @var \DateTimeInterface */
    private \DateTimeInterface $NgayDanhGia;
    /** @var string (FK) */
    private string $MaKH;
    /** @var string (FK) */
    private string $MaPhieu;

    public function __construct(
        string $MaDanhGia,
        int $SoSao,
        string $NoiDung,
        \DateTimeInterface $NgayDanhGia,
        string $MaKH,
        string $MaPhieu
    ) {
        $this->MaDanhGia = $MaDanhGia;
        $this->SoSao = $SoSao;
        $this->NoiDung = $NoiDung;
        $this->NgayDanhGia = $NgayDanhGia;
        $this->MaKH = $MaKH;
        $this->MaPhieu = $MaPhieu;
    }

    public function getMaDanhGia(): string
    {
        return $this->MaDanhGia;
    }

    public function getSoSao(): int
    {
        return $this->SoSao;
    }

    public function getNoiDung(): string
    {
        return $this->NoiDung;
    }

    public function getNgayDanhGia(): \DateTimeInterface
    {
        return $this->NgayDanhGia;
    }

    public function getMaKH(): string
    {
        return $this->MaKH;
    }

    public function getMaPhieu(): string
    {
        return $this->MaPhieu;
    }

    public function setMaDanhGia(string $MaDanhGia): void
    {
        $this->MaDanhGia = $MaDanhGia;
    }

    public function setSoSao(int $SoSao): void
    {
        $this->SoSao = $SoSao;
    }

    public function setNoiDung(string $NoiDung): void
    {
        $this->NoiDung = $NoiDung;
    }

    public function setNgayDanhGia(\DateTimeInterface $NgayDanhGia): void
    {
        $this->NgayDanhGia = $NgayDanhGia;
    }

    public function setMaKH(string $MaKH): void
    {
        $this->MaKH = $MaKH;
    }

    public function setMaPhieu(string $MaPhieu): void
    {
        $this->MaPhieu = $MaPhieu;
    }

    public function toArray(): array
    {
        return [
            'MaDanhGia' => $this->MaDanhGia,
            'SoSao' => $this->SoSao,
            'NoiDung' => $this->NoiDung,
            'NgayDanhGia' => $this->NgayDanhGia,
            'MaKH' => $this->MaKH,
            'MaPhieu' => $this->MaPhieu,
        ];
    }
}
