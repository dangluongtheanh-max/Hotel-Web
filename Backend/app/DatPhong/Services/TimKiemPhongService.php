<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\Phong\Services\PhongService;
use Exception;

/**
 * Service chuyên trách tìm kiếm, lọc phòng trống và sinh gợi ý Combo thông minh.
 */
class TimKiemPhongService
{
    private PhongService $phongService;

    public function __construct(PhongService $phongService)
    {
        $this->phongService = $phongService;
    }

    /**
     * Quy đổi số người theo công thức:
     * Số người = Người lớn + floor(Trẻ em / 2) (2 trẻ em = 1 người lớn, 1 trẻ em không tính)
     */
    public function quyDoiSoKhach(int $soNguoiLon, int $soTreEm): int
    {
        $tong = $soNguoiLon + intdiv(max(0, $soTreEm), 2);
        return max(1, $tong);
    }

    /**
     * Tính số đêm giữa 2 ngày nhận - trả phòng
     */
    public function tinhSoDem(string $ngayNhan, string $ngayTra): int
    {
        $timeNhan = strtotime($ngayNhan);
        $timeTra = strtotime($ngayTra);
        if ($timeTra <= $timeNhan) {
            throw new Exception("Ngày trả phòng phải lớn hơn ngày nhận phòng.");
        }
        $soDem = (int) round(($timeTra - $timeNhan) / 86400);
        return max(1, $soDem);
    }

