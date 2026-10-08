<?php

namespace App\Admin\Services;

use App\Admin\Repositories\AdminRepository;
use InvalidArgumentException;

/**
 * Service (business logic) cho module Admin.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class AdminService
{
    private AdminRepository $repository;

    public function __construct(AdminRepository $repository)
    {
        $this->repository = $repository;
    }

    public function xemDSNhanVien(array $params = []): array
    {
        return $this->repository->getAllLeTan($params);
    }

    public function timKiemNhanVien(array $params = []): array
    {
        $keyword = $params['tuKhoa'] ?? $params['keyword'] ?? $params['HoTen'] ?? '';
        return $this->repository->timKiemLeTan($keyword);
    }

    public function xemCTNhanVien(array $params = []): ?array
    {
        $id = $params['MaLeTan'] ?? $params['id'] ?? $params['MaTK'] ?? '';
        if (empty($id)) {
            throw new InvalidArgumentException('Mã nhân viên (MaLeTan) hoặc Mã tài khoản là bắt buộc.');
        }

        return $this->repository->findLeTanById($id);
    }

    public function capNhatThongTinNhanVien(array $params = []): array
    {
        $id = $params['MaLeTan'] ?? $params['id'] ?? '';
        if (empty($id)) {
            throw new InvalidArgumentException('Mã nhân viên (MaLeTan) là bắt buộc.');
        }

        $ok = $this->repository->capNhatLeTan($id, $params);
        if (!$ok) {
            throw new \RuntimeException('Không thể cập nhật thông tin nhân viên.');
        }

        return [
            'success' => true,
            'message' => 'Cập nhật thông tin nhân viên thành công.',
            'data' => $this->repository->findLeTanById($id)
        ];
    }

    public function themNhanVien(array $params = []): array
    {
        if (empty($params['HoTen'])) {
            throw new InvalidArgumentException('Họ tên nhân viên là bắt buộc.');
        }
        if (empty($params['SDT'])) {
            throw new InvalidArgumentException('Số điện thoại nhân viên là bắt buộc.');
        }

        return $this->repository->themLeTan($params);
    }

    public function moTaiKhoanNhanVien(array $params = []): array
    {
        $id = $params['MaLeTan'] ?? $params['id'] ?? $params['MaTK'] ?? '';
        if (empty($id)) {
            throw new InvalidArgumentException('Mã nhân viên (MaLeTan) hoặc Mã tài khoản là bắt buộc.');
        }

        $this->repository->capNhatTrangThaiTaiKhoan($id, 'HoatDong');

        return [
            'success' => true,
            'message' => 'Đã mở khóa tài khoản thành công.',
            'data' => $this->repository->findLeTanById($id)
        ];
    }

    public function khoaTaiKhoanNhanVien(array $params = []): array
    {
        $id = $params['MaLeTan'] ?? $params['id'] ?? $params['MaTK'] ?? '';
        if (empty($id)) {
            throw new InvalidArgumentException('Mã nhân viên (MaLeTan) hoặc Mã tài khoản là bắt buộc.');
        }

        $this->repository->capNhatTrangThaiTaiKhoan($id, 'Khoa');

        return [
            'success' => true,
            'message' => 'Đã khóa tài khoản thành công.',
            'data' => $this->repository->findLeTanById($id)
        ];
    }

    public function thongKeSoLuongNhanVien(array $params = []): array
    {
        return $this->repository->thongKeSoLuongNhanVien();
    }

    public function thongKeDoanhThuChiNhanh(array $params = []): array
    {
        return $this->repository->thongKeDoanhThu($params);
    }

    public function thongKeSoLuongPhong(array $params = []): array
    {
        return $this->repository->thongKeSoLuongPhong();
    }
}
