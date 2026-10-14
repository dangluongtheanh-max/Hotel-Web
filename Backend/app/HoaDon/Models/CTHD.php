<?php

namespace App\HoaDon\Models;

/**
 * Model đại diện cho một dòng chi tiết trong hóa đơn (CTHD).
 * Có thể là chi tiết tiền phòng (MaPhong != null) hoặc tiền dịch vụ (MaCTDV != null).
 */
class CTHD
{
    private ?int $MaCTHD;
    private string $MaHD;
    private string $MaPhieu;
    private ?string $MaPhong;
    private ?string $MaCTDV;
    private int $SoDem;
    private int $SoLuong;
    private float $DonGia;

    public function __construct(
        ?int $MaCTHD = null,
        string $MaHD = '',
        string $MaPhieu = '',
        ?string $MaPhong = null,
        ?string $MaCTDV = null,
        int $SoDem = 0,
        int $SoLuong = 1,
        float $DonGia = 0.0
    ) {
        $this->MaCTHD = $MaCTHD;
        $this->MaHD = $MaHD;
        $this->MaPhieu = $MaPhieu;
        $this->MaPhong = $MaPhong;
        $this->MaCTDV = $MaCTDV;
        $this->SoDem = $SoDem;
        $this->SoLuong = $SoLuong;
        $this->DonGia = $DonGia;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['MaCTHD']) ? (int)$data['MaCTHD'] : null,
            $data['MaHD'] ?? '',
            $data['MaPhieu'] ?? '',
            $data['MaPhong'] ?? null,
            $data['MaCTDV'] ?? ($data['MaDV'] ?? null),
            (int)($data['SoDem'] ?? 0),
            (int)($data['SoLuong'] ?? 1),
            (float)($data['DonGia'] ?? 0.0)
        );
    }

    public function getMaCTHD(): ?int { return $this->MaCTHD; }
    public function getMaHD(): string { return $this->MaHD; }
    public function getMaPhieu(): string { return $this->MaPhieu; }
    public function getMaPhong(): ?string { return $this->MaPhong; }
    public function getMaCTDV(): ?string { return $this->MaCTDV; }
    public function getSoDem(): int { return $this->SoDem; }
    public function getSoLuong(): int { return $this->SoLuong; }
    public function getDonGia(): float { return $this->DonGia; }

    public function getThanhTien(): float
    {
        if ($this->MaPhong !== null) {
            return $this->SoDem * $this->DonGia;
        }
        return $this->SoLuong * $this->DonGia;
    }

    public function toArray(): array
    {
        return [
            'MaCTHD' => $this->MaCTHD,
            'MaHD' => $this->MaHD,
            'MaPhieu' => $this->MaPhieu,
            'MaPhong' => $this->MaPhong,
            'MaCTDV' => $this->MaCTDV,
            'SoDem' => $this->SoDem,
            'SoLuong' => $this->SoLuong,
            'DonGia' => $this->DonGia,
            'ThanhTien' => $this->getThanhTien(),
        ];
    }
}