    /**
     * Lọc phòng theo ngày, số khách quy đổi, số phòng và loại phòng (nếu có)
     * Trả về danh sách loại phòng phù hợp và dòng gợi ý Combo thông minh
     */
    public function timKiemVaGoiY(array $params = []): array
    {
        $ngayNhan = trim($params['ngayNhan'] ?? '');
        $ngayTra = trim($params['ngayTra'] ?? '');
        $soNguoiLon = (int) ($params['soNguoiLon'] ?? 1);
        $soTreEm = (int) ($params['soTreEm'] ?? 0);
        $soPhong = (int) ($params['soPhong'] ?? 1);
        $maLoaiPhong = trim($params['maLoaiPhong'] ?? '');

        if (!$ngayNhan || !$ngayTra) {
            throw new Exception("Vui lòng chọn ngày nhận phòng và ngày trả phòng.");
        }

        if ($soPhong < 1) {
            throw new Exception("Số phòng muốn đặt phải từ 1 trở lên.");
        }

        $soDem = $this->tinhSoDem($ngayNhan, $ngayTra);
        $tongKhach = $this->quyDoiSoKhach($soNguoiLon, $soTreEm);

        // Tự động quét và giải phóng các phòng quá hạn thanh toán 20 phút (Lazy Cleanup)
        try {
            (new \App\ThanhToan\Services\ThanhToanService())->quetVaXuLyTatCaPhieuHetHan();
        } catch (\Throwable $e) {
            // Không ngắt luồng tìm kiếm nếu quét lỗi
        }

        // Lấy danh sách loại phòng và số phòng trống thực tế
        $tatCaLoaiPhong = $this->phongService->layDanhSachLoaiPhong();
        $phongTrongMap = $this->phongService->kiemTraPhongTrong([
            'checkIn' => $ngayNhan,
            'checkOut' => $ngayTra
        ]);

        $danhSachKetQua = [];
        foreach ($tatCaLoaiPhong as $lp) {
            $maLP = $lp->getMaLoaiPhong();
            $soTrong = $phongTrongMap[$maLP] ?? 0;
            $danhSachKetQua[] = [
                'MaLoaiPhong' => $maLP,
                'TenLoaiPhong' => $lp->getTenLoaiPhong(),
                'Gia' => $lp->getGia(),
                'SoNguoiToiDa' => $lp->getSoNguoiToiDa(),
                'MoTa' => $lp->getMoTa(),
                'TienNghi' => $lp->getTienNghi(),
                'SoPhongTrong' => $soTrong,
            ];
        }

        // Trường hợp 1: Khách CHỈ ĐỊNH 1 loại phòng cụ thể
        if ($maLoaiPhong !== '' && $maLoaiPhong !== 'ALL') {
            $loaiPhongChon = null;
            foreach ($danhSachKetQua as $item) {
                if ($item['MaLoaiPhong'] === $maLoaiPhong) {
                    $loaiPhongChon = $item;
                    break;
                }
            }

            if (!$loaiPhongChon) {
                return [
                    'hopLe' => false,
                    'thongBao' => 'Không tìm thấy loại phòng đã chọn.',
                    'tongKhach' => $tongKhach,
                    'soDem' => $soDem,
                    'danhSach' => [],
                    'goiYCombo' => null,
                ];
            }

            // Kiểm tra điều kiện 1: Đủ số phòng trống
            $duPhong = $loaiPhongChon['SoPhongTrong'] >= $soPhong;
            // Kiểm tra điều kiện 2: Đủ sức chứa (số phòng * sức chứa >= tổng khách)
            $duSucChua = ($soPhong * $loaiPhongChon['SoNguoiToiDa']) >= $tongKhach;

            if (!$duPhong || !$duSucChua) {
                $lyDo = !$duPhong 
                    ? "Loại phòng {$loaiPhongChon['TenLoaiPhong']} chỉ còn {$loaiPhongChon['SoPhongTrong']} phòng trống (yêu cầu {$soPhong} phòng)."
                    : "Sức chứa tối đa của {$soPhong} phòng {$loaiPhongChon['TenLoaiPhong']} là " . ($soPhong * $loaiPhongChon['SoNguoiToiDa']) . " người, không đủ cho đoàn {$tongKhach} khách.";
                return [
                    'hopLe' => false,
                    'thongBao' => $lyDo,
                    'tongKhach' => $tongKhach,
                    'soDem' => $soDem,
                    'danhSach' => [],
                    'goiYCombo' => null,
                ];
            }

            return [
                'hopLe' => true,
                'thongBao' => 'Đã tìm thấy phòng phù hợp.',
                'tongKhach' => $tongKhach,
                'soDem' => $soDem,
                'danhSach' => [$loaiPhongChon],
                'goiYCombo' => "Lựa chọn: {$soPhong} phòng {$loaiPhongChon['TenLoaiPhong']} (Sức chứa tối đa: " . ($soPhong * $loaiPhongChon['SoNguoiToiDa']) . " người cho {$tongKhach} khách)",
                'comboToiUu' => [
                    'chiTiet' => [
                        [
                            'maLoai' => $loaiPhongChon['MaLoaiPhong'],
                            'tenLoai' => $loaiPhongChon['TenLoaiPhong'],
                            'soLuong' => $soPhong,
                            'sucChua' => $loaiPhongChon['SoNguoiToiDa'],
                            'gia' => $loaiPhongChon['Gia'],
                        ]
                    ],
                    'tongSucChua' => $soPhong * $loaiPhongChon['SoNguoiToiDa'],
                    'tongTien' => $soPhong * $loaiPhongChon['Gia'] * $soDem,
                ]
            ];
        }

        // Trường hợp 2: Khách chọn "Tất cả loại phòng" (Cách A kèm dòng gợi ý Cách B)
        $phongConTrong = array_filter($danhSachKetQua, function ($item) {
            return $item['SoPhongTrong'] > 0;
        });
        $phongConTrong = array_values($phongConTrong);

        if (empty($phongConTrong)) {
            return [
                'hopLe' => false,
                'thongBao' => 'Hiện tại khách sạn đã hết phòng trống trong khoảng thời gian này.',
                'tongKhach' => $tongKhach,
                'soDem' => $soDem,
                'danhSach' => [],
                'goiYCombo' => null,
            ];
        }

        // Tìm tất cả các tổ hợp phòng hợp lệ (tổng phòng = $soPhong, tổng sức chứa >= $tongKhach)
        $validCombos = [];
        $this->timToHopPhuHop($phongConTrong, 0, $soPhong, $tongKhach, [], 0, 0, 0, $validCombos);

        if (empty($validCombos)) {
            return [
                'hopLe' => false,
                'thongBao' => "Không tìm thấy phương án phòng nào đủ sức chứa cho {$tongKhach} khách với {$soPhong} phòng đã chọn trong khoảng thời gian này. Quý khách vui lòng chọn thêm số lượng phòng hoặc chọn ngày khác.",
                'tongKhach' => $tongKhach,
                'soDem' => $soDem,
                'danhSach' => [],
                'goiYCombo' => null,
            ];
        }

        // Sắp xếp các combo ưu tiên: giá rẻ nhất, sau đó sức chứa gần nhất với số khách
        usort($validCombos, function ($a, $b) use ($tongKhach) {
            if ($a['tongTien'] !== $b['tongTien']) {
                return $a['tongTien'] <=> $b['tongTien'];
            }
            $diffA = abs($a['tongSucChua'] - $tongKhach);
            $diffB = abs($b['tongSucChua'] - $tongKhach);
            return $diffA <=> $diffB;
        });

        $best = $validCombos[0];
        $parts = [];
        foreach ($best['chiTiet'] as $item) {
            $parts[] = "{$item['soLuong']} phòng {$item['tenLoai']} (sức chứa {$item['sucChua']} người/phòng)";
        }
        $goiYCombo = "💡 Gợi ý combo tối ưu: " . implode(' + ', $parts) . " → Tổng sức chứa: {$best['tongSucChua']} người cho {$tongKhach} khách.";

        // Lọc danh sách phòng: Chỉ hiển thị các loại phòng có thể tham gia vào tổ hợp đáp ứng nhu cầu
        $maLoaiPhuHop = [];
        foreach ($validCombos as $combo) {
            foreach ($combo['chiTiet'] as $item) {
                $maLoaiPhuHop[$item['maLoai']] = true;
            }
        }

        $danhSachPhuHop = array_values(array_filter($phongConTrong, function ($item) use ($maLoaiPhuHop) {
            return isset($maLoaiPhuHop[$item['MaLoaiPhong']]);
        }));

        return [
            'hopLe' => true,
            'thongBao' => 'Tìm thấy danh sách loại phòng phù hợp.',
            'tongKhach' => $tongKhach,
            'soDem' => $soDem,
            'danhSach' => $danhSachPhuHop,
            'goiYCombo' => $goiYCombo,
            'comboToiUu' => $best,
        ];
    }

