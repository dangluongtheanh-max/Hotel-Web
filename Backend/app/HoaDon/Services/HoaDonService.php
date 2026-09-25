<?php

namespace App\HoaDon\Services;

use App\HoaDon\Repositories\HoaDonRepository;

/**
 * Service (business logic) cho module HoaDon.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class HoaDonService
{
    private HoaDonRepository $repository;

    public function __construct(HoaDonRepository $repository)
    {
        $this->repository = $repository;
    }

    public function taoHoaDon(array $params = [])
    {
        // TODO: taoHoaDon
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

    public function timHoaDon(array $params = [])
    {
        // TODO: timHoaDon
        return null;
    }

}
