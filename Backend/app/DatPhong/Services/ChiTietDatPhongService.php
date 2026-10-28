<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;
use Exception;

/**
 * Service chuyên trách tra cứu chi tiết phiếu đặt phòng và tích hợp thông tin đánh giá.
 */
class ChiTietDatPhongService
{
    private DatPhongRepository $repository;
    private KhachHangRepository $khachHangRepo;
    private ?DanhGiaService $danhGiaService;

    public function __construct(
        DatPhongRepository $repository,
        ?KhachHangRepository $khachHangRepo = null,
        ?DanhGiaService $danhGiaService = null
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
        $this->danhGiaService = $danhGiaService ?? new DanhGiaService(new DanhGiaRepository());
    }

    /**
     * Lấy chi tiết phiếu đặt theo mã phiếu (có kiểm tra quyền sở hữu của khách hàng)
     */
    public function xemChiTietPhieu(string $maPhieu, ?string $maTK = null): ?array
    {
        $maKH = null;
        if (!empty($maTK)) {
            $db = Database::getConnection();
            $stmtTK = $db->prepare("SELECT MaTK, VaiTro FROM TaiKhoan WHERE MaTK = ? LIMIT 1");
            $stmtTK->execute([$maTK]);
            $tk = $stmtTK->fetch(\PDO::FETCH_ASSOC);

            if (!$tk) {
                throw new Exception("Tài khoản không hợp lệ hoặc chưa đăng nhập.", 403);
            }

            // Nếu là Lễ tân hoặc Admin: có toàn quyền tra cứu bất kỳ phiếu nhận phòng / hóa đơn nào trong khách sạn
            if (in_array($tk['VaiTro'], ['Admin', 'LeTan'], true)) {
                $maKH = null;
            } else {
                // Nếu là Khách hàng: chỉ được xem phiếu của chính mình
                $khachHang = $this->khachHangRepo->findByMaTK($maTK);
                if (!$khachHang) {
                    throw new Exception("Tài khoản không hợp lệ hoặc chưa đăng nhập.", 403);
                }
                $maKH = $khachHang['MaKH'];
            }
        }

        $result = $this->repository->getChiTietPhieu($maPhieu, $maKH);
        if ($result === false) {
            throw new Exception("Bạn không có quyền truy cập thông tin phiếu nhận phòng này.", 403);
        }

        // Đính kèm thông tin đánh giá cho từng phòng thuộc phiếu
        if ($result && !empty($result['DanhSachPhong']) && is_array($result['DanhSachPhong'])) {
            $danhGiaRes = $this->danhGiaService ? $this->danhGiaService->xemDanhGiaTheoPhieu(['MaPhieu' => $maPhieu]) : null;
            $danhGiaMap = [];
            if ($danhGiaRes && !empty($danhGiaRes['data']) && is_array($danhGiaRes['data'])) {
                foreach ($danhGiaRes['data'] as $dg) {
                    $danhGiaMap[$dg['MaPhong']] = $dg;
                }
            }

            foreach ($result['DanhSachPhong'] as &$room) {
                $maPhong = $room['MaPhong'] ?? '';
                if (isset($danhGiaMap[$maPhong])) {
                    $room['DaDanhGia'] = true;
                    $room['DanhGia'] = $danhGiaMap[$maPhong];
                } else {
                    $room['DaDanhGia'] = false;
                    $room['DanhGia'] = null;
                }
            }
            unset($room);
        }

        return $result;
    }
}
