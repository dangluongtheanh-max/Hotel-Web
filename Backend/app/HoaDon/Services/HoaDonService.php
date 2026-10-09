<?php

namespace App\HoaDon\Services;

use App\HoaDon\Repositories\HoaDonRepository;
use App\KhuyenMai\Services\KhuyenMaiService;

/**
 * Service xử lý toàn bộ logic nghiệp vụ tính toán và lập hóa đơn thanh toán khách sạn.
 */
class HoaDonService
{
    private HoaDonRepository $repo;
    private KhuyenMaiService $kmService;

    public function __construct(?HoaDonRepository $repo = null, ?KhuyenMaiService $kmService = null)
    {
        $this->repo = $repo ?? new HoaDonRepository();
        $this->kmService = $kmService ?? new KhuyenMaiService();
    }

    /**
     * Tính toán bảng kê chi phí chi tiết của một phiếu trước khi xuất hóa đơn (Preview Folio).
     */
    public function tinhToanChiPhi(string $maPhieu): array
    {
        $phieu = $this->repo->getPhieuNhanPhongFull($maPhieu);
        if (!$phieu) {
            return [
                'success' => false,
                'error' => "Không tìm thấy phiếu nhận phòng có mã '{$maPhieu}'."
            ];
        }

        $itemsCTHD = [];
        $tongTienPhong = 0.0;
        $tongSoDem = 0;
        $tongSoNguoi = 0;
        $ngayTraPhongCuoi = date('Y-m-d');

        // 1. Tính tiền phòng
        foreach ($phieu['DanhSachPhong'] as $phong) {
            $checkIn = strtotime($phong['ThoiGianNhanPhong']);
            $checkOut = strtotime($phong['ThoiGianTraPhong']);
            $soDem = max(1, (int)round(($checkOut - $checkIn) / 86400));
            $donGia = (float)$phong['DonGiaPhong'];
            $thanhTien = $soDem * $donGia;

            $tongSoDem += $soDem;
            $tongSoNguoi += (int)$phong['SoNguoi'];
            $tongTienPhong += $thanhTien;
            $ngayTraPhongCuoi = date('Y-m-d', $checkOut);

            $itemsCTHD[] = [
                'LoaiKhoan' => 'Phong',
                'MaPhong' => $phong['MaPhong'],
                'TenKhoan' => "Phòng {$phong['MaPhong']} ({$phong['TenLoaiPhong']})",
                'MaCTDV' => null,
                'SoDem' => $soDem,
                'SoLuong' => 1,
                'DonGia' => $donGia,
                'ThanhTien' => $thanhTien,
            ];
        }

        // 2. Tính tiền dịch vụ đã dùng
        $tongTienDichVu = 0.0;
        foreach ($phieu['DanhSachDichVu'] as $dv) {
            $soLuong = (int)$dv['SoLuong'];
            $donGia = (float)$dv['DonGia'];
            $thanhTien = $soLuong * $donGia;
            $tongTienDichVu += $thanhTien;

            $itemsCTHD[] = [
                'LoaiKhoan' => 'DichVu',
                'MaPhong' => null,
                'TenKhoan' => $dv['TenCTDV'] . ($dv['MoTa'] ? " ({$dv['MoTa']})" : ""),
                'MaCTDV' => $dv['MaCTDV'],
                'SoDem' => 0,
                'SoLuong' => $soLuong,
                'DonGia' => $donGia,
                'ThanhTien' => $thanhTien,
            ];
        }

        // 3. Khấu trừ khuyến mãi (nếu có mã KM)
        $tienGiamKM = 0.0;
        $thongTinKM = null;

        if (!empty($phieu['MaKM'])) {
            $kmResult = $this->kmService->apDungKhuyenMai([
                'MaKM' => $phieu['MaKM'],
                'SoDem' => $tongSoDem,
                'SoNguoi' => $tongSoNguoi,
                'TienPhong' => $tongTienPhong,
                'NgayApDung' => $ngayTraPhongCuoi,
            ]);

            if ($kmResult['success'] ?? false) {
                $tienGiamKM = (float)$kmResult['data']['TienGiam'];
                $thongTinKM = $kmResult['data'];
            }
        }

        // 4. Tính thuế VAT 10%
        $tienPhongSauGiam = max(0.0, $tongTienPhong - $tienGiamKM);
        $tongTienTruocVAT = $tienPhongSauGiam + $tongTienDichVu;
        $thueVAT = round($tongTienTruocVAT * 0.10);
        $tongTienSauVAT = $tongTienTruocVAT + $thueVAT;

        return [
            'success' => true,
            'data' => [
                'MaPhieu' => $phieu['MaPhieu'],
                'NgayLap' => $phieu['NgayLap'],
                'TrangThaiPhieu' => $phieu['TrangThai'],
                'KhachHang' => [
                    'MaKH' => $phieu['MaKH'],
                    'TenKhachHang' => $phieu['TenKhachHang'],
                    'SDT' => $phieu['SDT'],
                    'Email' => $phieu['Email'],
                ],
                'BangKeChiTiet' => $itemsCTHD,
                'TongTienPhong' => $tongTienPhong,
                'TongTienDichVu' => $tongTienDichVu,
                'KhuyenMai' => $thongTinKM,
                'TienGiamKM' => $tienGiamKM,
                'TienPhongSauGiam' => $tienPhongSauGiam,
                'TongTienTruocVAT' => $tongTienTruocVAT,
                'ThueVAT' => $thueVAT,
                'TongTienSauVAT' => $tongTienSauVAT,
            ]
        ];
    }

