<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminDichVuRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service quản lý danh mục & bảng giá chi tiết dịch vụ.
 */
class AdminDichVuService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminDichVuRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getDichVuRepo();
        } else {
            $this->repository = new AdminDichVuRepository();
        }
    }

    public function xemDSDichVu(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $maDV = trim((string)($params['maDV'] ?? $params['MaDV'] ?? ''));

        $filters = [];
        if ($keyword !== '') $filters['keyword'] = $keyword;
        if ($maDV !== '') $filters['maDV'] = $maDV;

        $list = $this->repository->getAllDichVu($filters);

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'maDV' => $maDV,
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function sinhMaChiTietDichVu(string $maDV): array
    {
        $code = $this->repository->generateMaChiTietDichVu($maDV);
        return [
            'status' => 'success',
            'maDV' => $maDV,
            'maCTDV' => $code,
        ];
    }

    public function themChiTietDichVu(array $data): array
    {
        $normalized = [
            'MaDV' => $data['MaDV'] ?? $data['maDV'] ?? 'DVDoAn',
            'MaCTDV' => $data['MaCTDV'] ?? $data['maCTDV'] ?? null,
            'TenCTDV' => trim($data['TenCTDV'] ?? $data['tenCTDV'] ?? ''),
            'DonGia' => $data['DonGia'] ?? $data['donGia'] ?? 0,
            'SoLuongTon' => $data['SoLuongTon'] ?? $data['soLuongTon'] ?? 0,
            'MoTa' => $data['MoTa'] ?? $data['moTa'] ?? null,
            'MoTaChiTiet' => $data['MoTaChiTiet'] ?? $data['moTaChiTiet'] ?? null,
            'TrangThai' => $data['TrangThai'] ?? $data['trangThai'] ?? 'DangCungCap',
        ];

        if (empty($normalized['TenCTDV'])) {
            return [
                'status' => 'error',
                'message' => 'Tên chi tiết dịch vụ không được để trống.',
            ];
        }

        try {
            $created = $this->repository->createChiTietDichVu($normalized);
            return [
                'status' => 'success',
                'message' => 'Thêm chi tiết dịch vụ thành công.',
                'data' => $created,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function suaChiTietDichVu(string $maCTDV, array $data): array
    {
        if (empty($maCTDV)) {
            $maCTDV = $data['MaCTDV'] ?? $data['maCTDV'] ?? '';
        }
        if (empty($maCTDV)) {
            return [
                'status' => 'error',
                'message' => 'Mã chi tiết dịch vụ không hợp lệ.',
            ];
        }

        $normalized = [];
        if (isset($data['TenCTDV']) || isset($data['tenCTDV'])) {
            $normalized['TenCTDV'] = trim($data['TenCTDV'] ?? $data['tenCTDV']);
        }
        if (isset($data['MaDV']) || isset($data['maDV'])) {
            $normalized['MaDV'] = trim($data['MaDV'] ?? $data['maDV']);
        }
        if (isset($data['DonGia']) || isset($data['donGia'])) {
            $normalized['DonGia'] = (float)($data['DonGia'] ?? $data['donGia']);
        }
        if (isset($data['SoLuongTon']) || isset($data['soLuongTon'])) {
            $normalized['SoLuongTon'] = (int)($data['SoLuongTon'] ?? $data['soLuongTon']);
        }
        if (isset($data['MoTa']) || isset($data['moTa'])) {
            $normalized['MoTa'] = $data['MoTa'] ?? $data['moTa'];
        }
        if (isset($data['MoTaChiTiet']) || isset($data['moTaChiTiet'])) {
            $normalized['MoTaChiTiet'] = $data['MoTaChiTiet'] ?? $data['moTaChiTiet'];
        }
        if (isset($data['TrangThai']) || isset($data['trangThai'])) {
            $normalized['TrangThai'] = $data['TrangThai'] ?? $data['trangThai'];
        }

        try {
            $updated = $this->repository->updateChiTietDichVu($maCTDV, $normalized);
            if (!$updated) {
                return [
                    'status' => 'error',
                    'message' => 'Cập nhật không thành công hoặc không có dữ liệu thay đổi.',
                ];
            }
            $item = $this->repository->findChiTietDichVuById($maCTDV);
            return [
                'status' => 'success',
                'message' => 'Cập nhật chi tiết dịch vụ thành công.',
                'data' => $item,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function doiTrangThaiChiTietDichVu(string $maCTDV): array
    {
        if (empty($maCTDV)) {
            return [
                'status' => 'error',
                'message' => 'Mã chi tiết dịch vụ không hợp lệ.',
            ];
        }

        try {
            $item = $this->repository->toggleTrangThaiChiTietDichVu($maCTDV);
            $msg = ($item['TrangThai'] === 'DangCungCap') ? 'Đã mở lại hoạt động dịch vụ.' : 'Đã chuyển dịch vụ sang trạng thái ngừng hoạt động.';
            return [
                'status' => 'success',
                'message' => $msg,
                'data' => $item,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
