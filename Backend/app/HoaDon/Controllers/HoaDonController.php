<?php

namespace App\HoaDon\Controllers;

use App\HoaDon\Services\HoaDonService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module HoaDon.
 * Tiếp nhận HTTP Request, gọi Service xử lý và trả về JSON hoặc HTML Dashboard.
 */
class HoaDonController
{
    private HoaDonService $service;

    public function __construct(?HoaDonService $service = null)
    {
        $this->service = $service ?? new HoaDonService();
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

    /**
     * API: Lập hóa đơn chính thức cho phiếu đặt phòng (Check-out).
     * Endpoint: POST /hoadon/tao hoặc POST /hoadon/taohoadon
     */
    public function taoHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->taoHoaDon($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Xem chi tiết hóa đơn (kèm danh sách phòng, dịch vụ, thuế).
     * Endpoint: GET /hoadon/xem?MaHD=... hoặc POST /hoadon/xemhoadon
     */
    public function xemHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maHD = $params['MaHD'] ?? ($params['id'] ?? '');
        $result = $this->service->xemChiTietHoaDon($maHD);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Xem trước bảng kê chi phí của phiếu trước khi bấm lập hóa đơn (Preview Folio).
     * Endpoint: POST /hoadon/preview hoặc GET /hoadon/preview?MaPhieu=...
     */
    public function previewHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maPhieu = $params['MaPhieu'] ?? '';
        $result = $this->service->tinhToanChiPhi($maPhieu);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Lấy danh sách toàn bộ hóa đơn.
     * Endpoint: GET /hoadon/danhsach
     */
    public function danhSachHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->layDanhSachHoaDon($params);
        return Response::json($response, $result, 200);
    }

    /**
     * Giao diện trực quan Dashboard hóa đơn & in phiếu Folio thanh toán.
     * Endpoint: GET /hoadon
     */
    public function viewDashboard($request = null, $response = null)
    {
        $viewFile = __DIR__ . '/../Views/index.html';
        if (file_exists($viewFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewFile);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "Giao diện Hóa Đơn đang được khởi tạo...";
        exit;
    }
}
