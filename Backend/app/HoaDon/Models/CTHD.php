<?php

namespace App\HoaDon\Models;

class CTHD
{
    private ?int $MaCTHD;
    private string $MaHD;
    private string $MaPhieu;
    private ?string $MaPhong;   // Null nếu là dòng dịch vụ
    private ?string $MaCTDV;    // Null nếu là dòng tiền phòng
    private int $SoDem;         // > 0 khi MaPhong != null
    private int $SoLuong;       // > 0 khi MaCTDV != null
    private float $DonGia;

    public function __construct(
        ?int $MaCTHD,
        string $MaHD,
        string $MaPhieu,
        ?string $MaPhong,
        ?string $MaCTDV,
        int $SoDem,
        int $SoLuong,
        float $DonGia
    ) {
        $this->MaCTHD  = $MaCTHD;
        $this->MaHD    = $MaHD;
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
        $this->MaCTDV  = $MaCTDV;
        $this->SoDem   = $SoDem;
        $this->SoLuong = $SoLuong;
        $this->DonGia  = $DonGia;
    }

    // ---------- Getters ----------

    public function getMaCTHD(): ?int   { return $this->MaCTHD; }
    public function getMaHD(): string   { return $this->MaHD; }
    public function getMaPhieu(): string { return $this->MaPhieu; }
    public function getMaPhong(): ?string { return $this->MaPhong; }
    // FIX #2: bỏ alias getMaDV() — chỉ dùng getMaCTDV() nhất quán với DB
    public function getMaCTDV(): ?string { return $this->MaCTDV; }
    public function getSoDem(): int     { return $this->SoDem; }
    public function getSoLuong(): int   { return $this->SoLuong; }
    public function getDonGia(): float  { return $this->DonGia; }

    // ---------- Setters ----------

    public function setMaCTHD(?int $MaCTHD): void     { $this->MaCTHD  = $MaCTHD; }
    public function setMaHD(string $MaHD): void        { $this->MaHD    = $MaHD; }
    public function setMaPhieu(string $MaPhieu): void  { $this->MaPhieu = $MaPhieu; }
    public function setMaPhong(?string $MaPhong): void { $this->MaPhong = $MaPhong; }
    public function setMaCTDV(?string $MaCTDV): void   { $this->MaCTDV  = $MaCTDV; }
    public function setSoDem(int $SoDem): void          { $this->SoDem   = $SoDem; }
    public function setSoLuong(int $SoLuong): void      { $this->SoLuong = $SoLuong; }
    public function setDonGia(float $DonGia): void      { $this->DonGia  = $DonGia; }

    // ---------- Helper ----------

    /** Trả về đây là dòng tiền phòng hay dịch vụ */
    public function laDoPhong(): bool { return $this->MaPhong !== null; }
    public function laDoDichVu(): bool { return $this->MaCTDV !== null; }

    /** Thành tiền: SoDem*DonGia (phòng) hoặc SoLuong*DonGia (dịch vụ) */
    public function thanhTien(): float
    {
        return $this->laDoPhong()
            ? $this->SoDem   * $this->DonGia
            : $this->SoLuong * $this->DonGia;
    }

    public function toArray(): array
    {
        return [
            'MaCTHD'    => $this->MaCTHD,
            'MaHD'      => $this->MaHD,
            'MaPhieu'   => $this->MaPhieu,
            'MaPhong'   => $this->MaPhong,
            'MaCTDV'    => $this->MaCTDV,
            'SoDem'     => $this->SoDem,
            'SoLuong'   => $this->SoLuong,
            'DonGia'    => $this->DonGia,
            'ThanhTien' => $this->thanhTien(),
            'LoaiDong'  => $this->laDoPhong() ? 'TienPhong' : 'TienDichVu',
        ];
    }
}
