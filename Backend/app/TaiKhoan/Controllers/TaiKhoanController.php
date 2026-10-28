<?php

declare(strict_types=1);

namespace App\TaiKhoan\Controllers;

use App\TaiKhoan\Services\TaiKhoanService;
use App\TaiKhoan\Services\QuenMatKhauService;
use Shared\Response;
use Exception;

/**
 * Controller cho module TaiKhoan.
 * Chỉ nhận request, gọi Service xử lý, trả về response - không chứa business logic.
 */
class TaiKhoanController
{
    private TaiKhoanService $service;

    public function __construct(
        TaiKhoanService $service,
        ?QuenMatKhauService $quenMatKhauService = null
    ) {
        $this->service = $service;
    }

    public function dangNhap($request, $response)
    {
        try {
            $result = $this->service->dangNhap($request->all());
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage());
        }
    }

    public function quenMatKhau($request, $response)
    {
        try {
            $result = $this->service->quenMatKhau($request->all());
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $result['message'] ?? 'Thao tác thành công.',
                'data'    => $result['data'] ?? null,
            ]);
            return $response;
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage());
        }
    }
}
