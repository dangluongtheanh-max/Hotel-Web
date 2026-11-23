<?php

declare(strict_types=1);

namespace Shared;

/**
 * Nguồn chuẩn duy nhất (Single Source of Truth) cho Chính sách Hủy phòng & Hoàn tiền.
 *
 * Quy tắc áp dụng thống nhất toàn hệ thống (Đặt phòng, Lễ tân, Thanh toán):
 * - Trước ngày nhận phòng từ 7 ngày trở lên: Hoàn tiền 100%, Phí hủy 0%
 * - Trước ngày nhận phòng từ 3 đến dưới 7 ngày: Hoàn tiền 50%, Phí hủy 50%
 * - Trước ngày nhận phòng dưới 3 ngày: Hoàn tiền 0%, Phí hủy 100%
 * - Không đến nhận phòng, không báo hủy (No-show — quá giờ trả phòng dự kiến): Hoàn tiền 0%, Phí hủy 100%
 */
class ChinhSachHoanHuy
{
    public const MOC_HOAN_100_NGAY = 7;
    public const MOC_HOAN_50_NGAY = 3;

    public const TY_LE_HOAN_100 = 100.0;
    public const TY_LE_HOAN_50 = 50.0;
    public const TY_LE_HOAN_0 = 0.0;

    public const TT_HOAN_TAT = 'HoanTat';
    public const TT_CHO_THANH_TOAN = 'ChoThanhToan';
    public const TT_DA_HUY = 'DaHuy';
    public const TT_HOAN_MOT_PHAN = 'HoanTienMotPhan';
    public const TT_DA_HOAN_TIEN = 'DaHoanTien';

    /**
     * Danh sách 4 mốc chính sách chuẩn để trả API & đồng bộ UI.
     */
    public static function danhSachQuyTac(): array
    {
        return [
            [
                'maMoc' => 'BEFORE_7_DAYS',
                'thoiDiem' => 'Trước ngày nhận phòng từ 7 ngày trở lên',
                'minDays' => self::MOC_HOAN_100_NGAY,
                'maxDays' => null,
                'tyLeHoan' => 100,
                'tyLePhiHuy' => 0,
                'moTa' => 'Hoàn 100%, Phí hủy 0%',
            ],
            [
                'maMoc' => 'FROM_3_TO_7_DAYS',
                'thoiDiem' => 'Trước ngày nhận phòng từ 3 đến dưới 7 ngày',
                'minDays' => self::MOC_HOAN_50_NGAY,
                'maxDays' => self::MOC_HOAN_100_NGAY,
                'tyLeHoan' => 50,
                'tyLePhiHuy' => 50,
                'moTa' => 'Hoàn 50%, Phí hủy 50%',
            ],
            [
                'maMoc' => 'UNDER_3_DAYS',
                'thoiDiem' => 'Trước ngày nhận phòng dưới 3 ngày',
                'minDays' => 0,
                'maxDays' => self::MOC_HOAN_50_NGAY,
                'tyLeHoan' => 0,
                'tyLePhiHuy' => 100,
                'moTa' => 'Hoàn 0%, Phí hủy 100%',
            ],
            [
                'maMoc' => 'NO_SHOW',
                'thoiDiem' => 'Không đến nhận phòng, không báo hủy',
                'minDays' => null,
                'maxDays' => 0,
                'tyLeHoan' => 0,
                'tyLePhiHuy' => 100,
                'moTa' => 'Hoàn 0%, Phí hủy 100%',
            ],
        ];
    }

