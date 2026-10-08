<?php

namespace App\HoaDon\Services;

use App\HoaDon\Models\HoaDon;
use App\HoaDon\Models\CTHD;
use App\HoaDon\Repositories\HoaDonRepository;
use Shared\Helper;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service (business logic) cho module HoaDon.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class HoaDonService
{
    private HoaDonRepository $repository;

    public function __construct(HoaDonRepository $repository)
    {
        $this->repository = $repository;
    }

    public function taoHoaDon(array $params = []): array
    {
        if (empty($params['MaPhieu'])) {
            throw new InvalidArgumentException('Mã phiếu đặt phòng (MaPhieu) là bắt buộc.');
        }

        $maPhieu = $params['MaPhieu'];

        // Kiểm tra phiếu nhận phòng có tồn tại không
        $thongTinPhieu = $this->repository->kiemTraPhieuNhanPhong($maPhieu);
        if (!$thongTinPhieu) {
            throw new InvalidArgumentException("Phiếu đặt phòng '{$maPhieu}' không tồn tại trong hệ thống.");
        }

        if (in_array($thongTinPhieu['TrangThai'], ['DaHuy', 'TuChoi'], true)) {
            throw new InvalidArgumentException("Không thể tạo hóa đơn cho phiếu có trạng thái '{$thongTinPhieu['TrangThai']}'.");
        }

        // Kiểm tra xem phiếu này đã tạo hóa đơn trước đó chưa
        $daCoHoaDon = $this->repository->findByMaPhieu($maPhieu);
        if ($daCoHoaDon) {
            $dsCTHD = $this->repository->getCTHDByMaHD($daCoHoaDon->getMaHD());
            return [
                'hoaDon' => $daCoHoaDon->toArray(),
                'chiTiet' => array_map(fn(CTHD $c) => $c->toArray(), $dsCTHD),
                'thongBao' => 'Phiếu này đã có hóa đơn từ trước.'
            ];
        }

        $maHD = !empty($params['MaHD']) ? $params['MaHD'] : Helper::generateId('HD');
        $danhSachCTHD = [];
        $tongTienTruocThue = 0.0;

        // Trường hợp 1: Client truyền trực tiếp danh sách chi tiết
        if (!empty($params['chiTiet']) && is_array($params['chiTiet'])) {
            foreach ($params['chiTiet'] as $item) {
                $maPhong = !empty($item['MaPhong']) ? $item['MaPhong'] : null;
                $maCTDV = !empty($item['MaCTDV']) ? $item['MaCTDV'] : (!empty($item['MaDV']) ? $item['MaDV'] : null);
                $soDem = (int) ($item['SoDem'] ?? 0);
                $soLuong = (int) ($item['SoLuong'] ?? 1);
                $donGia = (float) ($item['DonGia'] ?? 0);

                if ($maPhong !== null) {
                    $soLuong = 0;
                    if ($soDem <= 0) $soDem = 1;
                    $tongTienTruocThue += $soDem * $donGia;
                } else if ($maCTDV !== null) {
                    $soDem = 0;
                    if ($soLuong <= 0) $soLuong = 1;
                    $tongTienTruocThue += $soLuong * $donGia;
                }

                $danhSachCTHD[] = new CTHD(
                    null,
                    $maHD,
                    $maPhieu,
                    $maPhong,
                    $maCTDV,
                    $soDem,
                    $soLuong,
                    $donGia
                );
            }
        } else {
            // Trường hợp 2: Tự động trích xuất chi phí từ phiếu đặt phòng & dịch vụ đã dùng
            $duLieuPhieu = $this->repository->layChiPhiTuPhieu($maPhieu);

            // Xử lý tiền phòng — tính số đêm bằng DateTime PHP chính xác
            foreach ($duLieuPhieu['phong'] as $p) {
                $soDem = (int) ($p['SoDem'] ?? 0);
                if ($soDem <= 0) {
                    if (!empty($p['ThoiGianNhanPhong']) && !empty($p['ThoiGianTraPhong'])) {
                        try {
                            $nhan = new \DateTime($p['ThoiGianNhanPhong']);
                            $tra = new \DateTime($p['ThoiGianTraPhong']);
                            $diff = $nhan->diff($tra);
                            $soDem = max(1, (int) $diff->days);
                        } catch (\Throwable $e) {
                            $soDem = 1;
                        }
                    } else {
                        $soDem = 1;
                    }
                }
                $donGia = (float) ($p['DonGia'] ?? 0);
                $tongTienTruocThue += $soDem * $donGia;

                $danhSachCTHD[] = new CTHD(
                    null,
                    $maHD,
                    $maPhieu,
                    $p['MaPhong'],
                    null,
                    $soDem,
                    0,
                    $donGia
                );
            }

            // Xử lý tiền dịch vụ
            foreach ($duLieuPhieu['dichVu'] as $dv) {
                $soLuong = (int) ($dv['SoLuong'] ?? 1);
                $donGia = (float) ($dv['DonGia'] ?? 0);
                $tongTienTruocThue += $soLuong * $donGia;

                $danhSachCTHD[] = new CTHD(
                    null,
                    $maHD,
                    $maPhieu,
                    null,
                    $dv['MaCTDV'],
                    0,
                    $soLuong,
                    $donGia
                );
            }

            // Áp dụng khuyến mãi nếu có
            if (!empty($duLieuPhieu['khuyenMai'])) {
                $km = $duLieuPhieu['khuyenMai'];
                $giam = (float) ($km['GiaTriGiam'] ?? 0);
                if (($km['LoaiKM'] ?? '') === 'PhanTram') {
                    $tongTienTruocThue -= ($tongTienTruocThue * ($giam / 100));
                } else {
                    $tongTienTruocThue -= $giam;
                }
            }
        }

        if ($tongTienTruocThue < 0) {
            $tongTienTruocThue = 0;
        }

        // Tính VAT 10%
        $tongTienSauVAT = isset($params['TongTienSauVAT'])
            ? (float) $params['TongTienSauVAT']
            : round($tongTienTruocThue * 1.1, 2);

        $hoaDon = new HoaDon($maHD, $maPhieu, $tongTienSauVAT);
        $okHD = $this->repository->saveHoaDon($hoaDon);

        if (!$okHD) {
            throw new RuntimeException('Không thể lưu hóa đơn vào cơ sở dữ liệu.');
        }

        // Lưu từng dòng chi tiết hóa đơn
        $savedCTHD = [];
        foreach ($danhSachCTHD as $cthd) {
            $this->repository->saveCTHD($cthd);
            $savedCTHD[] = $cthd->toArray();
        }

        return [
            'hoaDon' => $hoaDon->toArray(),
            'chiTiet' => $savedCTHD,
            'tongTienTruocThue' => $tongTienTruocThue,
        ];
    }

    public function xemHoaDon(array $params = []): ?array
    {
        $hoaDon = null;

        if (!empty($params['MaHD'])) {
            $hoaDon = $this->repository->findHoaDonById($params['MaHD']);
        } elseif (!empty($params['MaPhieu'])) {
            $hoaDon = $this->repository->findByMaPhieu($params['MaPhieu']);
        }

        if (!$hoaDon) {
            return null;
        }

        $dsCTHD = $this->repository->getCTHDByMaHD($hoaDon->getMaHD());

        return [
            'hoaDon' => $hoaDon->toArray(),
            'chiTiet' => array_map(fn(CTHD $c) => $c->toArray(), $dsCTHD),
        ];
    }

    public function xemCTHD(array $params = []): array
    {
        if (!empty($params['MaHD'])) {
            $list = $this->repository->getCTHDByMaHD($params['MaHD']);
            return array_map(fn(CTHD $c) => $c->toArray(), $list);
        }

        if (!empty($params['MaCTHD'])) {
            $item = $this->repository->findCTHDById($params['MaCTHD']);
            return $item ? [$item->toArray()] : [];
        }

        $list = $this->repository->getAllCTHD($params);
        return array_map(fn(CTHD $c) => $c->toArray(), $list);
    }

    public function timHoaDon(array $params = []): array
    {
        $list = $this->repository->getAllHoaDon($params);
        return array_map(fn(HoaDon $hd) => $hd->toArray(), $list);
    }
}
