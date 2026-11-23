<?php

declare(strict_types=1);

namespace App\Phong\Models;

/**
 * Model Phong
 * Sinh ra tu so do lop (class diagram) - module Phong.
 */
class Phong
{
    public const TRANG_THAI_HOP_LE = ['Trong', 'ChoThanhToan', 'DaDat', 'DangSuDung', 'BaoTri'];
    public const TRANG_THAI_KHOI_TAO = ['Trong', 'BaoTri'];
    public const TRANG_THAI_BAO_TRI = ['Trong', 'BaoTri'];

    /** @var string (PK) */
    private string $MaPhong;
    /** @var string */
    private string $TrangThaiPhong;
    /** @var string (FK) */
    private string $MaLoaiPhong;
    public function __construct(
        string $MaPhong,
        string $TrangThaiPhong,
        string $MaLoaiPhong
    ) {
        $this->MaPhong = $MaPhong;
        $this->TrangThaiPhong = $TrangThaiPhong;
        $this->MaLoaiPhong = $MaLoaiPhong;
    }

    public function getMaPhong(): string
    {
        return $this->MaPhong;
    }

    public function getTrangThaiPhong(): string
    {
        return $this->TrangThaiPhong;
    }

    public function getMaLoaiPhong(): string
    {
        return $this->MaLoaiPhong;
    }

    public function setMaPhong(string $MaPhong): void
    {
        $this->MaPhong = $MaPhong;
    }

    public function setTrangThaiPhong(string $TrangThaiPhong): void
    {
        $this->TrangThaiPhong = $TrangThaiPhong;
    }

    public function setMaLoaiPhong(string $MaLoaiPhong): void
    {
        $this->MaLoaiPhong = $MaLoaiPhong;
    }

    public function toArray(): array
    {
        return [
            'MaPhong' => $this->MaPhong,
            'TrangThaiPhong' => $this->TrangThaiPhong,
            'MaLoaiPhong' => $this->MaLoaiPhong,
        ];
    }

    /**
     * Kiểm tra trạng thái có thuộc danh sách 5 trạng thái hợp lệ của phòng không.
     */
    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::TRANG_THAI_HOP_LE, true);
    }

    /**
     * Kiểm tra trạng thái khởi tạo khi tạo phòng mới (chỉ cho phép Trong hoặc BaoTri).
     */
    public static function validateInitialStatus(string $status): ?string
    {
        if (!self::isValidStatus($status)) {
            return 'Trạng thái phòng không hợp lệ.';
        }
        if (!in_array($status, self::TRANG_THAI_KHOI_TAO, true)) {
            return "Phòng mới chỉ có thể khởi tạo ở trạng thái 'Trong' hoặc 'BaoTri'.";
        }
        return null;
    }

    /**
     * Kiểm tra chuyển đổi trạng thái thủ công từ giao diện/API quản trị.
     * Chỉ cho phép chuyển qua lại giữa 'Trong' và 'BaoTri'; không cho phép
     * chuyển trực tiếp từ 'ChoThanhToan', 'DaDat' hoặc 'DangSuDung' sang 'BaoTri'.
     */
    public static function validateManualStatusTransition(string $currentStatus, string $newStatus, string $maPhong = ''): ?string
    {
        if (!self::isValidStatus($newStatus)) {
            return 'Trạng thái phòng không hợp lệ.';
        }
        if ($newStatus === $currentStatus) {
            return null;
        }

        $roomLabel = $maPhong !== '' ? " '{$maPhong}'" : '';
        if ($currentStatus === 'DangSuDung') {
            return "Phòng{$roomLabel} đang có khách lưu trú! Không thể thay đổi trạng thái phòng lúc này.";
        }
        if ($currentStatus === 'ChoThanhToan') {
            return "Phòng{$roomLabel} đang chờ thanh toán! Vui lòng hủy hoặc chờ xử lý giao dịch về trạng thái Trống trước khi chuyển bảo trì.";
        }
        if ($currentStatus === 'DaDat') {
            return "Phòng{$roomLabel} đã có đơn đặt trước! Vui lòng hủy phiếu đặt phòng về trạng thái Trống trước khi chuyển bảo trì.";
        }
        if (!in_array($newStatus, self::TRANG_THAI_BAO_TRI, true)) {
            return "Thao tác quản trị chỉ cho phép chuyển đổi qua lại giữa 'Trong' và 'BaoTri'.";
        }

        return null;
    }
}