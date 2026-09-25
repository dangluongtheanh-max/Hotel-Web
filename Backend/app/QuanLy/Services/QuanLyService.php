<?php

namespace App\QuanLy\Services;

use App\QuanLy\Repositories\QuanLyRepository;

/**
 * Service (business logic) cho module QuanLy.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class QuanLyService
{
    private QuanLyRepository $repository;

    public function __construct(QuanLyRepository $repository)
    {
        $this->repository = $repository;
    }

    public function xemDSLeTan(array $params = [])
    {
        // TODO: xemDSLeTan
        return null;
    }

    public function xemChiTietLeTan(array $params = [])
    {
        // TODO: xemChiTietLeTan
        return null;
    }

    public function capNhatThongTinLeTan(array $params = [])
    {
        // TODO: capNhatThongTinLeTan
        return null;
    }

    public function timKiemLeTan(array $params = [])
    {
        // TODO: timKiemLeTan
        return null;
    }

    public function khoaTaiKhoanLeTan(array $params = [])
    {
        // TODO: khoaTaiKhoanLeTan
        return null;
    }

    public function moTaiKhoanLeTan(array $params = [])
    {
        // TODO: moTaiKhoanLeTan
        return null;
    }

    public function thongKeDoanhThu(array $params = [])
    {
        // TODO: thongKeDoanhThu
        return null;
    }

    public function thongKeSLDatPhong(array $params = [])
    {
        // TODO: thongKeSLDatPhong
        return null;
    }

    public function thongKeDanhGiaKhachHang(array $params = [])
    {
        // TODO: thongKeDanhGiaKhachHang
        return null;
    }

    public function timHoaDon(array $params = [])
    {
        // TODO: timHoaDon
        return null;
    }

    public function xemHoaDon(array $params = [])
    {
        // TODO: xemHoaDon
        return null;
    }

    public function xemCTHD(array $params = [])
    {
        // TODO: xemCTHD
        return null;
    }

}
