<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository phụ trách toàn bộ truy vấn Báo Cáo & Thống Kê (BE4).
 */
class AdminThongKeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /* =========================================================================
     * THỐNG KÊ DOANH THU (BE4)
     * ========================================================================= */

    /** Trạng thái giao dịch đã phát sinh dòng tiền thật trên bảng ThanhToan. */
    private const PAID_STATUSES = ['HoanTat', 'HoanTienMotPhan', 'DaHoanTien'];

    /**
     * Chuẩn hóa khoảng ngày lọc thống kê về định dạng DATETIME [Y-m-d 00:00:00, Y-m-d 23:59:59].
     */
    private function normalizeDateRange(array $filters, string $defaultFrom, string $defaultTo): array
    {
        $fromDate = !empty($filters['from_date'])
            ? trim((string)$filters['from_date'])
            : (!empty($filters['tu_ngay']) ? trim((string)$filters['tu_ngay']) : $defaultFrom);
        $toDate = !empty($filters['to_date'])
            ? trim((string)$filters['to_date'])
            : (!empty($filters['den_ngay']) ? trim((string)$filters['den_ngay']) : $defaultTo);

        if (strlen($fromDate) === 10) {
            $fromDate .= ' 00:00:00';
        }
        if (strlen($toDate) === 10) {
            $toDate .= ' 23:59:59';
        }

        return [$fromDate, $toDate];
    }

    /**
     * Phân bổ một khoản tiền theo tỷ trọng có bù làm tròn (Largest Remainder Method)
     * để tổng các phần tử luôn khớp tuyệt đối với $totalTarget đến từng đồng.
     */
    private function allocateAmountsProportionally(float $totalTarget, array $weights): array
    {
        $result = [];
        $keys = array_keys($weights);
        if (empty($keys)) {
            return $result;
        }

        $targetInt = (int)round($totalTarget);
        if ($targetInt === 0) {
            foreach ($keys as $k) {
                $result[$k] = 0.0;
            }
            return $result;
        }

        $sumWeight = 0.0;
        foreach ($weights as $w) {
            $sumWeight += max(0.0, (float)$w);
        }

        if ($sumWeight <= 0.0) {
            $equalWeights = [];
            foreach ($keys as $k) {
                $equalWeights[$k] = 1.0;
            }
            return $this->allocateAmountsProportionally((float)$targetInt, $equalWeights);
        }

        $sign = $targetInt < 0 ? -1 : 1;
        $absTarget = abs($targetInt);
        $floors = [];
        $remainders = [];
        $assigned = 0;

        foreach ($keys as $k) {
            $exact = ($absTarget * max(0.0, (float)$weights[$k])) / $sumWeight;
            $fl = (int)floor($exact);
            $floors[$k] = $fl;
            $remainders[$k] = $exact - $fl;
            $assigned += $fl;
        }

        $diff = $absTarget - $assigned;
        if ($diff > 0) {
            arsort($remainders, SORT_NUMERIC);
            foreach (array_keys($remainders) as $k) {
                if ($diff <= 0) {
                    break;
                }
                $floors[$k]++;
                $diff--;
            }
        }

        foreach ($keys as $k) {
            $result[$k] = (float)($sign * $floors[$k]);
        }

        return $result;
    }

    /**
     * Nguồn chuẩn duy nhất cho Doanh thu thực thu ròng trong kỳ:
     * - Chỉ tính giao dịch có dòng tiền thật (HoanTat, HoanTienMotPhan, DaHoanTien).
     * - Doanh thu ròng = Tiền đã thu trong kỳ - Tiền đã hoàn trong kỳ (giữ lại đúng phần phí hủy).
     * - Phân bổ phần thực thu ròng của từng hóa đơn (sau KM, VAT và hoàn hủy) xuống Loại phòng & Dịch vụ
     *   để TongDoanhThu === DoanhThuPhong + DoanhThuDichVu.
     */
    private function summarizeNetRevenue(string $fromDate, string $toDate): array
    {
        $statusPlaceholders = implode(',', array_fill(0, count(self::PAID_STATUSES), '?'));
        $sqlTT = "SELECT MaThanhToan, MaHD, SoTien, COALESCE(SoTienHoan, 0) AS SoTienHoan,
                         HinhThuc, ThoiGianThanhToan,
                         COALESCE(ThoiGianHoan, ThoiGianThanhToan) AS NgayHoan
                  FROM ThanhToan
                  WHERE TrangThaiThanhToan IN ($statusPlaceholders)
                    AND (
                        (ThoiGianThanhToan >= ? AND ThoiGianThanhToan <= ?)
                        OR (COALESCE(SoTienHoan, 0) > 0 AND COALESCE(ThoiGianHoan, ThoiGianThanhToan) >= ? AND COALESCE(ThoiGianHoan, ThoiGianThanhToan) <= ?)
                    )";
        $paramsTT = array_merge(self::PAID_STATUSES, [$fromDate, $toDate, $fromDate, $toDate]);
        $stmtTT = $this->db->prepare($sqlTT);
        $stmtTT->execute($paramsTT);
        $txRows = $stmtTT->fetchAll(PDO::FETCH_ASSOC);

        $tongTienThu = 0.0;
        $tongTienHoan = 0.0;
        $tongGiaoDich = 0;
        $chuyenKhoan = 0.0;
        $tienMat = 0.0;
        $dailyMap = [];
        $invoiceNetInPeriod = [];

        foreach ($txRows as $tx) {
            $maHD = (string)$tx['MaHD'];
            $soTien = (float)($tx['SoTien'] ?? 0);
            $soTienHoan = (float)($tx['SoTienHoan'] ?? 0);
            $tgThu = (string)($tx['ThoiGianThanhToan'] ?? '');
            $tgHoan = (string)($tx['NgayHoan'] ?? $tgThu);
            $hinhThuc = (string)($tx['HinhThuc'] ?? '');

            $thuInPeriod = ($tgThu >= $fromDate && $tgThu <= $toDate) ? $soTien : 0.0;
            $hoanInPeriod = ($soTienHoan > 0 && $tgHoan >= $fromDate && $tgHoan <= $toDate) ? $soTienHoan : 0.0;

            if ($thuInPeriod > 0) {
                $tongTienThu += $thuInPeriod;
                $tongGiaoDich++;
                $dayThu = substr($tgThu, 0, 10);
                if (!isset($dailyMap[$dayThu])) {
                    $dailyMap[$dayThu] = ['Ngay' => $dayThu, 'SoGiaoDich' => 0, 'DoanhThu' => 0.0];
                }
                $dailyMap[$dayThu]['SoGiaoDich']++;
                $dailyMap[$dayThu]['DoanhThu'] += $thuInPeriod;
            }

            if ($hoanInPeriod > 0) {
                $tongTienHoan += $hoanInPeriod;
                $dayHoan = substr($tgHoan, 0, 10);
                if (!isset($dailyMap[$dayHoan])) {
                    $dailyMap[$dayHoan] = ['Ngay' => $dayHoan, 'SoGiaoDich' => 0, 'DoanhThu' => 0.0];
                }
                $dailyMap[$dayHoan]['DoanhThu'] -= $hoanInPeriod;
            }

            $netTx = $thuInPeriod - $hoanInPeriod;
            if ($hinhThuc === 'ChuyenKhoan') {
                $chuyenKhoan += $netTx;
            } elseif ($hinhThuc === 'TienMat') {
                $tienMat += $netTx;
            }

            if ($thuInPeriod != 0.0 || $hoanInPeriod != 0.0) {
                $invoiceNetInPeriod[$maHD] = ($invoiceNetInPeriod[$maHD] ?? 0.0) + $netTx;
            }
        }

        ksort($dailyMap);
        $timeline = [];
        foreach ($dailyMap as $dayRow) {
            $timeline[] = [
                'Ngay' => $dayRow['Ngay'],
                'SoGiaoDich' => $dayRow['SoGiaoDich'],
                'DoanhThu' => number_format(round($dayRow['DoanhThu']), 2, '.', ''),
            ];
        }

        $tongDoanhThu = round($tongTienThu - $tongTienHoan);
        $doanhThuPhong = 0.0;
        $doanhThuDichVu = 0.0;
        $byRoomType = [];
        $byServiceGroup = [];
        $byServiceItem = [];

        if (!empty($invoiceNetInPeriod)) {
            $hdIds = array_keys($invoiceNetInPeriod);
            $hdPh = implode(',', array_fill(0, count($hdIds), '?'));

            // Tổng đã thu mọi thời điểm trên từng hóa đơn để tách chính xác tỷ trọng Phòng / Dịch vụ
            $stmtAllPaid = $this->db->prepare("
                SELECT MaHD, COALESCE(SUM(SoTien), 0) AS TongThuHoaDon
                FROM ThanhToan
                WHERE MaHD IN ($hdPh)
                  AND TrangThaiThanhToan IN ($statusPlaceholders)
                GROUP BY MaHD
            ");
            $stmtAllPaid->execute(array_merge($hdIds, self::PAID_STATUSES));
            $allPaidMap = $stmtAllPaid->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $stmtItems = $this->db->prepare("
                SELECT cthd.MaHD, cthd.MaPhong, cthd.MaCTDV, cthd.SoDem, cthd.SoLuong, cthd.DonGia,
                       p.MaLoaiPhong, ctp.TrangThai AS TrangThaiPhongPhieu, COALESCE(ctp.SoTienHoan, 0) AS PhongSoTienHoan,
                       ctdv.TenCTDV, dv.MaDV, dv.TenDV,
                       km.LoaiKM, km.GiaTriGiam
                FROM CTHD cthd
                JOIN HoaDon hd ON cthd.MaHD = hd.MaHD
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                LEFT JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
                LEFT JOIN Phong p ON cthd.MaPhong = p.MaPhong
                LEFT JOIN ChiTietPhieuNhanPhong ctp ON hd.MaPhieu = ctp.MaPhieu AND cthd.MaPhong = ctp.MaPhong
                LEFT JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
                LEFT JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                WHERE cthd.MaHD IN ($hdPh)
                ORDER BY cthd.MaCTHD ASC
            ");
            $stmtItems->execute($hdIds);
            $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $itemsByHd = [];
            foreach ($allItems as $it) {
                $itemsByHd[$it['MaHD']][] = $it;
            }

            foreach ($invoiceNetInPeriod as $maHD => $netHD) {
                $lines = $itemsByHd[$maHD] ?? [];
                if (empty($lines) || round($netHD) == 0.0) {
                    continue;
                }

                $roomLines = [];
                $svcLines = [];
                $roomGross = 0.0;
                $svcGross = 0.0;
                $loaiKM = $lines[0]['LoaiKM'] ?? '';
                $giaTriGiam = (float)($lines[0]['GiaTriGiam'] ?? 0);

                foreach ($lines as $idx => $ln) {
                    if (!empty($ln['MaPhong']) && empty($ln['MaCTDV'])) {
                        $gross = (float)$ln['SoDem'] * (float)$ln['DonGia'];
                        $roomGross += $gross;
                        $roomLines[$idx] = $ln + ['gross' => $gross];
                    } elseif (!empty($ln['MaCTDV'])) {
                        $gross = (float)$ln['SoLuong'] * (float)$ln['DonGia'];
                        $svcGross += $gross;
                        $svcLines[$idx] = $ln + ['gross' => $gross];
                    }
                }

                $tienGiamKM = 0.0;
                if ($roomGross > 0 && $giaTriGiam > 0) {
                    $tienGiamKM = ($loaiKM === 'PhanTram')
                        ? ($roomGross * ($giaTriGiam / 100.0))
                        : min($roomGross, $giaTriGiam);
                }
                $roomAfterVat = round(max(0.0, $roomGross - $tienGiamKM) * 1.08);
                $totalPaidAllTime = (float)($allPaidMap[$maHD] ?? $netHD);

                if (empty($svcLines)) {
                    $roomNet = (float)round($netHD);
                    $svcNet = 0.0;
                } elseif (empty($roomLines)) {
                    $roomNet = 0.0;
                    $svcNet = (float)round($netHD);
                } else {
                    $svcTarget = ($totalPaidAllTime > $roomAfterVat)
                        ? ($totalPaidAllTime - $roomAfterVat)
                        : round($svcGross * 1.08);
                    $split = $this->allocateAmountsProportionally($netHD, [
                        'room' => $roomAfterVat,
                        'service' => max($svcGross, $svcTarget),
                    ]);
                    $roomNet = $split['room'];
                    $svcNet = $split['service'];
                }

                $doanhThuPhong += $roomNet;
                $doanhThuDichVu += $svcNet;

                if (!empty($roomLines)) {
                    $roomWeights = [];
                    foreach ($roomLines as $idx => $rl) {
                        $nominalShare = ($roomGross > 0) ? (($rl['gross'] / $roomGross) * $roomAfterVat) : 0.0;
                        $cancelledDeduct = (($rl['TrangThaiPhongPhieu'] ?? '') === 'DaHuy')
                            ? (float)$rl['PhongSoTienHoan']
                            : 0.0;
                        $roomWeights[$idx] = max(0.0, $nominalShare - $cancelledDeduct);
                    }
                    if (array_sum($roomWeights) <= 0.0) {
                        foreach ($roomLines as $idx => $rl) {
                            $roomWeights[$idx] = $rl['gross'];
                        }
                    }

                    $allocatedRooms = $this->allocateAmountsProportionally($roomNet, $roomWeights);
                    foreach ($roomLines as $idx => $rl) {
                        $maLP = (string)$rl['MaLoaiPhong'];
                        if (!isset($byRoomType[$maLP])) {
                            $byRoomType[$maLP] = ['so_dem' => 0, 'doanh_thu' => 0.0];
                        }
                        $byRoomType[$maLP]['doanh_thu'] += $allocatedRooms[$idx] ?? 0.0;
                        if (($allocatedRooms[$idx] ?? 0.0) > 0 && ($rl['TrangThaiPhongPhieu'] ?? '') !== 'DaHuy') {
                            $byRoomType[$maLP]['so_dem'] += (int)$rl['SoDem'];
                        }
                    }
                }

                if (!empty($svcLines)) {
                    $svcWeights = [];
                    foreach ($svcLines as $idx => $sl) {
                        $svcWeights[$idx] = $sl['gross'];
                    }
                    $allocatedSvcs = $this->allocateAmountsProportionally($svcNet, $svcWeights);
                    foreach ($svcLines as $idx => $sl) {
                        $maDV = (string)$sl['MaDV'];
                        $maCTDV = (string)$sl['MaCTDV'];
                        $amt = $allocatedSvcs[$idx] ?? 0.0;
                        $qty = (int)$sl['SoLuong'];

                        if (!isset($byServiceGroup[$maDV])) {
                            $byServiceGroup[$maDV] = ['so_luot' => 0, 'tong_so_luong' => 0, 'doanh_thu' => 0.0];
                        }
                        $byServiceGroup[$maDV]['doanh_thu'] += $amt;
                        if ($amt > 0) {
                            $byServiceGroup[$maDV]['so_luot']++;
                            $byServiceGroup[$maDV]['tong_so_luong'] += $qty;
                        }

                        if (!isset($byServiceItem[$maCTDV])) {
                            $byServiceItem[$maCTDV] = [
                                'ma_ctdv' => $maCTDV,
                                'ten_ctdv' => (string)$sl['TenCTDV'],
                                'ma_dv' => $maDV,
                                'ten_dv' => (string)$sl['TenDV'],
                                'so_luot' => 0,
                                'tong_so_luong' => 0,
                                'doanh_thu' => 0.0,
                            ];
                        }
                        $byServiceItem[$maCTDV]['doanh_thu'] += $amt;
                        if ($amt > 0) {
                            $byServiceItem[$maCTDV]['so_luot']++;
                            $byServiceItem[$maCTDV]['tong_so_luong'] += $qty;
                        }
                    }
                }
            }
        }

        return [
            'tong_doanh_thu' => $tongDoanhThu,
            'tong_tien_thu' => round($tongTienThu),
            'tong_tien_hoan' => round($tongTienHoan),
            'tong_giao_dich' => $tongGiaoDich,
            'doanh_thu_chuyen_khoan' => round($chuyenKhoan),
            'doanh_thu_tien_mat' => round($tienMat),
            'doanh_thu_phong' => round($doanhThuPhong),
            'doanh_thu_dich_vu' => round($doanhThuDichVu),
            'timeline' => $timeline,
            'by_room_type' => $byRoomType,
            'by_service_group' => $byServiceGroup,
            'by_service_item' => $byServiceItem,
        ];
    }

    /**
     * Thống kê doanh thu chi tiết theo khoảng thời gian và phân loại nguồn thu.
     */
    public function thongKeDoanhThu(array $filters = []): array
    {
        [$fromDate, $toDate] = $this->normalizeDateRange($filters, date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));
        $net = $this->summarizeNetRevenue($fromDate, $toDate);

        // Danh sách giao dịch gần nhất
        $sqlGiaoDich = "SELECT 
                            tt.MaThanhToan, tt.MaHD, tt.SoTien, COALESCE(tt.SoTienHoan, 0) AS SoTienHoan,
                            tt.HinhThuc, tt.ThoiGianThanhToan, tt.TrangThaiThanhToan,
                            kh.HoTen AS TenKhachHang, kh.SDT
                        FROM ThanhToan tt
                        JOIN HoaDon hd ON tt.MaHD = hd.MaHD
                        JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                        JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                        ORDER BY tt.ThoiGianThanhToan DESC
                        LIMIT 10";
        $stmtGD = $this->db->query($sqlGiaoDich);
        $dsGiaoDich = $stmtGD->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_quan' => [
                'tong_doanh_thu' => $net['tong_doanh_thu'],
                'tong_tien_thu' => $net['tong_tien_thu'],
                'tong_giao_dich' => $net['tong_giao_dich'],
                'tong_tien_hoan' => $net['tong_tien_hoan'],
                'doanh_thu_chuyen_khoan' => $net['doanh_thu_chuyen_khoan'],
                'doanh_thu_tien_mat' => $net['doanh_thu_tien_mat'],
                'doanh_thu_phong' => $net['doanh_thu_phong'],
                'doanh_thu_dich_vu' => $net['doanh_thu_dich_vu'],
            ],
            'bieu_do_doanh_thu' => $net['timeline'],
            'giao_dich_gan_nhat' => $dsGiaoDich,
        ];
    }

    /**
     * Thống kê doanh thu theo từng Loại Phòng (Tab 1: Thống Kê Doanh Thu Loại Phòng).
     * Doanh thu phản ánh số tiền thực thu ròng (sau KM, VAT và trừ hoàn tiền hủy phòng).
     */
    public function thongKeDoanhThuLoaiPhong(array $filters = []): array
    {
        [$fromDate, $toDate] = $this->normalizeDateRange($filters, date('Y-01-01'), date('Y-12-31'));
        $net = $this->summarizeNetRevenue($fromDate, $toDate);
        $byRoomType = $net['by_room_type'];

        $stmtLP = $this->db->query("SELECT MaLoaiPhong, TenLoaiPhong, Gia AS GiaNiemYet FROM LoaiPhong");
        $roomTypes = $stmtLP->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuPhong = $net['doanh_thu_phong'];
        $tongSoDem = 0;
        $danhSach = [];

        foreach ($roomTypes as $lp) {
            $maLP = (string)$lp['MaLoaiPhong'];
            $stat = $byRoomType[$maLP] ?? ['so_dem' => 0, 'doanh_thu' => 0.0];
            $doanhThu = (float)$stat['doanh_thu'];
            $soDem = (int)$stat['so_dem'];
            $tongSoDem += $soDem;
            $tyTrong = ($tongDoanhThuPhong > 0) ? round(($doanhThu / $tongDoanhThuPhong) * 100, 2) : 0.0;

            $danhSach[] = [
                'ma_loai_phong' => $maLP,
                'ten_loai_phong' => $lp['TenLoaiPhong'],
                'gia_niem_yet' => (float)$lp['GiaNiemYet'],
                'so_dem_su_dung' => $soDem,
                'doanh_thu' => $doanhThu,
                'ty_trong' => $tyTrong,
            ];
        }

        usort($danhSach, static function (array $a, array $b): int {
            $cmp = $b['doanh_thu'] <=> $a['doanh_thu'];
            return $cmp !== 0 ? $cmp : ($b['so_dem_su_dung'] <=> $a['so_dem_su_dung']);
        });

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_doanh_thu_phong' => $tongDoanhThuPhong,
            'tong_so_dem' => $tongSoDem,
            'danh_sach' => $danhSach,
        ];
    }

    /**
     * Thống kê doanh thu theo từng Dịch Vụ (Tab 2: Thống Kê Doanh Thu Dịch Vụ).
     * Doanh thu phản ánh số tiền dịch vụ thực thu ròng từ các giao dịch thành công (đã trừ hoàn nếu có).
     */
    public function thongKeDoanhThuDichVu(array $filters = []): array
    {
        [$fromDate, $toDate] = $this->normalizeDateRange($filters, date('Y-01-01'), date('Y-12-31'));
        $net = $this->summarizeNetRevenue($fromDate, $toDate);
        $byGroup = $net['by_service_group'];
        $byItem = $net['by_service_item'];

        $stmtDV = $this->db->query("SELECT MaDV, TenDV FROM DichVu");
        $services = $stmtDV->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuDichVu = $net['doanh_thu_dich_vu'];
        $tongSoLuot = 0;
        $tongSoLuong = 0;
        $danhSach = [];

        foreach ($services as $dv) {
            $maDV = (string)$dv['MaDV'];
            $stat = $byGroup[$maDV] ?? ['so_luot' => 0, 'tong_so_luong' => 0, 'doanh_thu' => 0.0];
            $doanhThu = (float)$stat['doanh_thu'];
            $soLuot = (int)$stat['so_luot'];
            $soLuong = (int)$stat['tong_so_luong'];
            $tongSoLuot += $soLuot;
            $tongSoLuong += $soLuong;
            $tyTrong = ($tongDoanhThuDichVu > 0) ? round(($doanhThu / $tongDoanhThuDichVu) * 100, 2) : 0.0;

            $danhSach[] = [
                'ma_dv' => $maDV,
                'ten_dv' => $dv['TenDV'],
                'so_luot_su_dung' => $soLuot,
                'tong_so_luong' => $soLuong,
                'doanh_thu' => $doanhThu,
                'ty_trong' => $tyTrong,
            ];
        }

        usort($danhSach, static function (array $a, array $b): int {
            $cmp = $b['doanh_thu'] <=> $a['doanh_thu'];
            return $cmp !== 0 ? $cmp : ($b['so_luot_su_dung'] <=> $a['so_luot_su_dung']);
        });

        $chiTietCacMon = [];
        foreach ($byItem as $it) {
            $doanhThuIt = (float)$it['doanh_thu'];
            $tyTrongIt = ($tongDoanhThuDichVu > 0) ? round(($doanhThuIt / $tongDoanhThuDichVu) * 100, 2) : 0.0;
            $chiTietCacMon[] = [
                'ma_ctdv' => $it['ma_ctdv'],
                'ten_ctdv' => $it['ten_ctdv'],
                'ma_dv' => $it['ma_dv'],
                'ten_dv' => $it['ten_dv'],
                'so_luot_su_dung' => (int)$it['so_luot'],
                'tong_so_luong' => (int)$it['tong_so_luong'],
                'doanh_thu' => $doanhThuIt,
                'ty_trong' => $tyTrongIt,
            ];
        }

        usort($chiTietCacMon, static function (array $a, array $b): int {
            return $b['doanh_thu'] <=> $a['doanh_thu'];
        });

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_doanh_thu_dich_vu' => $tongDoanhThuDichVu,
            'tong_so_luot' => $tongSoLuot,
            'tong_so_luong' => $tongSoLuong,
            'danh_sach' => $danhSach,
            'chi_tiet_mon' => $chiTietCacMon,
        ];
    }

    /**
     * Thống kê công suất phòng theo đặc tả usecase UC21.2.
     * Công thức: Công suất phòng = (Số đêm phòng thực tế sử dụng / Số đêm phòng có thể bán) * 100%
     * - Số đêm phòng có thể bán = Tổng số phòng * Số ngày trong kỳ lọc.
     * - Số đêm phòng thực tế sử dụng = Tổng số đêm khách đã thực tế lưu trú (DangLuuTru, DaTraPhong) có giao trong kỳ.
     * - Trường hợp 0 đêm phòng có thể bán: trả về null ("Không áp dụng" - N/A).
     * - Hỗ trợ lọc theo loại phòng nếu $filters['ma_loai_phong'] được truyền vào.
     */
    public function thongKeCongSuatPhong(array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : (!empty($filters['tu_ngay']) ? $filters['tu_ngay'] : date('Y-m-01'));
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : (!empty($filters['den_ngay']) ? $filters['den_ngay'] : date('Y-m-d'));
        $filterMaLoaiPhong = !empty($filters['ma_loai_phong']) ? trim($filters['ma_loai_phong']) : null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        if ($fromDateClean > $toDateClean) {
            throw new \InvalidArgumentException("Ngày bắt đầu ({$fromDateClean}) không được lớn hơn ngày kết thúc ({$toDateClean})!");
        }

        $daysInPeriod = (int)((strtotime($toDateClean) - strtotime($fromDateClean)) / 86400) + 1;
        $dEndPlus1 = date('Y-m-d', strtotime($toDateClean . ' +1 day'));

        // 1. Lấy danh sách loại phòng và số phòng có thể bán (loại phòng BaoTri và loại phòng NgungKinhDoanh theo đặc tả UC21.2)
        $sqlLP = "
            SELECT lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang,
                   COUNT(CASE WHEN lp.TinhTrang <> 'NgungKinhDoanh' AND p.TrangThaiPhong <> 'BaoTri' THEN p.MaPhong END) as TongSoPhong,
                   COUNT(p.MaPhong) as TongSoPhongVatLy
            FROM LoaiPhong lp
            LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
            GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang
            ORDER BY lp.MaLoaiPhong ASC
        ";
        $loaiPhongs = $this->db->query($sqlLP)->fetchAll(PDO::FETCH_ASSOC);

        // 2. Lấy các lượt đặt phòng thực tế (DangLuuTru, DaTraPhong) có giao với khoảng thời gian
        $sqlBookings = "
            SELECT ctpnp.MaPhieu, ctpnp.MaPhong, p.MaLoaiPhong,
                   DATE(ctpnp.ThoiGianNhanPhong) as CheckIn,
                   DATE(ctpnp.ThoiGianTraPhong) as CheckOut
            FROM ChiTietPhieuNhanPhong ctpnp
            JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
            JOIN PhieuNhanPhong pnp ON ctpnp.MaPhieu = pnp.MaPhieu
            WHERE ctpnp.TrangThai IN ('DangLuuTru', 'DaTraPhong')
              AND pnp.TrangThai NOT IN ('DaHuy', 'ThanhToanThatBai')
              AND DATE(ctpnp.ThoiGianNhanPhong) <= ?
              AND (
                  DATE(ctpnp.ThoiGianTraPhong) >= ?
                  OR (DATE(ctpnp.ThoiGianTraPhong) = DATE(ctpnp.ThoiGianNhanPhong) AND DATE(ctpnp.ThoiGianNhanPhong) >= ?)
              )
        ";
        $stmtBk = $this->db->prepare($sqlBookings);
        $stmtBk->execute([$toDateClean, $fromDateClean, $fromDateClean]);
        $bookings = $stmtBk->fetchAll(PDO::FETCH_ASSOC);

        // Tính số đêm thực tế theo từng loại phòng
        $demSuDungTheoLoai = [];
        foreach ($loaiPhongs as $lp) {
            $demSuDungTheoLoai[$lp['MaLoaiPhong']] = 0;
        }

        foreach ($bookings as $b) {
            $cIn = $b['CheckIn'];
            $cOut = $b['CheckOut'];
            $effCOut = ($cOut <= $cIn) ? date('Y-m-d', strtotime($cIn . ' +1 day')) : $cOut;

            $overlapStart = max($fromDateClean, $cIn);
            $overlapEnd = min($dEndPlus1, $effCOut);

            $diff = (int)((strtotime($overlapEnd) - strtotime($overlapStart)) / 86400);
            if ($diff > 0) {
                $maLP = $b['MaLoaiPhong'];
                if (isset($demSuDungTheoLoai[$maLP])) {
                    $demSuDungTheoLoai[$maLP] += $diff;
                }
            }
        }

        // 3. Tổng hợp số liệu theo loại phòng và toàn khách sạn
        $tongSoPhongToanKS = 0;
        $tongDemCoTheBanToanKS = 0;
        $tongDemThucTeToanKS = 0;

        $danhSach = [];
        foreach ($loaiPhongs as $lp) {
            $soPhong = (int)$lp['TongSoPhong'];
            $soDemCoTheBan = $soPhong * $daysInPeriod;
            $soDemThucTe = $demSuDungTheoLoai[$lp['MaLoaiPhong']] ?? 0;

            $tongSoPhongToanKS += $soPhong;
            $tongDemCoTheBanToanKS += $soDemCoTheBan;
            $tongDemThucTeToanKS += $soDemThucTe;

            $congSuat = null;
            if ($soDemCoTheBan > 0) {
                $congSuat = round(($soDemThucTe / $soDemCoTheBan) * 100, 2);
            }

            $danhSach[] = [
                'ma_loai_phong' => $lp['MaLoaiPhong'],
                'ten_loai_phong' => $lp['TenLoaiPhong'],
                'tong_so_phong' => $soPhong,
                'so_dem_co_the_ban' => $soDemCoTheBan,
                'so_dem_su_dung' => $soDemThucTe,
                'cong_suat_phong' => $congSuat, // null nếu N/A
                'gia_niem_yet' => (float)$lp['Gia'],
                'tinh_trang' => $lp['TinhTrang']
            ];
        }

        $congSuatToanKS = null;
        if ($tongDemCoTheBanToanKS > 0) {
            $congSuatToanKS = round(($tongDemThucTeToanKS / $tongDemCoTheBanToanKS) * 100, 2);
        }

        // Lọc danh sách theo loại phòng nếu được yêu cầu
        $danhSachKetQua = $danhSach;
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $danhSachKetQua = array_values(array_filter($danhSach, function($it) use ($filterMaLoaiPhong) {
                return $it['ma_loai_phong'] === $filterMaLoaiPhong;
            }));
        }

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'so_ngay_trong_ky' => $daysInPeriod,
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all'
            ],
            'tong_quan' => [
                'tong_so_phong' => $tongSoPhongToanKS,
                'tong_dem_co_the_ban' => $tongDemCoTheBanToanKS,
                'tong_dem_thuc_te' => $tongDemThucTeToanKS,
                'cong_suat_trung_binh' => $congSuatToanKS,
            ],
            'danh_sach' => $danhSachKetQua
        ];
    }

    /* =========================================================================
     * THỐNG KÊ ĐÁNH GIÁ (UC21.3)
     * ========================================================================= */

    /**
     * Thống kê đánh giá theo loại phòng (UC21.3).
     */
    public function thongKeDanhGiaTheoLoaiPhong(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $filterMaLoaiPhong = $filters['ma_loai_phong'] ?? null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $sql = "SELECT 
                    lp.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    lp.Gia,
                    lp.TinhTrang,
                    COUNT(d.MaDanhGia) AS so_luot_danh_gia,
                    COALESCE(SUM(d.SoSao), 0) AS tong_so_sao,
                    ROUND(AVG(d.SoSao), 2) AS diem_trung_binh,
                    COALESCE(SUM(CASE WHEN d.SoSao = 1 THEN 1 ELSE 0 END), 0) AS so_luot_1_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 2 THEN 1 ELSE 0 END), 0) AS so_luot_2_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 3 THEN 1 ELSE 0 END), 0) AS so_luot_3_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 4 THEN 1 ELSE 0 END), 0) AS so_luot_4_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 5 THEN 1 ELSE 0 END), 0) AS so_luot_5_sao
                FROM LoaiPhong lp
                LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
                LEFT JOIN DanhGia d ON p.MaPhong = d.MaPhong 
                    AND d.NgayDanhGia >= :fromDate 
                    AND d.NgayDanhGia <= :toDate
                    AND d.SoSao BETWEEN 1 AND 5
                GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.TinhTrang
                ORDER BY so_luot_danh_gia DESC, diem_trung_binh DESC, lp.MaLoaiPhong ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongLuotToanKS = 0;
        $tongSoSaoToanKS = 0;
        $soLoaiPhongCoDanhGia = 0;
        $soLuot5SaoToanKS = 0;

        $danhSach = [];
        foreach ($rows as $row) {
            $n = (int)$row['so_luot_danh_gia'];
            $s = (int)$row['tong_so_sao'];
            $s1 = (int)$row['so_luot_1_sao'];
            $s2 = (int)$row['so_luot_2_sao'];
            $s3 = (int)$row['so_luot_3_sao'];
            $s4 = (int)$row['so_luot_4_sao'];
            $s5 = (int)$row['so_luot_5_sao'];

            $tongLuotToanKS += $n;
            $tongSoSaoToanKS += $s;
            if ($n > 0) {
                $soLoaiPhongCoDanhGia++;
            }
            $soLuot5SaoToanKS += $s5;

            $diemTB = $n > 0 ? round($s / $n, 2) : null;
            $tyLe45 = $n > 0 ? round((($s4 + $s5) / $n) * 100, 2) : null;

            $danhSach[] = [
                'ma_loai_phong' => $row['MaLoaiPhong'],
                'ten_loai_phong' => $row['TenLoaiPhong'],
                'gia_niem_yet' => (float)$row['Gia'],
                'tinh_trang' => $row['TinhTrang'],
                'so_luot_danh_gia' => $n,
                'tong_so_sao' => $s,
                'diem_trung_binh' => $diemTB, // null nếu N = 0
                'so_luot_1_sao' => $s1,
                'so_luot_2_sao' => $s2,
                'so_luot_3_sao' => $s3,
                'so_luot_4_sao' => $s4,
                'so_luot_5_sao' => $s5,
                'ty_le_4_5_sao' => $tyLe45
            ];
        }

        // Lọc theo loại phòng cụ thể nếu có
        $danhSachKetQua = $danhSach;
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $danhSachKetQua = array_values(array_filter($danhSach, function($it) use ($filterMaLoaiPhong) {
                return $it['ma_loai_phong'] === $filterMaLoaiPhong;
            }));
        }

        $diemTBToanKS = $tongLuotToanKS > 0 ? round($tongSoSaoToanKS / $tongLuotToanKS, 2) : null;

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'che_do' => 'room_type',
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all'
            ],
            'tong_quan' => [
                'tong_luot_danh_gia' => $tongLuotToanKS,
                'diem_trung_binh' => $diemTBToanKS,
                'so_loai_phong_co_danh_gia' => $soLoaiPhongCoDanhGia,
                'so_luot_5_sao' => $soLuot5SaoToanKS,
                'tong_so_loai_phong' => count($danhSachKetQua)
            ],
            'danh_sach' => $danhSachKetQua
        ];
    }

    /**
     * Thống kê đánh giá theo từng phòng (UC21.3).
     */
    public function thongKeDanhGiaTheoPhong(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $filterMaLoaiPhong = $filters['ma_loai_phong'] ?? null;
        $keyword = trim($filters['keyword'] ?? '');

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $params = [
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ];

        $whereClause = "";
        if (!empty($filterMaLoaiPhong) && $filterMaLoaiPhong !== 'all') {
            $whereClause .= " AND lp.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $filterMaLoaiPhong;
        }

        if ($keyword !== '') {
            $whereClause .= " AND (p.MaPhong LIKE :kw OR lp.TenLoaiPhong LIKE :kw)";
            $params[':kw'] = '%' . $keyword . '%';
        }

        $sql = "SELECT 
                    p.MaPhong,
                    p.TrangThaiPhong,
                    lp.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    COUNT(d.MaDanhGia) AS so_luot_danh_gia,
                    COALESCE(SUM(d.SoSao), 0) AS tong_so_sao,
                    ROUND(AVG(d.SoSao), 2) AS diem_trung_binh,
                    COALESCE(SUM(CASE WHEN d.SoSao = 1 THEN 1 ELSE 0 END), 0) AS so_luot_1_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 2 THEN 1 ELSE 0 END), 0) AS so_luot_2_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 3 THEN 1 ELSE 0 END), 0) AS so_luot_3_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 4 THEN 1 ELSE 0 END), 0) AS so_luot_4_sao,
                    COALESCE(SUM(CASE WHEN d.SoSao = 5 THEN 1 ELSE 0 END), 0) AS so_luot_5_sao
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN DanhGia d ON p.MaPhong = d.MaPhong 
                    AND d.NgayDanhGia >= :fromDate 
                    AND d.NgayDanhGia <= :toDate
                    AND d.SoSao BETWEEN 1 AND 5
                WHERE 1=1 {$whereClause}
                GROUP BY p.MaPhong, p.TrangThaiPhong, lp.MaLoaiPhong, lp.TenLoaiPhong
                ORDER BY so_luot_danh_gia DESC, diem_trung_binh DESC, p.MaPhong ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongLuot = 0;
        $tongSoSao = 0;
        $soPhongCoDanhGia = 0;
        $soLuot12Sao = 0;

        $danhSach = [];
        foreach ($rows as $row) {
            $n = (int)$row['so_luot_danh_gia'];
            $s = (int)$row['tong_so_sao'];
            $s1 = (int)$row['so_luot_1_sao'];
            $s2 = (int)$row['so_luot_2_sao'];
            $s3 = (int)$row['so_luot_3_sao'];
            $s4 = (int)$row['so_luot_4_sao'];
            $s5 = (int)$row['so_luot_5_sao'];

            $tongLuot += $n;
            $tongSoSao += $s;
            if ($n > 0) {
                $soPhongCoDanhGia++;
            }
            $soLuot12Sao += ($s1 + $s2);

            $diemTB = $n > 0 ? round($s / $n, 2) : null;

            $danhSach[] = [
                'ma_phong' => $row['MaPhong'],
                'trang_thai_phong' => $row['TrangThaiPhong'],
                'ma_loai_phong' => $row['MaLoaiPhong'],
                'ten_loai_phong' => $row['TenLoaiPhong'],
                'so_luot_danh_gia' => $n,
                'tong_so_sao' => $s,
                'diem_trung_binh' => $diemTB, // null nếu N = 0
                'so_luot_1_sao' => $s1,
                'so_luot_2_sao' => $s2,
                'so_luot_3_sao' => $s3,
                'so_luot_4_sao' => $s4,
                'so_luot_5_sao' => $s5
            ];
        }

        $diemTBToanBo = $tongLuot > 0 ? round($tongSoSao / $tongLuot, 2) : null;

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDateClean,
                'to_date' => $toDateClean,
                'che_do' => 'room',
                'ma_loai_phong_loc' => $filterMaLoaiPhong ?: 'all',
                'keyword' => $keyword
            ],
            'tong_quan' => [
                'tong_luot_danh_gia' => $tongLuot,
                'diem_trung_binh' => $diemTBToanBo,
                'so_phong_co_danh_gia' => $soPhongCoDanhGia,
                'so_luot_1_2_sao' => $soLuot12Sao,
                'so_danh_gia_1_2_sao' => $soLuot12Sao,
                'tong_so_phong' => count($danhSach)
            ],
            'danh_sach' => $danhSach
        ];
    }

    /**
     * Lấy danh sách đánh giá chi tiết theo loại phòng hoặc theo phòng cụ thể (UC21.3).
     */
    public function danhSachChiTietDanhGia(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');
        $maLoaiPhong = $filters['ma_loai_phong'] ?? null;
        $maPhong = $filters['ma_phong'] ?? null;

        $fromDateClean = substr($fromDate, 0, 10);
        $toDateClean = substr($toDate, 0, 10);

        $params = [
            ':fromDate' => $fromDateClean,
            ':toDate' => $toDateClean
        ];

        $where = "d.NgayDanhGia >= :fromDate AND d.NgayDanhGia <= :toDate AND d.SoSao BETWEEN 1 AND 5";

        if (!empty($maLoaiPhong) && $maLoaiPhong !== 'all') {
            $where .= " AND p.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $maLoaiPhong;
        }

        if (!empty($maPhong) && $maPhong !== 'all') {
            $where .= " AND d.MaPhong = :maPhong";
            $params[':maPhong'] = $maPhong;
        }

        $sql = "SELECT 
                    d.MaDanhGia,
                    d.SoSao,
                    d.NoiDung,
                    d.NgayDanhGia,
                    d.MaPhieu,
                    d.MaPhong,
                    p.MaLoaiPhong,
                    lp.TenLoaiPhong,
                    k.MaKH,
                    k.HoTen AS HoTenKhachHang
                FROM DanhGia d
                JOIN Phong p ON d.MaPhong = p.MaPhong
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN KhachHang k ON d.MaKH = k.MaKH
                WHERE {$where}
                ORDER BY d.NgayDanhGia DESC, d.MaDanhGia DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * THỐNG KÊ SỐ LƯỢNG / TÌNH TRẠNG PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê số lượng phòng, trạng thái sử dụng và phân bổ loại phòng.
     */
    public function thongKeSoLuongPhong(): array
    {
        // 1. Tổng số phòng và phân loại theo trạng thái
        $sqlTrangThai = "SELECT 
                            TrangThaiPhong,
                            COUNT(*) AS SoLuong
                         FROM Phong
                         GROUP BY TrangThaiPhong";
        $stmtTT = $this->db->query($sqlTrangThai);
        $trangThaiList = $stmtTT->fetchAll(PDO::FETCH_ASSOC);

        $soLuongTheoTrangThai = [
            'Trong' => 0,
            'DaDat' => 0,
            'DangSuDung' => 0,
            'BaoTri' => 0,
        ];
        $tongPhong = 0;

        foreach ($trangThaiList as $row) {
            $tt = $row['TrangThaiPhong'];
            $sl = (int)$row['SoLuong'];
            $tongPhong += $sl;
            if (isset($soLuongTheoTrangThai[$tt])) {
                $soLuongTheoTrangThai[$tt] = $sl;
            } else {
                $soLuongTheoTrangThai[$tt] = $sl;
            }
        }

        // Tỷ lệ lấp đầy phòng (%) = (Đang sử dụng + Đã đặt) / Tổng phòng
        $phongBan = $soLuongTheoTrangThai['DangSuDung'] + $soLuongTheoTrangThai['DaDat'];
        $tyLeLapDay = $tongPhong > 0 ? round(($phongBan / $tongPhong) * 100, 1) : 0;

        // 2. Thống kê theo Loại Phòng
        $sqlLoaiPhong = "SELECT 
                            lp.MaLoaiPhong,
                            lp.TenLoaiPhong,
                            lp.Gia,
                            lp.SoNguoiToiDa,
                            COUNT(p.MaPhong) AS TongSoPhong,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'Trong' THEN 1 ELSE 0 END), 0) AS PhongTrong,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'DangSuDung' THEN 1 ELSE 0 END), 0) AS PhongDangO,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'DaDat' THEN 1 ELSE 0 END), 0) AS PhongDaDat,
                            COALESCE(SUM(CASE WHEN p.TrangThaiPhong = 'BaoTri' THEN 1 ELSE 0 END), 0) AS PhongBaoTri
                         FROM LoaiPhong lp
                         LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
                         GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa";
        $stmtLP = $this->db->query($sqlLoaiPhong);
        $loaiPhongList = $stmtLP->fetchAll(PDO::FETCH_ASSOC);

        // 3. Danh sách toàn bộ phòng và trạng thái chi tiết
        $sqlDanhSachPhong = "SELECT 
                                p.MaPhong,
                                p.TrangThaiPhong,
                                lp.TenLoaiPhong,
                                lp.Gia
                             FROM Phong p
                             JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                             ORDER BY p.MaPhong ASC";
        $stmtDSP = $this->db->query($sqlDanhSachPhong);
        $dsPhong = $stmtDSP->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tong_so_phong' => $tongPhong,
            'ty_le_lap_day' => $tyLeLapDay,
            'theo_trang_thai' => $soLuongTheoTrangThai,
            'theo_loai_phong' => $loaiPhongList,
            'danh_sach_phong' => $dsPhong,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ PHIẾU ĐẶT PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê số lượng phiếu nhận phòng theo trạng thái, kênh đặt và thời gian.
     */
    public function thongKeSoLuongPhieu(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? date('Y-01-01');
        $toDate = $filters['to_date'] ?? date('Y-12-31');

        // 1. Thống kê theo trạng thái phiếu
        $sqlTrangThai = "SELECT 
                            TrangThai,
                            COUNT(*) AS SoLuong
                         FROM PhieuNhanPhong
                         WHERE NgayLap >= :fromDate AND NgayLap <= :toDate
                         GROUP BY TrangThai";
        $stmtTT = $this->db->prepare($sqlTrangThai);
        $stmtTT->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $trangThaiPhieu = $stmtTT->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 2. Thống kê theo kênh đặt
        $sqlKenhDat = "SELECT 
                          KenhDat,
                          COUNT(*) AS SoLuong
                       FROM PhieuNhanPhong
                       WHERE NgayLap >= :fromDate AND NgayLap <= :toDate
                       GROUP BY KenhDat";
        $stmtKenh = $this->db->prepare($sqlKenhDat);
        $stmtKenh->execute([':fromDate' => $fromDate, ':toDate' => $toDate]);
        $kenhDatPhieu = $stmtKenh->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 3. Tổng số phiếu
        $tongSoPhieu = array_sum($trangThaiPhieu);

        // 4. Danh sách các phiếu mới nhất kèm thông tin
        $sqlRecent = "SELECT 
                        pnp.MaPhieu,
                        pnp.NgayLap,
                        pnp.KenhDat,
                        pnp.TrangThai,
                        kh.HoTen AS TenKhachHang,
                        kh.SDT,
                        COALESCE(hd.TongTienSauVAT, 0) AS TongTien
                      FROM PhieuNhanPhong pnp
                      JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                      LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
                      ORDER BY pnp.NgayLap DESC
                      LIMIT 10";
        $stmtRecent = $this->db->query($sqlRecent);
        $danhSachPhieu = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        return [
            'ky_thong_ke' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'tong_so_phieu' => $tongSoPhieu,
            'theo_trang_thai' => $trangThaiPhieu,
            'theo_kenh_dat' => $kenhDatPhieu,
            'danh_sach_phieu' => $danhSachPhieu,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ DỊCH VỤ SỬ DỤNG (BE4)
     * ========================================================================= */

    /**
     * Thống kê dịch vụ sử dụng: top dịch vụ tiêu thụ, doanh thu và tồn kho.
     */
    public function thongKeDichVu(): array
    {
        // 1. Top dịch vụ sử dụng nhiều nhất từ các hóa đơn đã thanh toán thành công
        $sqlTopDichVu = "SELECT 
                            ctdv.MaCTDV,
                            ctdv.TenCTDV,
                            dv.TenDV AS NhomDichVu,
                            COUNT(cthd.MaCTHD) AS SoLuotDat,
                            COALESCE(SUM(cthd.SoLuong), 0) AS TongSoLuongTieuThu,
                            COALESCE(SUM(cthd.SoLuong * cthd.DonGia), 0) AS TongDoanhThuDichVu
                         FROM CTHD cthd
                         JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
                         JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                         WHERE EXISTS (
                             SELECT 1 FROM ThanhToan tt
                             WHERE tt.MaHD = cthd.MaHD
                               AND tt.TrangThaiThanhToan IN ('HoanTat', 'HoanTienMotPhan', 'DaHoanTien')
                         )
                         GROUP BY ctdv.MaCTDV, ctdv.TenCTDV, dv.TenDV
                         ORDER BY TongDoanhThuDichVu DESC";
        $stmtTop = $this->db->query($sqlTopDichVu);
        $topDichVu = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        // 2. Thống kê theo nhóm dịch vụ
        $sqlNhom = "SELECT 
                        dv.MaDV,
                        dv.TenDV,
                        COUNT(ctdv.MaCTDV) AS SoLuongMatHang,
                        COALESCE(SUM(ctdv.SoLuongTon), 0) AS TongTonKho
                    FROM DichVu dv
                    LEFT JOIN ChiTietDichVu ctdv ON dv.MaDV = ctdv.MaDV
                    GROUP BY dv.MaDV, dv.TenDV";
        $stmtNhom = $this->db->query($sqlNhom);
        $nhomDichVu = $stmtNhom->fetchAll(PDO::FETCH_ASSOC);

        // 3. Tình trạng tồn kho của các mặt hàng
        $sqlTonKho = "SELECT 
                        ctdv.MaCTDV,
                        ctdv.TenCTDV,
                        ctdv.DonGia,
                        ctdv.SoLuongTon,
                        dv.TenDV AS NhomDichVu,
                        CASE 
                            WHEN ctdv.SoLuongTon = 0 THEN 'HetHang'
                            WHEN ctdv.SoLuongTon < 10 THEN 'SapHet'
                            ELSE 'ConHang'
                        END AS TrangThaiKho
                      FROM ChiTietDichVu ctdv
                      JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                      ORDER BY ctdv.SoLuongTon ASC";
        $stmtTonKho = $this->db->query($sqlTonKho);
        $tonKho = $stmtTonKho->fetchAll(PDO::FETCH_ASSOC);

        $tongDoanhThuDichVu = 0.0;
        foreach ($topDichVu as $item) {
            $tongDoanhThuDichVu += (float)$item['TongDoanhThuDichVu'];
        }

        return [
            'tong_doanh_thu_dich_vu' => $tongDoanhThuDichVu,
            'top_dich_vu' => $topDichVu,
            'nhom_dich_vu' => $nhomDichVu,
            'ton_kho_dich_vu' => $tonKho,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ TỔNG QUAN DASHBOARD (BE4)
     * ========================================================================= */

    /**
     * Tổng hợp các chỉ số KPI trọng yếu nhất phục vụ trang chủ Admin.
     */
    public function thongKeTongQuan(): array
    {
        $doanhThu = $this->thongKeDoanhThu();
        $phong = $this->thongKeSoLuongPhong();
        $phieu = $this->thongKeSoLuongPhieu();
        $dichVu = $this->thongKeDichVu();

        return [
            'status' => 'success',
            'kpi' => [
                'tong_doanh_thu' => $doanhThu['tong_quan']['tong_doanh_thu'],
                'tong_giao_dich' => $doanhThu['tong_quan']['tong_giao_dich'],
                'tong_phong' => $phong['tong_so_phong'],
                'ty_le_lap_day' => $phong['ty_le_lap_day'],
                'phong_dang_o' => $phong['theo_trang_thai']['DangSuDung'] ?? 0,
                'phong_trong' => $phong['theo_trang_thai']['Trong'] ?? 0,
                'tong_phieu_dat' => $phieu['tong_so_phieu'],
                'doanh_thu_dich_vu' => $dichVu['tong_doanh_thu_dich_vu'],
            ],
            'chi_tiet_doanh_thu' => $doanhThu['tong_quan'],
            'bieu_do_doanh_thu' => $doanhThu['bieu_do_doanh_thu'],
            'trang_thai_phong' => $phong['theo_trang_thai'],
            'giao_dich_gan_nhat' => $doanhThu['giao_dich_gan_nhat'],
            'phieu_dat_gan_nhat' => $phieu['danh_sach_phieu'],
            'top_dich_vu' => array_slice($dichVu['top_dich_vu'], 0, 5),
        ];
    }

    /* =========================================================================
     * QUẢN LÝ NHÂN VIÊN & LỄ TÂN (Hỗ trợ chia sẻ module)
     * ========================================================================= */

    public function thongKeSoLuongNhanVien(): array
    {
        $sqlAdmin = "SELECT COUNT(*) FROM Admin";
        $sqlLeTan = "SELECT COUNT(*) FROM LeTan";
        $soAdmin = (int)$this->db->query($sqlAdmin)->fetchColumn();
        $soLeTan = (int)$this->db->query($sqlLeTan)->fetchColumn();

        return [
            'tong_nhan_su' => $soAdmin + $soLeTan,
            'so_admin' => $soAdmin,
            'so_le_tan' => $soLeTan,
        ];
    }
}