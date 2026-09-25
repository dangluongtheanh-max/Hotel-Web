<?php

namespace App\Admin\Controllers;

use App\Admin\Services\AdminService;
use Shared\Response;

/**
 * Controller cho module Admin.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class AdminController
{
    private AdminService $service;

    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    public function xemDSNhanVien($request, $response)
    {
        $result = $this->service->xemDSNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function timKiemNhanVien($request, $response)
    {
        $result = $this->service->timKiemNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function xemCTNhanVien($request, $response)
    {
        $result = $this->service->xemCTNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function capNhatThongTinNhanVien($request, $response)
    {
        $result = $this->service->capNhatThongTinNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function themNhanVien($request, $response)
    {
        $result = $this->service->themNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function moTaiKhoanNhanVien($request, $response)
    {
        $result = $this->service->moTaiKhoanNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function khoaTaiKhoanNhanVien($request, $response)
    {
        $result = $this->service->khoaTaiKhoanNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function thongKeSoLuongNhanVien($request, $response)
    {
        $result = $this->service->thongKeSoLuongNhanVien($request->all());
        return Response::json($response, $result);
    }

    public function thongKeDoanhThuChiNhanh($request, $response)
    {
        $result = $this->service->thongKeDoanhThuChiNhanh($request->all());
        return Response::json($response, $result);
    }

    public function thongKeSoLuongPhong($request, $response)
    {
        $result = $this->service->thongKeSoLuongPhong($request->all());
        return Response::json($response, $result);
    }

}
