<?php

namespace App\Phong\Services;

use App\Phong\Repositories\PhongRepository;

/**
 * Service (business logic) cho module Phong.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class PhongService
{
    private PhongRepository $repository;

    public function __construct(PhongRepository $repository)
    {
        $this->repository = $repository;
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

    public function themPhong(array $params = [])
    {
        // TODO: themPhong
        return null;
    }

    public function capNhatThongTinPhong(array $params = [])
    {
        // TODO: capNhatThongTinPhong
        return null;
    }

    public function timKiemPhong(array $params = [])
    {
        // TODO: timKiemPhong
        return null;
    }

    public function kiemTraTrangThaiPhong(array $params = [])
    {
        // TODO: kiemTraTrangThaiPhong
        return null;
    }

    public function kiemTraPhongTrong(array $params = [])
    {
        // TODO: kiemTraPhongTrong
        return null;
    }

}