    /**
     * Lập hóa đơn chính thức cho phiếu đặt phòng (Tạo bản ghi trong HoaDon & CTHD).
     */
    public function taoHoaDon(array $params): array
    {
        $maPhieu = trim($params['MaPhieu'] ?? '');
        if (empty($maPhieu)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).'
            ];
        }

        // Kiểm tra xem phiếu này đã từng xuất hóa đơn chưa
        $existing = $this->repo->findHoaDonByMaPhieu($maPhieu);
        if ($existing) {
            return [
                'success' => true,
                'message' => "Phiếu '{$maPhieu}' đã có hóa đơn '{$existing['MaHD']}' trong hệ thống.",
                'is_existing' => true,
                'data' => $this->xemChiTietHoaDon($existing['MaHD'])['data'] ?? $existing
            ];
        }

        // Tính toán các khoản chi phí
        $calc = $this->tinhToanChiPhi($maPhieu);
        if (!$calc['success']) {
            return $calc;
        }

        $info = $calc['data'];
        $maHD = $this->repo->generateNextMaHD();

        try {
            $this->repo->saveHoaDonWithDetails(
                [
                    'MaHD' => $maHD,
                    'MaPhieu' => $maPhieu,
                    'TongTienSauVAT' => $info['TongTienSauVAT'],
                ],
                $info['BangKeChiTiet'],
                $maPhieu
            );

            return [
                'success' => true,
                'message' => "Lập hóa đơn '{$maHD}' thành công cho phiếu '{$maPhieu}'!",
                'is_existing' => false,
                'data' => $this->xemChiTietHoaDon($maHD)['data'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Lỗi khi lưu hóa đơn vào cơ sở dữ liệu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Xem thông tin chi tiết đầy đủ của một hóa đơn.
     */
    public function xemChiTietHoaDon(string $maHD): array
    {
        $hd = $this->repo->findHoaDonById($maHD);
        if (!$hd) {
            return [
                'success' => false,
                'error' => "Không tìm thấy hóa đơn '{$maHD}'."
            ];
        }

        $details = $this->repo->getChiTietHoaDon($maHD);

        $tongPhong = 0.0;
        $tongDV = 0.0;
        foreach ($details as $d) {
            if ($d['MaPhong'] !== null) {
                $tongPhong += (float)$d['ThanhTien'];
            } else {
                $tongDV += (float)$d['ThanhTien'];
            }
        }

        return [
            'success' => true,
            'data' => [
                'MaHD' => $hd['MaHD'],
                'MaPhieu' => $hd['MaPhieu'],
                'TongTienSauVAT' => (float)$hd['TongTienSauVAT'],
                'NgayLapPhieu' => $hd['NgayLap'],
                'KenhDat' => $hd['KenhDat'],
                'TrangThaiPhieu' => $hd['TrangThaiPhieu'],
                'KhachHang' => [
                    'MaKH' => $hd['MaKH'],
                    'TenKhachHang' => $hd['TenKhachHang'],
                    'SDT' => $hd['SDT'],
                    'Email' => $hd['Email'],
                ],
                'KhuyenMai' => $hd['MaKM'] ? [
                    'MaKM' => $hd['MaKM'],
                    'TenKM' => $hd['TenKM'],
                    'LoaiKM' => $hd['LoaiKM'],
                    'GiaTriGiam' => (float)$hd['GiaTriGiam'],
                ] : null,
                'TongTienPhong' => $tongPhong,
                'TongTienDichVu' => $tongDV,
                'ChiTietCacKhoan' => $details
            ]
        ];
    }

    /**
     * Lấy danh sách toàn bộ hóa đơn.
     */
    public function layDanhSachHoaDon(array $filters = []): array
    {
        $list = $this->repo->getAllHoaDon($filters);
        return [
            'success' => true,
            'total' => count($list),
            'data' => $list
        ];
    }
}
