<?php

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanRepository;

/**
 * Service (business logic) cho module ThanhToan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class ThanhToanService
{
    private ThanhToanRepository $repository;

    public function __construct(ThanhToanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function taoThanhToan(array $params = [])
    {
        // TODO: taoThanhToan
        return null;
    }

    public function xemThanhToan(array $params = [])
    {
        // TODO: xemThanhToan
        return null;
    }

    public function capNhatTrangThaiThanhToan(array $params = [])
    {
        // TODO: capNhatTrangThaiThanhToan
        return null;
    }

}
