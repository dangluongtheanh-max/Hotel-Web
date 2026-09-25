<?php

namespace App\DanhGia\Services;

use App\DanhGia\Repositories\DanhGiaRepository;

/**
 * Service (business logic) cho module DanhGia.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class DanhGiaService
{
    private DanhGiaRepository $repository;

    public function __construct(DanhGiaRepository $repository)
    {
        $this->repository = $repository;
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

    public function xemDanhGiaTheoPhieu(array $params = [])
    {
        // TODO: xemDanhGiaTheoPhieu
        return null;
    }

}
