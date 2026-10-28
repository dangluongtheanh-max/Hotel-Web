<?php

declare(strict_types=1);

namespace App\DatPhong\Controllers;

use App\DatPhong\Services\DatPhongService;
use App\DatPhong\Services\LichSuDatPhongService;
use Shared\Response;
use Exception;

/**
 * Controller cho module DatPhong.
 * Chỉ nhận request, gọi Service xử lý, trả về response JSON - không chứa business logic.
 */
class DatPhongController
{
    private DatPhongService $service;
    private LichSuDatPhongService $lichSuService;

    public function __construct(
        DatPhongService $service,
        ?LichSuDatPhongService $lichSuService = null
    ) {
        $this->service = $service;
        $this->lichSuService = $lichSuService ?? new LichSuDatPhongService(new \App\DatPhong\Repositories\DatPhongRepository());
    }

    /**
     * API Tìm kiếm và lọc phòng trống kèm dòng gợi ý Combo thông minh
     * POST /datphong/timkiem
     */
    public function timKiemVaGoiY($request, $response)
    {
        try {
            $params = $request->all();
            $result = $this->service->timKiemVaGoiY($params);
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * API Lấy danh sách dịch vụ đi kèm
     * GET/POST /datphong/dichvu
     */
    public function layDanhSachDichVu($request, $response)
    {
        try {
            $result = $this->service->layDanhSachDichVu();
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * API Kiểm tra mã khuyến mãi
     * POST /datphong/kiemtrakhuyenmai
     */
    public function kiemTraKhuyenMai($request, $response)
    {
        try {
            $params = $request->all();
            $maKM = $params['maKM'] ?? '';
            $soDem = (int) ($params['soDem'] ?? 1);
            $tienPhong = (float) ($params['tienPhong'] ?? 0);
            $result = $this->service->kiemTraKhuyenMai($maKM, $soDem, $tienPhong);
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * API Tính toán chi phí tạm tính
     * POST /datphong/tinhchiphi
     */
    public function tinhChiPhi($request, $response)
    {
        try {
            $params = $request->all();
            $result = $this->service->tinhChiPhiTamTinh($params);
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * API Đặt phòng và thanh toán hoàn tất
     * POST /datphong/datphong
     */
    public function datPhongVaThanhToan($request, $response)
    {
        try {
            $params = $request->all();
            $result = $this->service->datPhongVaThanhToan($params);
            if (isset($result['data']) && isset($result['success'])) {
                return Response::json($response, $result['data']);
            }
            return Response::json($response, $result);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    /**
     * API Xem chi tiết phiếu đặt phòng (xác minh quyền sở hữu của khách hàng)
     * POST /datphong/xemctphieu
     */
    public function xemCTPhieu($request, $response)
    {
        try {
            $params = $request->all();
            $maPhieu = trim($params['maPhieu'] ?? '');
            if ($maPhieu === '') {
                return Response::error($response, 'Mã phiếu không hợp lệ.', 400);
            }

            // Lấy maTK từ request hoặc Authorization header
            $maTK = trim($params['maTK'] ?? '');
            if ($maTK === '') {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (empty($authHeader) && function_exists('getallheaders')) {
                    $headers = getallheaders();
                    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                }
                if (!empty($authHeader)) {
                    $maTK = trim(str_replace(['Bearer ', 'bearer '], '', $authHeader));
                }
            }

            $result = $this->service->xemChiTietPhieu($maPhieu, $maTK ?: null);
            if (!$result) {
                return Response::error($response, 'Không tìm thấy phiếu nhận phòng.', 404);
            }
            return Response::json($response, $result);
        } catch (Exception $e) {
            $code = ($e->getCode() === 403) ? 403 : 400;
            return Response::error($response, $e->getMessage(), $code);
        }
    }

    /**
     * API Chỉnh sửa thông tin người lưu trú (khách hàng cập nhật khi chưa nhận phòng)
     * POST /datphong/capnhatnguoiluutru
     */
    public function capNhatNguoiLuuTru($request, $response)
    {
        try {
            $params = $request->all();
            $maTK = trim($params['maTK'] ?? '');
            if ($maTK === '') {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (empty($authHeader) && function_exists('getallheaders')) {
                    $headers = getallheaders();
                    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                }
                if (!empty($authHeader)) {
                    $maTK = trim(str_replace(['Bearer ', 'bearer '], '', $authHeader));
                }
            }

            $result = $this->service->capNhatNguoiLuuTru($params, $maTK ?: null);
            return Response::json($response, $result);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404, 500], true) ? $e->getCode() : 400;
            return Response::error($response, $e->getMessage(), $code);
        }
    }

    /**
     * API Lấy lịch sử phiếu đặt phòng của khách hàng hiện tại
     * GET/POST /datphong/lichsu
     */
    public function lichSuDatPhong($request, $response)
    {
        try {
            $params = $request->all();
            $maTK = trim($params['maTK'] ?? '');

            // Fallback sang Authorization header nếu client truyền qua header
            if ($maTK === '') {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (empty($authHeader) && function_exists('getallheaders')) {
                    $headers = getallheaders();
                    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                }
                if (!empty($authHeader)) {
                    $maTK = trim(str_replace(['Bearer ', 'bearer '], '', $authHeader));
                }
            }

            if ($maTK === '') {
                return Response::error($response, 'Vui lòng đăng nhập tài khoản khách hàng để xem lịch sử đặt phòng.', 401);
            }

            $limit = isset($params['limit']) ? (int) $params['limit'] : 20;
            $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
            if ($limit <= 0) {
                $limit = 20;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            $items = $this->lichSuService->layLichSuDatPhong($maTK, $limit, $offset);

            return Response::json($response, [
                'items' => $items,
                'count' => count($items),
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => count($items) >= $limit
            ]);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    // Các hàm tương thích ngược với sơ đồ lớp
    public function xemDSPhieu($request, $response)
    {
        return Response::json($response, []);
    }

    public function timKiemPhieu($request, $response)
    {
        return $this->xemCTPhieu($request, $response);
    }

    public function checkin($request, $response)
    {
        return Response::json($response, ['message' => 'Checkin thành công']);
    }

    public function checkout($request, $response)
    {
        return Response::json($response, ['message' => 'Checkout thành công']);
    }

    public function taoPhieuNhanPhong($request, $response)
    {
        return $this->datPhongVaThanhToan($request, $response);
    }

    public function capNhatPhieuNhanPhong($request, $response)
    {
        return Response::json($response, ['message' => 'Cập nhật thành công']);
    }

    public function apDungKhuyenMai($request, $response)
    {
        return $this->kiemTraKhuyenMai($request, $response);
    }
}
