<?php

declare(strict_types=1);

namespace App\TaiKhoan\Services;

use App\TaiKhoan\Repositories\TaiKhoanRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service cho module TaiKhoan.
 * Đóng vai trò Facade xử lý đăng nhập, xác thực và tích hợp QuenMatKhauService.
 */
class TaiKhoanService
{
    private TaiKhoanRepository $repository;
    private QuenMatKhauService $quenMatKhauService;

    public function __construct(
        ?TaiKhoanRepository $repository = null,
        ?QuenMatKhauService $quenMatKhauService = null
    ) {
        $this->repository = $repository ?? new TaiKhoanRepository();
        $this->quenMatKhauService = $quenMatKhauService ?? new QuenMatKhauService();
    }

    public function getRepository(): TaiKhoanRepository
    {
        return $this->repository;
    }

    public function getQuenMatKhauService(): QuenMatKhauService
    {
        return $this->quenMatKhauService;
    }

    /**
     * Xác thực thông tin đăng nhập và trả về dữ liệu phiên làm việc
     */
    public function dangNhap(array $params = []): array
    {
        // 1. Nhận tham số đầu vào (hỗ trợ cả camelCase và PascalCase)
        $tenDangNhap = trim((string)($params['tenDangNhap'] ?? $params['TenDangNhap'] ?? ''));
        $matKhau = (string)($params['matKhau'] ?? $params['MatKhau'] ?? '');

        // 2. Kiểm tra dữ liệu đầu vào
        if ($tenDangNhap === '') {
            throw new Exception("Vui lòng nhập tên đăng nhập.");
        }

        if ($matKhau === '') {
            throw new Exception("Vui lòng nhập mật khẩu.");
        }

        // 3. Gọi Repository để tìm tài khoản
        $taiKhoan = $this->repository->findByTenDangNhap($tenDangNhap);
        
        // 4. Kiểm tra kết quả
        if ($taiKhoan === null) {
            throw new Exception("Tên đăng nhập không tồn tại.");
        }

        // Sử dụng password_verify để kiểm tra mật khẩu đã được hash
        if (!password_verify($matKhau, $taiKhoan->getMatKhau())) {
            throw new Exception("Sai mật khẩu.");
        }

        // Kiểm tra trạng thái tài khoản (chỉ cho phép đăng nhập nếu trạng thái là 'Hoạt động')
        $status = $taiKhoan->getTrangThaiTaiKhoan();
        if ($status !== 'Hoạt động' && $status !== 'HoatDong') {
            throw new Exception("Tài khoản đang bị khóa hoặc không hoạt động.");
        }

        // 5. Khởi tạo biến $result và loại bỏ thông tin nhạy cảm
        $result = [
            'MaTK' => $taiKhoan->getMaTK(),
            'TenDangNhap' => $taiKhoan->getTenDangNhap(),
            'Email' => $taiKhoan->getEmail(),
            'VaiTro' => $taiKhoan->getVaiTro(),
        ];

        // Nếu là tài khoản Admin, đính kèm MaAdmin, HoTen, SDT từ bảng Admin
        if (strcasecmp($taiKhoan->getVaiTro(), 'Admin') === 0) {
            $db = Database::getConnection();
            $stmtAD = $db->prepare("SELECT MaAdmin, HoTen, SDT FROM Admin WHERE MaTK = ?");
            $stmtAD->execute([$taiKhoan->getMaTK()]);
            $ad = $stmtAD->fetch(PDO::FETCH_ASSOC);
            if ($ad) {
                $result['MaAdmin'] = $ad['MaAdmin'];
                $result['HoTen'] = $ad['HoTen'];
                $result['SDT'] = $ad['SDT'];
            }
        }

        // Nếu là tài khoản Lễ tân, đính kèm MaLeTan và HoTen từ bảng LeTan
        if (strcasecmp($taiKhoan->getVaiTro(), 'LeTan') === 0) {
            $db = Database::getConnection();
            $stmtLT = $db->prepare("SELECT MaLeTan, HoTen FROM LeTan WHERE MaTK = ?");
            $stmtLT->execute([$taiKhoan->getMaTK()]);
            $lt = $stmtLT->fetch(PDO::FETCH_ASSOC);
            if ($lt) {
                $result['MaLeTan'] = $lt['MaLeTan'];
                $result['HoTen'] = $lt['HoTen'];
            }
        }

        // Nếu là tài khoản Khách hàng, đính kèm MaKH, HoTen, SDT từ bảng KhachHang
        if (strcasecmp($taiKhoan->getVaiTro(), 'KhachHang') === 0) {
            $db = Database::getConnection();
            $stmtKH = $db->prepare("SELECT MaKH, HoTen, SDT FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$taiKhoan->getMaTK()]);
            $kh = $stmtKH->fetch(PDO::FETCH_ASSOC);
            if ($kh) {
                $result['MaKH'] = $kh['MaKH'];
                $result['HoTen'] = $kh['HoTen'];
                $result['SDT'] = $kh['SDT'];
            }
        }

        return $result;
    }

    /**
     * Facade chuyển tiếp sang QuenMatKhauService
     */
    public function quenMatKhau(array $params = []): array
    {
        return $this->quenMatKhauService->quenMatKhau($params);
    }
}
