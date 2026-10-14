<?php

namespace App\DichVu\Services;

use App\DichVu\Repositories\DichVuRepository;

/**
 * Service (business logic) cho module DichVu.
 * Cac module khac (BE1 khi dat dich vu, BE4 khi lap hoa don)
 * PHAI goi qua Service nay de giu tinh dong goi.
 */
class DichVuService
{
    private DichVuRepository $repository;

    public function __construct(?DichVuRepository $repository = null)
    {
        $this->repository = $repository ?? new DichVuRepository();
    }

    /**
     * Lay toan bo danh muc dich vu kem cac mat hang chi tiet.
     */
    public function xemDSDichVu(array $params = []): array
    {
        $list = $this->repository->getAllDichVuWithDetails();
        return [
            'status' => 'success',
            'total_groups' => count($list),
            'services' => $list
        ];
    }

    /**
     * Tim kiem dich vu theo tu khoa.
     */
    public function timKiemDichVu(array $params = []): array
    {
        $keyword = trim($params['keyword'] ?? '');
        if ($keyword === '') {
            return $this->xemDSDichVu($params);
        }

        $results = $this->repository->timKiemDichVu($keyword);
        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total_found' => count($results),
            'items' => $results
        ];
    }

    /**
     * Ham noi bo danh cho BE1 va luong Billing/HoaDon:
     * Kiem tra mat hang co ton tai, co du kho, va tra ve thong tin DonGia snapshot.
     */
    public function kiemTraVaLayGia(string $maCTDV, int $soLuong = 1): array
    {
        $item = $this->repository->findChiTietById($maCTDV);
        if (!$item) {
            return [
                'hopLe' => false,
                'message' => "Khong tim thay dich vu voi ma: {$maCTDV}"
            ];
        }

        // Kiem tra ton kho (neu la mat hang co gioi han kho < 9999)
        if ($item['SoLuongTon'] < 9999 && $item['SoLuongTon'] < $soLuong) {
            return [
                'hopLe' => false,
                'message' => "Dich vu '{$item['TenCTDV']}' chi con {$item['SoLuongTon']} trong kho (yeu cau: {$soLuong})"
            ];
        }

        return [
            'hopLe' => true,
            'item' => $item,
            'donGia' => (float)$item['DonGia'],
            'thanhTien' => (float)$item['DonGia'] * $soLuong
        ];
    }

    /**
     * Dat dich vu va tru kho (khi khach su dung).
     */
    public function datDichVu(array $params = []): array
    {
        $maCTDV = $params['MaCTDV'] ?? '';
        $soLuong = (int)($params['SoLuong'] ?? 1);
        $moTa = $params['MoTa'] ?? ''; // Thong tin phuong an A: gio don, chuyen bay, ghi chu

        if (empty($maCTDV) || $soLuong <= 0) {
            return [
                'status' => 'error',
                'message' => 'Vui long cung cap MaCTDV hop le va SoLuong > 0'
            ];
        }

        $check = $this->kiemTraVaLayGia($maCTDV, $soLuong);
        if (!$check['hopLe']) {
            return [
                'status' => 'error',
                'message' => $check['message']
            ];
        }

        // Neu la mat hang can tru kho vat ly
        $item = $check['item'];
        if ($item['SoLuongTon'] < 9999) {
            $truThanhCong = $this->repository->truTonKho($maCTDV, $soLuong);
            if (!$truThanhCong) {
                return [
                    'status' => 'error',
                    'message' => 'Tru kho that bai, so luong trong kho khong du'
                ];
            }
        }

        return [
            'status' => 'success',
            'message' => 'Ghi nhan dich vu thanh cong',
            'data' => [
                'MaCTDV' => $maCTDV,
                'TenCTDV' => $item['TenCTDV'],
                'DonGia' => $check['donGia'],
                'SoLuong' => $soLuong,
                'ThanhTien' => $check['thanhTien'],
                'MoTa' => $moTa
            ]
        ];
    }

    /**
     * Admin them mot chi tiet dich vu moi.
     */
    public function themDichVu(array $params = []): array
    {
        $maCTDV = trim($params['MaCTDV'] ?? '');
        $maDV = trim($params['MaDV'] ?? '');
        $tenCTDV = trim($params['TenCTDV'] ?? '');
        $donGia = (float)($params['DonGia'] ?? 0);
        $soLuongTon = (int)($params['SoLuongTon'] ?? 0);

        if (empty($maCTDV) || empty($maDV) || empty($tenCTDV)) {
            return [
                'status' => 'error',
                'message' => 'Thieu thong tin bat buoc: MaCTDV, MaDV, TenCTDV'
            ];
        }

        if ($donGia < 0 || $soLuongTon < 0) {
            return [
                'status' => 'error',
                'message' => 'Don gia va so luong ton phai lon hon hoac bang 0'
            ];
        }

        // Kiem tra nhom DV co ton tai chua
        $group = $this->repository->findDichVuById($maDV);
        if (!$group) {
            return [
                'status' => 'error',
                'message' => "Nhom dich vu {$maDV} khong ton tai"
            ];
        }

        $success = $this->repository->themChiTietDichVu([
            'MaCTDV' => $maCTDV,
            'MaDV' => $maDV,
            'TenCTDV' => $tenCTDV,
            'DonGia' => $donGia,
            'SoLuongTon' => $soLuongTon
        ]);

        if (!$success) {
            return ['status' => 'error', 'message' => 'Khong the them dich vu, MaCTDV co the da bi trung'];
        }

        return [
            'status' => 'success',
            'message' => 'Them dich vu moi thanh cong',
            'data' => [
                'MaCTDV' => $maCTDV,
                'TenCTDV' => $tenCTDV,
                'DonGia' => $donGia,
                'SoLuongTon' => $soLuongTon
            ]
        ];
    }

    /**
     * Admin cap nhat thong tin dich vu (DonGia, SoLuongTon, TenCTDV).
     */
    public function capNhatDichVu(array $params = []): array
    {
        $maCTDV = trim($params['MaCTDV'] ?? '');
        if (empty($maCTDV)) {
            return ['status' => 'error', 'message' => 'Thieu MaCTDV can cap nhat'];
        }

        $item = $this->repository->findChiTietById($maCTDV);
        if (!$item) {
            return ['status' => 'error', 'message' => "Khong tim thay dich vu {$maCTDV}"];
        }

        $updateData = [];
        if (isset($params['TenCTDV'])) {
            $updateData['TenCTDV'] = trim($params['TenCTDV']);
        }
        if (isset($params['DonGia'])) {
            $donGia = (float)$params['DonGia'];
            if ($donGia < 0) {
                return ['status' => 'error', 'message' => 'Don gia khong duoc am'];
            }
            $updateData['DonGia'] = $donGia;
        }
        if (isset($params['SoLuongTon'])) {
            $soLuongTon = (int)$params['SoLuongTon'];
            if ($soLuongTon < 0) {
                return ['status' => 'error', 'message' => 'So luong ton khong duoc am'];
            }
            $updateData['SoLuongTon'] = $soLuongTon;
        }

        $success = $this->repository->capNhatChiTietDichVu($maCTDV, $updateData);
        return [
            'status' => $success ? 'success' : 'error',
            'message' => $success ? 'Cap nhat dich vu thanh cong' : 'Khong co thay doi nao duoc cap nhat',
            'data' => array_merge($item, $updateData)
        ];
    }

    /**
     * Xoa hoac ngung cung cap dich vu.
     */
    public function xoaDichVu(array $params = []): array
    {
        // De bao toan du lieu quan he khoa ngoai, cap nhat ton kho ve 0
        $maCTDV = trim($params['MaCTDV'] ?? '');
        if (empty($maCTDV)) {
            return ['status' => 'error', 'message' => 'Thieu MaCTDV'];
        }

        $this->repository->capNhatChiTietDichVu($maCTDV, ['SoLuongTon' => 0]);
        return [
            'status' => 'success',
            'message' => "Dich vu {$maCTDV} da duoc set so luong ton ve 0 (tam ngung cung cap)"
        ];
    }
}
