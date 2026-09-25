<?php

namespace App\Admin\Services;

use App\Admin\Repositories\AdminRepository;

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

    public function xemDSNhanVien(array $params = [])
    {
        // TODO: xemDSNhanVien
        return null;
    }

    public function timKiemNhanVien(array $params = [])
    {
        // TODO: timKiemNhanVien
        return null;
    }

    public function xemCTNhanVien(array $params = [])
    {
        // TODO: xemCTNhanVien
        return null;
    }

    public function capNhatThongTinNhanVien(array $params = [])
    {
        // TODO: capNhatThongTinNhanVien
        return null;
    }

    public function themNhanVien(array $params = [])
    {
        // TODO: themNhanVien
        return null;
    }

    public function moTaiKhoanNhanVien(array $params = [])
    {
        // TODO: moTaiKhoanNhanVien
        return null;
    }

    public function khoaTaiKhoanNhanVien(array $params = [])
    {
        // TODO: khoaTaiKhoanNhanVien
        return null;
    }

    public function thongKeSoLuongNhanVien(array $params = [])
    {
        // TODO: thongKeSoLuongNhanVien
        return null;
    }

    public function thongKeDoanhThuChiNhanh(array $params = [])
    {
        // TODO: thongKeDoanhThuChiNhanh
        return null;
    }

    public function thongKeSoLuongPhong(array $params = [])
    {
        // TODO: thongKeSoLuongPhong
        return null;
    }

}
