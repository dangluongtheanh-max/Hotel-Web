<?php

namespace App\TaiKhoan\Services;

use App\TaiKhoan\Repositories\TaiKhoanRepository;

/**
 * Service (business logic) cho module TaiKhoan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class TaiKhoanService
{
    private TaiKhoanRepository $repository;

    public function __construct(TaiKhoanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function dangNhap(array $params = [])
    {
        // TODO: dangNhap
        return null;
    }

    public function quenMatKhau(array $params = [])
    {
        // TODO: quenMatKhau
        return null;
    }

}
