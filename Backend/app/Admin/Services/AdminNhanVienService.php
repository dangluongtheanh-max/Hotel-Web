<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminNhanVienRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service quản lý nhân sự, lễ tân và tài khoản.
 */
class AdminNhanVienService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminNhanVienRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getNhanVienRepo();
        } else {
            $this->repository = new AdminNhanVienRepository();
        }
    }

    public function xemDSNhanVien(array $params = []): array
    {
        $list = $this->repository->getAllNhanVien($params);
        return [
            'status' => 'success',
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function xemDSLeTan(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $list = $this->repository->getAllLeTan(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }
    public function timKiemLeTan(array $params = []): array
    {
        return $this->xemDSLeTan($params);
    }

    public function xemCTLeTan(array $params = []): array
    {
        $id = trim((string)($params['MaLeTan'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Vui lòng cung cấp MaLeTan.'];
        }

        $leTan = $this->repository->findLeTanById($id);
        if ($leTan === null) {
            return ['status' => 'error', 'statusCode' => 404, 'message' => "Không tìm thấy lễ tân '{$id}'."];
        }

        return ['status' => 'success', 'data' => $leTan];
    }

    public function themLeTan(array $params = []): array
    {
        $hoTen = trim((string)($params['HoTen'] ?? $params['hoTen'] ?? ''));
        $sdt = trim((string)($params['SDT'] ?? $params['sdt'] ?? ''));
        $email = trim((string)($params['Email'] ?? $params['email'] ?? ''));
        $tenDangNhap = trim((string)($params['TenDangNhap'] ?? $params['tenDangNhap'] ?? ''));
        $matKhau = (string)($params['MatKhau'] ?? $params['matKhau'] ?? '123456');
        $maLeTan = trim((string)($params['MaLeTan'] ?? $params['maLeTan'] ?? ''));

        if ($hoTen === '' || $sdt === '' || $email === '' || $tenDangNhap === '') {
            return [
                'status' => 'error',
                'statusCode' => 400,
                'message' => 'Vui lòng cung cấp HoTen, SDT, Email và TenDangNhap.',
            ];
        }
        if (strlen($hoTen) > 100 || strlen($sdt) > 20 || strlen($email) > 255 || strlen($tenDangNhap) > 100) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Một hoặc nhiều trường vượt quá độ dài cho phép.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Email không hợp lệ.'];
        }
        if (strlen($matKhau) < 6) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.'];
        }
        if ($this->repository->leTanAccountValueExists('TenDangNhap', $tenDangNhap)) {
            return ['status' => 'error', 'statusCode' => 409, 'message' => 'Tên đăng nhập đã tồn tại.'];
        }
        if ($this->repository->leTanAccountValueExists('Email', $email)) {
            return ['status' => 'error', 'statusCode' => 409, 'message' => 'Email đã được sử dụng.'];
        }

        $createData = [
            'HoTen' => $hoTen,
            'SDT' => $sdt,
            'Email' => $email,
            'TenDangNhap' => $tenDangNhap,
            'MatKhau' => $matKhau,
        ];
        if (!empty($maLeTan)) {
            $createData['MaLeTan'] = $maLeTan;
        }

        $leTan = $this->repository->createLeTan($createData);

        return [
            'status' => 'success',
            'message' => 'Thêm lễ tân và tài khoản thành công.',
            'data' => $leTan,
        ];
    }

    public function capNhatThongTinLeTan(array $params = []): array
    {
        $id = trim((string)($params['MaLeTan'] ?? $params['maLeTan'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Vui lòng cung cấp MaLeTan.'];
        }

        $current = $this->repository->findLeTanById($id);
        if ($current === null) {
            return ['status' => 'error', 'statusCode' => 404, 'message' => "Không tìm thấy lễ tân '{$id}'."];
        }

        $updates = [];
        if (isset($params['HoTen']) || isset($params['hoTen'])) {
            $updates['HoTen'] = trim($params['HoTen'] ?? $params['hoTen']);
        }
        if (isset($params['SDT']) || isset($params['sdt'])) {
            $updates['SDT'] = trim($params['SDT'] ?? $params['sdt']);
        }
        if (isset($params['Email']) || isset($params['email'])) {
            $updates['Email'] = trim($params['Email'] ?? $params['email']);
        }
        if (!empty($params['TenDangNhap']) || !empty($params['tenDangNhap'])) {
            $updates['TenDangNhap'] = trim($params['TenDangNhap'] ?? $params['tenDangNhap']);
        }
        if (!empty($params['MatKhau']) || !empty($params['matKhau'])) {
            $updates['MatKhau'] = (string)($params['MatKhau'] ?? $params['matKhau']);
        }
        if (isset($params['TrangThai']) || isset($params['trangThai'])) {
            $updates['TrangThaiTaiKhoan'] = (string)($params['TrangThai'] ?? $params['trangThai']);
        }
        if (isset($params['TrangThaiTaiKhoan']) || isset($params['trangThaiTaiKhoan'])) {
            $updates['TrangThaiTaiKhoan'] = (string)($params['TrangThaiTaiKhoan'] ?? $params['trangThaiTaiKhoan']);
        }

        if (empty($updates)) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Vui lòng cung cấp thông tin cần cập nhật.'];
        }

        $hoTen = $updates['HoTen'] ?? $current['HoTen'];
        $sdt = $updates['SDT'] ?? $current['SDT'];
        $email = $updates['Email'] ?? $current['Email'];
        if (strlen($hoTen) > 100 || strlen($sdt) > 20 || strlen($email) > 255) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Một hoặc nhiều trường vượt quá độ dài cho phép.'];
        }
        if (array_key_exists('Email', $updates) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'statusCode' => 400, 'message' => 'Email không hợp lệ.'];
        }
        if (
            array_key_exists('Email', $updates)
            && $email !== $current['Email']
            && $this->repository->leTanAccountValueExists('Email', $email)
        ) {
            return ['status' => 'error', 'statusCode' => 409, 'message' => 'Email đã được sử dụng.'];
        }

        $this->repository->updateLeTan($id, $updates);
        return [
            'status' => 'success',
            'message' => 'Cập nhật thông tin lễ tân thành công.',
            'data' => $this->repository->findLeTanById($id),
        ];
    }

    public function timKiemNhanVien(array $params = []): array
    {
        $keyword = strtolower(trim($params['keyword'] ?? ''));
        $list = $this->repository->getAllNhanVien($params);

        if ($keyword !== '') {
            $list = array_values(array_filter($list, function ($item) use ($keyword) {
                return str_contains(strtolower($item['HoTen']), $keyword)
                    || str_contains(strtolower($item['MaNV']), $keyword)
                    || str_contains(strtolower($item['SDT'] ?? ''), $keyword)
                    || str_contains(strtolower($item['TenDangNhap']), $keyword);
            }));
        }

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function xemCTNhanVien(array $params = []): array
    {
        $id = $params['id'] ?? ($params['MaNV'] ?? '');
        if (empty($id)) {
            return ['status' => 'error', 'message' => 'Thiếu mã nhân viên'];
        }

        $item = $this->repository->findNhanVienById($id);
        if (!$item) {
            return ['status' => 'error', 'message' => "Không tìm thấy nhân viên '{$id}'"];
        }

        return ['status' => 'success', 'data' => $item];
    }

    public function capNhatThongTinNhanVien(array $params = []): array
    {
        $id = $params['id'] ?? ($params['MaNV'] ?? '');
        if (empty($id)) {
            return ['status' => 'error', 'message' => 'Thiếu mã nhân viên'];
        }

        return [
            'status' => 'success',
            'message' => "Cập nhật thông tin nhân viên '{$id}' thành công",
        ];
    }

    public function themNhanVien(array $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Thêm nhân viên thành công',
            'data' => $params,
        ];
    }

    public function sinhMaLeTan(): array
    {
        return [
            'status' => 'success',
            'maLeTan' => $this->repository->generateMaLeTan(),
        ];
    }

    public function doiTrangThaiLeTan(string $maLeTan): array
    {
        if (empty($maLeTan)) {
            return ['status' => 'error', 'message' => 'Mã lễ tân không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiLeTan($maLeTan);
            $msg = ($item['TrangThaiTaiKhoan'] === 'HoatDong')
                ? 'Đã mở khóa hoạt động tài khoản lễ tân.'
                : 'Đã khóa tài khoản lễ tân thành công.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

}
