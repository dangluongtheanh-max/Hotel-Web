<?php

namespace App\DichVu\Controllers;

use App\DichVu\Services\DichVuService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module DichVu.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class DichVuController
{
    private DichVuService $service;

    public function __construct(?DichVuService $service = null)
    {
        $this->service = $service ?? new DichVuService();
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

    public function xemDSDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSDichVu($params);
        return Response::json($response, $result, 200);
    }

    public function datDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->datDichVu($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function timKiemDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timKiemDichVu($params);
        return Response::json($response, $result, 200);
    }

    public function themDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themDichVu($params);
        $status = ($result['status'] === 'success') ? 201 : 400;
        return Response::json($response, $result, $status);
    }

    public function capNhatDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->capNhatDichVu($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function xoaDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xoaDichVu($params);
        return Response::json($response, $result, 200);
    }

    public function ui($request = null, $response = null)
    {
        $viewPath = __DIR__ . '/../Views/index.html';
        if (file_exists($viewPath)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewPath);
            exit;
        }
        return Response::json($response, ['message' => 'View not found'], 404);
    }
}
