<?php

namespace App\KhachHang\Services;

use App\KhachHang\Repositories\KhachHangRepository;

/**
 * Service (business logic) cho module KhachHang.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class KhachHangService
{
    private KhachHangRepository $repository;

    public function __construct(KhachHangRepository $repository)
    {
        $this->repository = $repository;
    }

    public function dangKy(array $params = [])
    {
        // TODO: dangKy
        return null;
    }

    public function xemDSPhong(array $params = [])
    {
        // TODO: xemDSPhong
        return null;
    }

    public function xemCTPhong(array $params = [])
    {
        // TODO: xemCTPhong
        return null;
    }

    public function datPhong(array $params = [])
    {
        // TODO: datPhong
        return null;
    }

    public function thanhToan(array $params = [])
    {
        // TODO: thanhToan
        return null;
    }

    public function huyDatPhong(array $params = [])
    {
        // TODO: huyDatPhong
        return null;
    }

    public function xemLichSuDatPhong(array $params = [])
    {
        // TODO: xemLichSuDatPhong
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

    public function xemThongTinCaNhan(array $params = [])
    {
        // TODO: xemThongTinCaNhan
        return null;
    }

    public function capNhatThongTinCaNhan(array $params = [])
    {
        // TODO: capNhatThongTinCaNhan
        return null;
    }

    public function themDanhGia(array $params = [])
    {
        // TODO: themDanhGia
        return null;
    }

    public function xoaDanhGia(array $params = [])
    {
        // TODO: xoaDanhGia
        return null;
    }

}
