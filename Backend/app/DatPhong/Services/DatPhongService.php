<?php

namespace App\DatPhong\Services;

use App\DatPhong\Repositories\DatPhongRepository;

/**
 * Service (business logic) cho module DatPhong.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class DatPhongService
{
    private DatPhongRepository $repository;

    public function __construct(DatPhongRepository $repository)
    {
        $this->repository = $repository;
    }

    public function xemDSPhieu(array $params = [])
    {
        // TODO: xemDSPhieu
        return null;
    }

    public function xemCTPhieu(array $params = [])
    {
        // TODO: xemCTPhieu
        return null;
    }

    public function timKiemPhieu(array $params = [])
    {
        // TODO: timKiemPhieu
        return null;
    }

    public function checkin(array $params = [])
    {
        // TODO: checkin
        return null;
    }

    public function checkout(array $params = [])
    {
        // TODO: checkout
        return null;
    }

    public function taoPhieuNhanPhong(array $params = [])
    {
        // TODO: taoPhieuNhanPhong
        return null;
    }

    public function capNhatPhieuNhanPhong(array $params = [])
    {
        // TODO: capNhatPhieuNhanPhong
        return null;
    }

    public function apDungKhuyenMai(array $params = [])
    {
        // TODO: apDungKhuyenMai
        return null;
    }

}
