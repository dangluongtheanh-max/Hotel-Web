<?php

namespace App\LeTan\Services;

use App\Phong\Services\PhongService;

/**
 * Service (business logic) cho module LeTan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class LeTanService
{
    private PhongService $phongService;

    public function __construct(PhongService $phongService)
    {
        $this->phongService = $phongService;
    }

    public function xemDSPhong(array $params = [])
    {
        return $this->phongService->xemDSPhong($params);
    }

    public function xemCTPhong(array $params = [])
    {
        return $this->phongService->xemCTPhong($params);
    }

    public function timKiemPhong(array $params = [])
    {
        return $this->phongService->timKiemPhong($params);
    }

    public function kiemTraPhongTrong(array $params = [])
    {
        return $this->phongService->kiemTraPhongTrong($params);
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
