<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DatPhong\Repositories\DatPhongRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Shared\Response;
use Exception;

/**
 * Service chuyên trách việc lấy lịch sử phiếu đặt phòng của khách hàng.
 * Tuân thủ SRP: chỉ thực hiện việc truy vấn, không xử lý nghiệp vụ đặt‑phòng
 * hay tính phí.
 */
class LichSuDatPhongService
{
    private DatPhongRepository $repository;
    private KhachHangRepository $khachHangRepo;

    public function __construct(
        DatPhongRepository $repository,
        ?KhachHangRepository $khachHangRepo = null
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new \Database\Database());
    }

    /**
     * Lấy danh sách phiếu đặt phòng của khách đang đăng nhập.
     *
     * @param string $maTK  Mã tài khoản (được truyền từ client, đã xác thực ở lớp middleware).
     * @param int    $limit Số bản ghi tối đa trả về (mặc định 20).
     * @param int    $offset Vị trí bắt đầu (mặc định 0).
     * @return array        Mảng các phiếu, mỗi phần tử chứa thông tin tóm tắt.
     */
    public function layLichSuDatPhong(string $maTK, int $limit = 20, int $offset = 0): array
    {
        try {
            (new \App\LeTan\Repositories\LeTanPhieuRepository())->tuDongHuyPhieuQuaHanTraPhong();
        } catch (\Throwable $e) {
            // Không chặn việc xem lịch sử nếu tác vụ dọn dẹp nền gặp lỗi
        }

        $dbConn = \Database\Database::getConnection();
        $stmtRole = $dbConn->prepare("SELECT VaiTro FROM TaiKhoan WHERE MaTK = ? LIMIT 1");
        $stmtRole->execute([$maTK]);
        if ($stmtRole->fetchColumn() === 'Admin') {
            throw new Exception('Tài khoản Quản trị viên không sử dụng chức năng xem lịch sử đặt phòng cá nhân.');
        }

        $khachHang = $this->khachHangRepo->findByMaTK($maTK);
        if (!$khachHang) {
            throw new Exception('Tài khoản không hợp lệ hoặc chưa đăng nhập.');
        }
        $maKH = $khachHang['MaKH'];
        return $this->repository->getDanhSachPhieuCuaKhach($maKH, $limit, $offset);
    }
}
