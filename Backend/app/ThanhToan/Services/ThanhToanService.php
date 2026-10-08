<?php

namespace App\ThanhToan\Services;

use App\ThanhToan\Models\ThanhToan;
use App\ThanhToan\Repositories\ThanhToanRepository;
use Shared\Helper;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service (business logic) cho module ThanhToan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class ThanhToanService
{
    private ThanhToanRepository $repository;

    public function __construct(ThanhToanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function taoThanhToan(array $params = []): array
    {
        if (empty($params['MaHD'])) {
            throw new InvalidArgumentException('Mã hóa đơn (MaHD) là bắt buộc.');
        }

        if (!isset($params['SoTien']) || (float)$params['SoTien'] <= 0) {
            throw new InvalidArgumentException('Số tiền thanh toán phải lớn hơn 0.');
        }

        $hinhThuc = $params['HinhThuc'] ?? 'ChuyenKhoan';
        if (!in_array($hinhThuc, ['ChuyenKhoan', 'TienMat'], true)) {
            throw new InvalidArgumentException('Hình thức thanh toán phải là ChuyenKhoan hoặc TienMat.');
        }

        $maTT = !empty($params['MaThanhToan']) ? $params['MaThanhToan'] : Helper::generateId('TT');
        $trangThai = $params['TrangThaiThanhToan'] ?? 'ThanhCong';
        $thoiGianStr = $params['ThoiGianThanhToan'] ?? Helper::now();
        $thoiGian = new DateTimeImmutable($thoiGianStr);

        $thanhToan = new ThanhToan(
            $maTT,
            (float)$params['SoTien'],
            $thoiGian,
            $hinhThuc,
            $trangThai,
            $params['MaHD']
        );

        $success = $this->repository->saveThanhToan($thanhToan);
        if (!$success) {
            throw new RuntimeException('Không thể lưu giao dịch thanh toán.');
        }

        return $thanhToan->toArray();
    }

    public function xemThanhToan(array $params = [])
    {
        if (!empty($params['MaThanhToan'])) {
            $tt = $this->repository->findThanhToanById($params['MaThanhToan']);
            return $tt ? $tt->toArray() : null;
        }

        if (!empty($params['MaHD'])) {
            $list = $this->repository->findByMaHD($params['MaHD']);
            return array_map(fn(ThanhToan $item) => $item->toArray(), $list);
        }

        $list = $this->repository->getAllThanhToan($params);
        return array_map(fn(ThanhToan $item) => $item->toArray(), $list);
    }

    public function capNhatTrangThaiThanhToan(array $params = []): array
    {
        if (empty($params['MaThanhToan'])) {
            throw new InvalidArgumentException('Mã thanh toán (MaThanhToan) là bắt buộc.');
        }

        if (empty($params['TrangThaiThanhToan'])) {
            throw new InvalidArgumentException('Trạng thái thanh toán (TrangThaiThanhToan) là bắt buộc.');
        }

        $tt = $this->repository->findThanhToanById($params['MaThanhToan']);
        if (!$tt) {
            throw new InvalidArgumentException('Không tìm thấy giao dịch thanh toán với mã: ' . $params['MaThanhToan']);
        }

        $this->repository->updateTrangThai($params['MaThanhToan'], $params['TrangThaiThanhToan']);
        $tt->setTrangThaiThanhToan($params['TrangThaiThanhToan']);

        return $tt->toArray();
    }
}
