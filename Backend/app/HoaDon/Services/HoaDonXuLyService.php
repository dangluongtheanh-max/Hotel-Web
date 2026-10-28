<?php

declare(strict_types=1);

namespace App\HoaDon\Services;

use App\HoaDon\Repositories\HoaDonRepository;
use App\HoaDon\Repositories\HoaDonXuLyRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service xử lý toàn bộ logic nghiệp vụ cho module HoaDon.
 * Gồm 2 phần nghiệp vụ: Tra cứu / tính toán chi phí (Query) và Khởi tạo hóa đơn (Command).
 */
class HoaDonXuLyService
{
    private HoaDonXuLyRepository $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof HoaDonXuLyRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof HoaDonRepository) {
            $this->repository = $repository->getXuLyRepository();
        } else {
            $this->repository = new HoaDonXuLyRepository();
        }
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU & TÍNH TOÁN CHI PHÍ HÓA ĐƠN (READ / QUERY)
    // =========================================================================

    /**
     * Xem thông tin chi tiết hóa đơn theo mã hóa đơn hoặc mã phiếu.
     * Tự động tính toán tiền phòng, tiền dịch vụ, giảm giá khuyến mãi và thuế VAT 8%.
     */
    public function xemHoaDon(array $params = []): array
    {
        $maHD = trim($params['MaHD'] ?? ($params['maHD'] ?? ''));
        $maPhieu = trim($params['MaPhieu'] ?? ($params['maPhieu'] ?? ''));

        $db = Database::getConnection();

        $hoaDon = null;
        if (!empty($maHD)) {
            $hoaDon = $this->repository->findHoaDonById($maHD, $db);
        } elseif (!empty($maPhieu)) {
            $hoaDon = $this->repository->findHoaDonByMaPhieu($maPhieu, $db);
        }

        if (!$hoaDon) {
            throw new Exception("Không tìm thấy hóa đơn phù hợp.");
        }

        $maHD = $hoaDon['MaHD'];
        $maPhieu = $hoaDon['MaPhieu'];

        // 1. Lấy danh sách CTHD kèm thông tin loại phòng và dịch vụ
        $chiTiet = $this->repository->getChiTietDayDuHoaDon($maHD, $db);

        // 2. Lấy thông tin phiếu nhận phòng & khách hàng
        $thongTinPhieu = $this->repository->getThongTinPhieuVaKhachHang($maPhieu, $db);

        // 3. Lấy thông tin thanh toán liên quan
        $thanhToanList = $this->repository->getThanhToanByMaHD($maHD, $db);

        // 4. Tính toán tổng hợp chi tiết: Tiền phòng, VAT phòng, Tiền dịch vụ, VAT dịch vụ
        $tongTienPhong = 0;
        $tongTienDichVu = 0;
        foreach ($chiTiet as $item) {
            $tt = (float)($item['ThanhTien'] ?? 0);
            if (!empty($item['MaPhong'])) {
                $tongTienPhong += $tt;
            } elseif (!empty($item['MaCTDV'])) {
                $tongTienDichVu += $tt;
            }
        }

        // 5. Tính giảm giá khuyến mãi nếu có
        $tienGiamKM = 0;
        $maKM = $thongTinPhieu['MaKM'] ?? null;
        if (!empty($maKM)) {
            $kmRow = $this->repository->getKhuyenMaiById($maKM, $db);
            if ($kmRow) {
                if (($kmRow['LoaiKM'] ?? '') === 'PhanTram') {
                    $tienGiamKM = $tongTienPhong * ((float)($kmRow['GiaTriGiam'] ?? 0) / 100);
                } else {
                    $tienGiamKM = min($tongTienPhong, (float)($kmRow['GiaTriGiam'] ?? 0));
                }
            }
        }

        // VAT Phòng: 8% tính trên tiền phòng sau khi trừ khuyến mãi
        $tienPhongSauKM = max(0, $tongTienPhong - $tienGiamKM);
        $vatPhongRate = 0.08;
        $vatPhong = round($tienPhongSauKM * $vatPhongRate, 2);
        $tongTienPhongSauVAT = $tienPhongSauKM + $vatPhong;

        // VAT Dịch Vụ: 8% tính trên tổng giá dịch vụ phát sinh
        $vatDichVuRate = 0.08;
        $vatDichVu = round($tongTienDichVu * $vatDichVuRate, 2);
        $tongTienDichVuSauVAT = $tongTienDichVu + $vatDichVu;

        $tongTienCuoiCung = (float)($hoaDon['TongTienSauVAT'] ?? ($tongTienPhongSauVAT + $tongTienDichVuSauVAT));

        return [
            'success' => true,
            'data' => [
                'hoaDon' => $hoaDon,
                'phieu' => $thongTinPhieu,
                'chiTiet' => $chiTiet,
                'thanhToan' => $thanhToanList,
                'tongHop' => [
                    'tongTienPhong' => $tongTienPhong,
                    'tienGiamKM' => $tienGiamKM,
                    'tienPhongSauKM' => $tienPhongSauKM,
                    'vatPhongRate' => $vatPhongRate,
                    'vatPhong' => $vatPhong,
                    'tongTienPhongSauVAT' => $tongTienPhongSauVAT,
                    'tongTienDichVu' => $tongTienDichVu,
                    'vatDichVuRate' => $vatDichVuRate,
                    'vatDichVu' => $vatDichVu,
                    'tongTienDichVuSauVAT' => $tongTienDichVuSauVAT,
                    'tongTienSauVAT' => $tongTienCuoiCung
                ]
            ]
        ];
    }

    public function xemCTHD(array $params = []): array
    {
        return $this->xemHoaDon($params);
    }

    public function timHoaDon(array $params = []): array
    {
        return $this->xemHoaDon($params);
    }

    // =========================================================================
    // PHẦN 2: KHỞI TẠO & LƯU TRỮ HÓA ĐƠN (WRITE / COMMAND)
    // =========================================================================

    /**
     * Tạo hoặc cập nhật hóa đơn cho phiếu nhận phòng.
     */
    public function taoHoaDon(array $params = []): array
    {
        $maPhieu = trim($params['MaPhieu'] ?? ($params['maPhieu'] ?? ''));
        $tongTien = (float)($params['TongTienSauVAT'] ?? ($params['tongTienSauVAT'] ?? ($params['tongTien'] ?? 0)));
        $chiTiet = $params['chiTiet'] ?? ($params['ChiTiet'] ?? []);

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).");
        }

        $db = Database::getConnection();

        // Kiểm tra xem phiếu này đã có hóa đơn chưa
        $existing = $this->repository->findHoaDonByMaPhieu($maPhieu, $db);
        if ($existing) {
            // Cập nhật tổng tiền nếu có thay đổi
            if ($tongTien > 0 && $tongTien != (float)$existing['TongTienSauVAT']) {
                $this->repository->luuHoaDon($existing['MaHD'], $maPhieu, $tongTien, $db);
            }
            return [
                'success' => true,
                'message' => 'Hóa đơn đã tồn tại cho phiếu này.',
                'data' => [
                    'maHD' => $existing['MaHD'],
                    'maPhieu' => $maPhieu,
                    'tongTienSauVAT' => $tongTien ?: (float)$existing['TongTienSauVAT']
                ]
            ];
        }

        $maHD = $this->repository->generateMaHoaDon($db);

        // Lưu Hóa đơn tổng
        $this->repository->luuHoaDon($maHD, $maPhieu, $tongTien, $db);

        // Lưu các dòng CTHD nếu có
        if (!empty($chiTiet) && is_array($chiTiet)) {
            foreach ($chiTiet as $item) {
                $maPhong = $item['maPhong'] ?? ($item['MaPhong'] ?? null);
                $maCTDV = $item['maCTDV'] ?? ($item['MaCTDV'] ?? null);
                $soDem = (int)($item['soDem'] ?? ($item['SoDem'] ?? 0));
                $soLuong = (int)($item['soLuong'] ?? ($item['SoLuong'] ?? 1));
                $donGia = (float)($item['donGia'] ?? ($item['DonGia'] ?? 0));

                if (!empty($maPhong) || !empty($maCTDV)) {
                    $this->repository->luuCTHD(
                        $maHD,
                        $maPhieu,
                        $maPhong,
                        $maCTDV,
                        $soDem,
                        $soLuong,
                        $donGia,
                        $db
                    );
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Tạo hóa đơn thành công.',
            'data' => [
                'maHD' => $maHD,
                'maPhieu' => $maPhieu,
                'tongTienSauVAT' => $tongTien
            ]
        ];
    }
}
