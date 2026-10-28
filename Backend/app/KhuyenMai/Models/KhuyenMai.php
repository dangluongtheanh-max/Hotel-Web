<?php

declare(strict_types=1);

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
    private int $soLuong;
    private int $soLuongDaDung;
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
        string $trangThai = 'HoatDong',
        int $soLuong = 100,
        int $soLuongDaDung = 0
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
        $this->soLuong = $soLuong;
        $this->soLuongDaDung = $soLuongDaDung;
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
            $data['TrangThai'] ?? 'HoatDong',
            (int)($data['SoLuong'] ?? 100),
            (int)($data['SoLuongDaDung'] ?? 0)
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
            'SoLuong' => $this->soLuong,
            'SoLuongDaDung' => $this->soLuongDaDung,
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
    public function getSoLuong(): int { return $this->soLuong; }
    public function getSoLuongDaDung(): int { return $this->soLuongDaDung; }
    public function getSoLuongConLai(): int { return max(0, $this->soLuong - $this->soLuongDaDung); }
}