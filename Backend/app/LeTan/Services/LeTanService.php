<?php

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanRepository;

/**
 * Service (business logic) cho module LeTan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class LeTanService
{
    private LeTanRepository $repository;

    public function __construct(LeTanRepository $repository)
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

    public function timKiemPhong(array $params = [])
    {
        // TODO: timKiemPhong
        return null;
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