    private function timToHopPhuHop(
        array $types,
        int $typeIndex,
        int $roomsLeft,
        int $guestsTarget,
        array $currentPick,
        int $currentCapacity,
        float $currentCost,
        int $roomsPicked,
        array &$results
    ): void {
        if ($roomsLeft === 0) {
            if ($currentCapacity >= $guestsTarget) {
                $results[] = [
                    'chiTiet' => $currentPick,
                    'tongSucChua' => $currentCapacity,
                    'tongTien' => $currentCost,
                ];
            }
            return;
        }

        if ($typeIndex >= count($types)) {
            return;
        }

        $type = $types[$typeIndex];
        $maxCanPick = min($roomsLeft, $type['SoPhongTrong']);

        for ($count = $maxCanPick; $count >= 0; $count--) {
            $nextPick = $currentPick;
            if ($count > 0) {
                $nextPick[] = [
                    'maLoai' => $type['MaLoaiPhong'],
                    'tenLoai' => $type['TenLoaiPhong'],
                    'soLuong' => $count,
                    'sucChua' => $type['SoNguoiToiDa'],
                    'gia' => $type['Gia'],
                ];
            }

            $this->timToHopPhuHop(
                $types,
                $typeIndex + 1,
                $roomsLeft - $count,
                $guestsTarget,
                $nextPick,
                $currentCapacity + ($count * $type['SoNguoiToiDa']),
                $currentCost + ($count * $type['Gia']),
                $roomsPicked + $count,
                $results
            );
        }
    }
}
