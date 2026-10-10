<?php

namespace App\Admin\Controllers;

use App\Admin\Services\AdminService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho phân hệ Admin & Báo Cáo Thống Kê (BE4).
 * Tiếp nhận request HTTP, điều phối AdminService xử lý và trả về JSON hoặc HTML Dashboard.
 */
class AdminController
{
    private AdminService $service;

    public function __construct(?AdminService $service = null)
    {
        $this->service = $service ?? new AdminService();
    }

    private function getParams($request): array
    {
        if ($request instanceof Request) {
            return $request->all();
        }
        if (is_array($request)) {
            return $request;
        }
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        return Request::capture()->all();
    }

    /* =========================================================================
     * THỐNG KÊ & BÁO CÁO (BE4)
     * ========================================================================= */

    /**
     * API: Thống kê doanh thu theo thời gian, hình thức và cơ cấu nguồn thu.
     * Endpoint: POST /admin/thongkedoanhthu hoặc GET /admin/thongkedoanhthu
     */
    public function thongKeDoanhThu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDoanhThu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê số lượng phòng và tình trạng phòng hiện tại.
     * Endpoint: POST /admin/thongkesoluongphong hoặc GET /admin/thongkesoluongphong
     */
    public function thongKeSoLuongPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongPhong($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê số lượng phiếu đặt phòng theo trạng thái và kênh đặt.
     * Endpoint: POST /admin/thongkesoluongphieu hoặc GET /admin/thongkesoluongphieu
     */
    public function thongKeSoLuongPhieu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongPhieu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê dịch vụ sử dụng: lượt dùng, doanh thu từ dịch vụ, tồn kho.
     * Endpoint: POST /admin/thongkedichvu hoặc GET /admin/thongkedichvu
     */
    public function thongKeDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDichVu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê tổng quan KPI trang chủ Dashboard.
     * Endpoint: GET /admin/thongketongquan hoặc POST /admin/thongketongquan
     */
    public function thongKeTongQuan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeTongQuan($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê số lượng nhân sự.
     * Endpoint: POST /admin/thongkesoluongnhanvien
     */
    public function thongKeSoLuongNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongNhanVien($params);
        return Response::json($response, $result, 200);
    }

    /* =========================================================================
     * QUẢN LÝ NHÂN SỰ / LỄ TÂN (Admin Shared)
     * ========================================================================= */

    public function xemDSNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSLeTan($request = null, $response = null)
    {
        return $this->xemDSNhanVien($request, $response);
    }

    public function timKiemNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timKiemNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function timKiemLeTan($request = null, $response = null)
    {
        return $this->timKiemNhanVien($request, $response);
    }

    public function xemCTNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemCTNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function xemCTLeTan($request = null, $response = null)
    {
        return $this->xemCTNhanVien($request, $response);
    }

    public function capNhatThongTinNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->capNhatThongTinNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function capNhatThongTinLeTan($request = null, $response = null)
    {
        return $this->capNhatThongTinNhanVien($request, $response);
    }

    public function themNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function themLeTan($request = null, $response = null)
    {
        return $this->themNhanVien($request, $response);
    }

    public function moTaiKhoanNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->moTaiKhoanNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function moTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->moTaiKhoanNhanVien($request, $response);
    }

    public function khoaTaiKhoanNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->khoaTaiKhoanNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function khoaTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->khoaTaiKhoanNhanVien($request, $response);
    }

    /* =========================================================================
     * GIAO DIỆN TRỰC QUAN DASHBOARD THỐNG KÊ (BE4)
     * ========================================================================= */

    /**
     * Giao diện HTML trực quan Trung tâm Báo Cáo & Thống Kê BE4.
     * Endpoint: GET /admin hoặc GET /admin/dashboard
     */
    public function viewDashboard($request = null, $response = null)
    {
        $viewFile = __DIR__ . '/../Views/index.html';
        if (file_exists($viewFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewFile);
            exit;
        }

        $overview = $this->service->thongKeTongQuan();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($overview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
