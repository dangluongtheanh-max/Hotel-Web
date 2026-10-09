<?php

namespace App\ThanhToan\Controllers;

use App\ThanhToan\Services\ThanhToanService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module ThanhToan.
 * Tiếp nhận request HTTP, điều phối Service và trả về JSON hoặc HTML Dashboard.
 */
class ThanhToanController
{
    private ThanhToanService $service;

    public function __construct(?ThanhToanService $service = null)
    {
        $this->service = $service ?? new ThanhToanService();
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
     * API: Khởi tạo giao dịch thanh toán cho hóa đơn (sinh mã VietQR Napas 247).
     * Endpoint: POST /thanhtoan/taothanhtoan hoặc POST /thanhtoan/tao
     */
    public function taoThanhToan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->taoThanhToan($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Tra cứu trạng thái thanh toán của hóa đơn (hỗ trợ Polling 2s/lần).
     * Endpoint: GET /thanhtoan/xem?MaHD=... hoặc POST /thanhtoan/xemthanhtoan
     */
    public function xemThanhToan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemThanhToan($params);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Nhận Webhook biến động số dư từ Ngân Hàng Napas 247 (MBBank).
     * Endpoint: POST /thanhtoan/webhook
     */
    public function xuLyWebhook($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xuLyWebhook($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Mô phỏng ngân hàng báo có (Dành cho việc test & báo cáo bài tập lớn).
     * Endpoint: POST /thanhtoan/mophong
     */
    public function moPhongChuyenKhoan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maHD = $params['MaHD'] ?? '';
        $soTien = (float)($params['SoTien'] ?? 0);
        $noiDung = $params['NoiDung'] ?? "THANH TOAN {$maHD}";

        $result = $this->service->xuLyWebhook([
            'content' => $noiDung,
            'amount' => $soTien,
        ]);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Gửi yêu cầu hoàn tiền (Mô hình 1: Khách điền STK nhận lại tiền).
     * Endpoint: POST /thanhtoan/hoantien
     */
    public function yeuCauHoanTien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->yeuCauHoanTien($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Lấy danh sách toàn bộ các giao dịch thanh toán.
     * Endpoint: GET /thanhtoan/danhsach
     */
    public function danhSachThanhToan($request = null, $response = null)
    {
        $result = $this->service->layDanhSachThanhToan();
        return Response::json($response, $result, 200);
    }

    /**
     * Giao diện trực quan Dashboard Cổng Thanh Toán & Quét VietQR MBBank.
     * Endpoint: GET /thanhtoan
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
        echo "Giao diện Thanh Toán đang được khởi tạo...";
        exit;
    }
}
