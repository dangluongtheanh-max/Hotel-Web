<?php

declare(strict_types=1);

namespace App\DanhGia\Services;

use App\DanhGia\Models\DanhGia;
use App\DanhGia\Repositories\DanhGiaXuLyRepository;
use App\DanhGia\Repositories\DanhGiaRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

/**
 * Service xử lý toàn bộ logic nghiệp vụ cho module Đánh Giá.
 * Gồm 2 phần nghiệp vụ chính: Tra cứu dữ liệu (Query) và Thao tác dữ liệu (Command).
 */
class DanhGiaXuLyService
{
    private DanhGiaXuLyRepository $repository;
    private KhachHangRepository $khachHangRepo;

    public function __construct(
        $repository,
        ?KhachHangRepository $khachHangRepo = null
    ) {
        if ($repository instanceof DanhGiaXuLyRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof DanhGiaRepository) {
            $this->repository = $repository->getXuLyRepository();
        } else {
            $this->repository = new DanhGiaXuLyRepository();
        }

        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & HIỂN THỊ ĐÁNH GIÁ (READ / QUERY)
    // =========================================================================

    /**
     * Tra cứu danh sách đánh giá theo mã phiếu nhận phòng.
     */
    public function xemDanhGiaTheoPhieu(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? $params['MaPhieu'] ?? '');
        if (empty($maPhieu)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Thiếu mã phiếu nhận phòng.'
            ];
        }

        $data = $this->repository->getDanhGiaByPhieu($maPhieu);
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $data
        ];
    }

    /**
     * Tra cứu chi tiết một đánh giá theo mã đánh giá.
     */
    public function xemChiTietDanhGia(string $maDG): array
    {
        $dg = $this->repository->findDanhGiaById($maDG);
        if (!$dg) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Không tìm thấy đánh giá với mã đã cho.'
            ];
        }

        return [
            'status' => 'success',
            'code' => 200,
            'data' => $dg->toArray()
        ];
    }

    /**
     * Lấy danh sách đánh giá theo bộ lọc tổng hợp.
     */
    public function layDanhSachDanhGia(array $filters = []): array
    {
        $data = $this->repository->getAllDanhGia($filters);
        return [
            'status' => 'success',
            'code' => 200,
            'data' => $data
        ];
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC & XÁC THỰC NGHIỆP VỤ (WRITE / MUTATION)
    // =========================================================================

    /**
     * Thêm mới đánh giá phòng sau khi khách hàng đã hoàn tất trả phòng.
     */
    public function themDanhGia(array $params = []): array
    {
        $maTK = trim($params['maTK'] ?? $params['MaTK'] ?? '');
        $maPhieu = trim($params['maPhieu'] ?? $params['MaPhieu'] ?? '');
        $maPhong = trim($params['maPhong'] ?? $params['MaPhong'] ?? '');
        $soSao = isset($params['soSao']) ? (int)$params['soSao'] : (isset($params['SoSao']) ? (int)$params['SoSao'] : 5);
        $noiDung = trim($params['noiDung'] ?? $params['NoiDung'] ?? '');

        // 1. Xác thực thông tin khách hàng
        $maKH = '';
        if (!empty($maTK)) {
            $khachHang = $this->khachHangRepo->findByMaTK($maTK);
            if (!$khachHang) {
                return [
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'Tài khoản không hợp lệ hoặc chưa liên kết hồ sơ khách hàng.'
                ];
            }
            $maKH = $khachHang['MaKH'];
        } else {
            $maKH = trim($params['maKH'] ?? $params['MaKH'] ?? '');
        }

        if (empty($maKH)) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Bạn cần đăng nhập tài khoản khách hàng để thực hiện đánh giá.'
            ];
        }

        if (empty($maPhieu) || empty($maPhong)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Thiếu thông tin đánh giá: mã phiếu hoặc mã phòng.'
            ];
        }

        if ($soSao < 1 || $soSao > 5) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Số sao đánh giá phải là số nguyên từ 1 đến 5 sao.'
            ];
        }

        if (mb_strlen($noiDung) > 1000) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Nội dung nhận xét tối đa 1000 ký tự.'
            ];
        }

        // 2. Kiểm tra điều kiện phòng và quyền sở hữu phiếu
        $dieuKien = $this->repository->kiemTraDieuKienDanhGia($maPhieu, $maPhong);
        if (!$dieuKien) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Phòng này không tồn tại trong phiếu nhận phòng đã chọn.'
            ];
        }

        if ($dieuKien['MaKH'] !== $maKH) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Bạn không có quyền đánh giá phiếu đặt phòng của khách hàng khác.'
            ];
        }

        if ($dieuKien['TrangThaiPhong'] !== 'DaTraPhong') {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Phòng chưa hoàn tất thủ tục trả phòng (check-out), chưa đủ điều kiện để đánh giá.'
            ];
        }

        // 3. Kiểm tra xem phòng trong phiếu này đã được đánh giá chưa (chống đánh giá trùng lặp)
        $existing = $this->repository->findByPhieuVaPhong($maPhieu, $maPhong);
        if ($existing) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Phòng này trong phiếu nhận phòng đã được đánh giá trước đó.'
            ];
        }

        // 4. Lưu đánh giá mới
        $maDG = $params['maDanhGia'] ?? $params['MaDanhGia'] ?? $this->repository->generateMaDanhGia();
        $ngayDG = new \DateTime();

        $danhGia = new DanhGia(
            $maDG,
            $soSao,
            $noiDung,
            $ngayDG,
            $maKH,
            $maPhieu,
            $maPhong
        );

        $ok = $this->repository->saveDanhGia($danhGia);
        if ($ok) {
            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Đánh giá phòng thành công.',
                'data' => $danhGia->toArray()
            ];
        }

        return [
            'status' => 'error',
            'code' => 500,
            'message' => 'Lỗi khi lưu đánh giá vào cơ sở dữ liệu.'
        ];
    }

    /**
     * Xóa đánh giá theo mã.
     */
    public function xoaDanhGia(array $params = []): array
    {
        $maDG = trim($params['MaDanhGia'] ?? $params['maDanhGia'] ?? '');
        if (empty($maDG)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Thiếu mã đánh giá.'
            ];
        }

        $ok = $this->repository->deleteDanhGia($maDG);
        return [
            'status' => $ok ? 'success' : 'error',
            'code' => $ok ? 200 : 400,
            'message' => $ok ? 'Xóa đánh giá thành công.' : 'Không tìm thấy hoặc không thể xóa đánh giá.'
        ];
    }
}
