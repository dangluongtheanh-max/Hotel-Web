<?php

namespace App\KhuyenMai\Models;

/**
 * Model biểu diễn thông tin một mã khuyến mãi trong hệ thống.
 */
class KhuyenMai
{
    private string $maKM;
    private string $tenKM;
    private string $loaiKM;
    private float $giaTriGiam;
    private int $soDemToiThieu;
    private int $soNguoiToiThieu;
    private string $ngayBatDau;
    private string $ngayKetThuc;
    private string $trangThai;

    public function __construct(
        string $maKM = '',
        string $tenKM = '',
        string $loaiKM = 'PhanTram',
        float $giaTriGiam = 0.0,
        int $soDemToiThieu = 0,
        int $soNguoiToiThieu = 0,
        string $ngayBatDau = '',
        string $ngayKetThuc = '',
        string $trangThai = 'HoatDong'
    ) {
        $this->maKM = $maKM;
        $this->tenKM = $tenKM;
        $this->loaiKM = $loaiKM;
        $this->giaTriGiam = $giaTriGiam;
        $this->soDemToiThieu = $soDemToiThieu;
        $this->soNguoiToiThieu = $soNguoiToiThieu;
        $this->ngayBatDau = $ngayBatDau;
        $this->ngayKetThuc = $ngayKetThuc;
        $this->trangThai = $trangThai;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['MaKM'] ?? '',
            $data['TenKM'] ?? '',
            $data['LoaiKM'] ?? 'PhanTram',
            (float)($data['GiaTriGiam'] ?? 0),
            (int)($data['SoDemToiThieu'] ?? 0),
            (int)($data['SoNguoiToiThieu'] ?? 0),
            $data['NgayBatDau'] ?? '',
            $data['NgayKetThuc'] ?? '',
            $data['TrangThai'] ?? 'HoatDong'
        );
    }

    public function toArray(): array
    {
        return [
            'MaKM' => $this->maKM,
            'TenKM' => $this->tenKM,
            'LoaiKM' => $this->loaiKM,
            'GiaTriGiam' => $this->giaTriGiam,
            'SoDemToiThieu' => $this->soDemToiThieu,
            'SoNguoiToiThieu' => $this->soNguoiToiThieu,
            'NgayBatDau' => $this->ngayBatDau,
            'NgayKetThuc' => $this->ngayKetThuc,
            'TrangThai' => $this->trangThai,
        ];
    }

    // Getters
    public function getMaKM(): string { return $this->maKM; }
    public function getTenKM(): string { return $this->tenKM; }
    public function getLoaiKM(): string { return $this->loaiKM; }
    public function getGiaTriGiam(): float { return $this->giaTriGiam; }
    public function getSoDemToiThieu(): int { return $this->soDemToiThieu; }
    public function getSoNguoiToiThieu(): int { return $this->soNguoiToiThieu; }
    public function getNgayBatDau(): string { return $this->ngayBatDau; }
    public function getNgayKetThuc(): string { return $this->ngayKetThuc; }
    public function getTrangThai(): string { return $this->trangThai; }
}