    /**
     * Tính tỷ lệ hoàn, tiền hoàn và phí hủy từ thời gian nhận phòng & số tiền cơ sở đã thanh toán.
     */
    public static function tinhHoanTheoThoiGian(
        string $thoiGianNhan,
        float $soTienCoSo,
        ?int $nowTs = null,
        bool $isNoShow = false
    ): array {
        $now = $nowTs ?? time();
        $checkinTs = strtotime($thoiGianNhan);
        if ($checkinTs === false) {
            $checkinTs = $now;
        }

        $diffSeconds = $checkinTs - $now;
        $diffDays = $diffSeconds / 86400.0;
        $soTienCoSo = max(0.0, round($soTienCoSo, 0));

        if ($isNoShow) {
            $tiLeHoan = self::TY_LE_HOAN_0;
            $maMoc = 'NO_SHOW';
            $moTa = 'Không đến nhận phòng, không báo hủy (Hoàn 0% - Phí hủy 100%)';
        } elseif ($diffDays >= self::MOC_HOAN_100_NGAY) {
            $tiLeHoan = self::TY_LE_HOAN_100;
            $maMoc = 'BEFORE_7_DAYS';
            $moTa = 'Trước ngày nhận phòng từ 7 ngày trở lên (Hoàn 100%, Phí hủy 0%)';
        } elseif ($diffDays >= self::MOC_HOAN_50_NGAY) {
            $tiLeHoan = self::TY_LE_HOAN_50;
            $maMoc = 'FROM_3_TO_7_DAYS';
            $moTa = 'Trước ngày nhận phòng từ 3 đến dưới 7 ngày (Hoàn 50%, Phí hủy 50%)';
        } elseif ($diffDays > 0) {
            $tiLeHoan = self::TY_LE_HOAN_0;
            $maMoc = 'UNDER_3_DAYS';
            $moTa = 'Trước ngày nhận phòng dưới 3 ngày (Hoàn 0% - Phí hủy 100%)';
        } else {
            $tiLeHoan = self::TY_LE_HOAN_0;
            $maMoc = 'NO_SHOW';
            $moTa = 'Đã qua thời gian nhận phòng (Hoàn 0% - Phí hủy 100%)';
        }

        $tiLePhiHuy = 100.0 - $tiLeHoan;
        $soTienHoan = round(($soTienCoSo * $tiLeHoan) / 100, 0);
        $phiHuy = max(0.0, round($soTienCoSo - $soTienHoan, 0));

        return [
            'maMoc' => $maMoc,
            'moTaChinhSach' => $moTa,
            'chinhSach' => $moTa,
            'ngayNhan' => $thoiGianNhan,
            'diffDays' => round($diffDays, 2),
            'soNgayConLai' => round($diffDays, 2),
            'soGioConLai' => round($diffSeconds / 3600, 1),
            'soTienCoSo' => $soTienCoSo,
            'tiLeHoan' => $tiLeHoan,
            'tiLePhiHuy' => $tiLePhiHuy,
            'tyLeHoan' => $tiLeHoan,
            'tyLePhiHuy' => $tiLePhiHuy,
            'soTienHoan' => $soTienHoan,
            'tienHoan' => $soTienHoan,
            'phiHuy' => $phiHuy,
        ];
    }

    /**
     * Tính phần tiền cơ sở thực thu cho danh sách phòng cần hủy (phân bổ theo tỷ lệ thực thu khi có KM/VAT).
     */
    public static function tinhTienCoSoPhanBo(
        float $giaTriPhongHuy,
        float $tongGiaTriTatCaPhong,
        float $tongDaThanhToan,
        float $tongDaHoanTruocDo = 0.0,
        float $tongPhiHuyTruocDo = 0.0,
        bool $isCancelAllRemaining = false
    ): float {
        if ($tongDaThanhToan <= 0) {
            return 0.0;
        }

        $conLaiKhaDung = max(0.0, $tongDaThanhToan - $tongDaHoanTruocDo - $tongPhiHuyTruocDo);
        if ($tongGiaTriTatCaPhong <= 0) {
            return round($conLaiKhaDung, 0);
        }

        $tyTrong = min(1.0, max(0.0, $giaTriPhongHuy / $tongGiaTriTatCaPhong));
        $phanBo = round($tongDaThanhToan * $tyTrong, 0);

        if ($isCancelAllRemaining && abs($conLaiKhaDung - $phanBo) <= 10) {
            return round($conLaiKhaDung, 0);
        }

        return min($conLaiKhaDung, $phanBo);
    }

    /**
     * Quy tắc No-show đã chốt: khách đã thanh toán toàn bộ tiền nên phòng được giữ trọn kỳ đặt;
     * chỉ quá giờ trả phòng dự kiến (NOW > ThoiGianTraPhong) mà chưa check-in mới được hủy No-show.
     */
    public static function daQuaHanTraPhong(string $thoiGianTraPhong, ?int $nowTs = null): bool
    {
        $now = $nowTs ?? time();
        $traTs = strtotime($thoiGianTraPhong);
        return $traTs !== false && $now > $traTs;
    }
}
