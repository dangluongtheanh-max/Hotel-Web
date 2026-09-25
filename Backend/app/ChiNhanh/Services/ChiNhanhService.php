<?php

namespace App\ChiNhanh\Services;

use App\ChiNhanh\Repositories\ChiNhanhRepository;

/**
 * Service (business logic) cho module ChiNhanh.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class ChiNhanhService
{
    private ChiNhanhRepository $repository;

    public function __construct(ChiNhanhRepository $repository)
    {
        $this->repository = $repository;
    }

    public function xemDSChiNhanh(array $params = [])
    {
        // TODO: xemDSChiNhanh
        return null;
    }

    public function xemChiTietChiNhanh(array $params = [])
    {
        // TODO: xemChiTietChiNhanh
        return null;
    }

    public function themChiNhanh(array $params = [])
    {
        // TODO: themChiNhanh
        return null;
    }

    public function capNhatThongTinChiNhanh(array $params = [])
    {
        // TODO: capNhatThongTinChiNhanh
        return null;
    }

    public function timKiemChiNhanh(array $params = [])
    {
        // TODO: timKiemChiNhanh
        return null;
    }

}
