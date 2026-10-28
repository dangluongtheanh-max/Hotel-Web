<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\Phong\Services\PhongService;
use DateTimeImmutable;
use Exception;

/**
 * Validator chuyên trách kiểm tra thông tin người lưu trú, tính tuổi và phân bổ phòng.
 */
class NguoiLuuTruValidator
{
    /**
     * Tính tuổi của khách tính đến ngày nhận phòng
     */
    public function tinhTuoi(string $ngaySinh, string $ngayNhan): int
    {
        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $ngaySinh) ?: new DateTimeImmutable($ngaySinh);
        $checkInDt = DateTimeImmutable::createFromFormat('Y-m-d', $ngayNhan) ?: new DateTimeImmutable($ngayNhan);
        return $dob->diff($checkInDt)->y;
    }

    /**
     * Kiểm tra tính hợp lệ của người lưu trú theo từng phòng,
     * đối chiếu cơ cấu tuổi (Người lớn > 11t, Trẻ em <= 11t)
     * và phân bổ mã phòng vật lý ngẫu nhiên.
     *
     * @return array Danh sách phòng được gán kèm danh sách khách hợp lệ
     * @throws Exception
     */
    public function kiemTraVaPhanBoPhong(
        array $phongDaChon,
        array $nguoiLuuTruTheoPhong,
        string $ngayNhan,
        string $ngayTra,
        int $soNguoiLon,
        int $soTreEm,
        PhongService $phongService
    ): array {
        $tongSoKhachKhaiBao = 0;
        $tongNguoiLonKhaiBao = 0;
        $tongTreEmKhaiBao = 0;
        $danhSachPhongGan = [];
        $roomIndex = 0;

        foreach ($phongDaChon as $item) {
            $maLP = $item['maLoaiPhong'] ?? '';
            $sl = (int) ($item['soLuong'] ?? 0);
            if ($sl <= 0) continue;

            // Lấy ngẫu nhiên các phòng trống của loại phòng này
            $maPhongVatLyList = $phongService->layPhongTrongNgauNhien($maLP, $sl, $ngayNhan, $ngayTra);

            for ($i = 0; $i < $sl; $i++) {
                $maPhong = $maPhongVatLyList[$i];
                $lpInfo = $phongService->layThongTinLoaiPhong($maLP);

                // Lấy thông tin người lưu trú cho phòng này
                $khachTrongPhong = $nguoiLuuTruTheoPhong[$roomIndex]['khach'] ?? [];

                if (empty($khachTrongPhong)) {
                    throw new Exception("Phòng thứ " . ($roomIndex + 1) . " ({$lpInfo->getTenLoaiPhong()}) chưa được nhập thông tin người lưu trú.");
                }

                // Kiểm tra từng người lưu trú trong phòng
                $nguoiLonTrongPhong = 0;
                $treEmTrongPhong = 0;

                foreach ($khachTrongPhong as $kIdx => $g) {
                    $hoTen = trim($g['hoTen'] ?? '');
                    $ngaySinh = trim($g['ngaySinh'] ?? '');
                    $quocTich = trim($g['quocTich'] ?? '');
                    $loaiGiayTo = trim($g['loaiGiayTo'] ?? '');
                    $soGiayTo = trim($g['soGiayTo'] ?? '');

                    $viTri = "Phòng " . ($roomIndex + 1) . " - Khách " . ($kIdx + 1);

                    if ($hoTen === '') {
                        throw new Exception("{$viTri}: Vui lòng nhập họ và tên.");
                    }
                    if ($ngaySinh === '') {
                        throw new Exception("{$viTri} ({$hoTen}): Vui lòng chọn ngày sinh.");
                    }
                    if ($quocTich === '') {
                        throw new Exception("{$viTri} ({$hoTen}): Vui lòng chọn quốc tịch (Việt Nam hoặc Nước ngoài).");
                    }
                    if ($loaiGiayTo === '') {
                        throw new Exception("{$viTri} ({$hoTen}): Vui lòng chọn loại giấy tờ.");
                    }
                    if ($soGiayTo === '') {
                        throw new Exception("{$viTri} ({$hoTen}): Vui lòng nhập số giấy tờ tùy thân.");
                    }

                    // Kiểm tra quy chuẩn giấy tờ tùy thân (thừa/thiếu/sai định dạng)
                    $this->kiemTraGiayToTuyThan($loaiGiayTo, $soGiayTo, "{$viTri} ({$hoTen})");

                    // Kiểm tra ngày sinh hợp lệ (không vượt quá ngày nhận phòng hoặc tương lai)
                    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $ngaySinh) ?: new DateTimeImmutable($ngaySinh);
                    $checkInDt = DateTimeImmutable::createFromFormat('Y-m-d', $ngayNhan) ?: new DateTimeImmutable($ngayNhan);
                    if ($dob > $checkInDt) {
                        throw new Exception("{$viTri} ({$hoTen}): Ngày sinh ({$ngaySinh}) không thể nằm sau ngày nhận phòng.");
                    }

                    // Hệ thống tự động tính tuổi dựa vào năm sinh/ngày sinh đến ngày nhận phòng
                    $tuoi = $this->tinhTuoi($ngaySinh, $ngayNhan);
                    if ($tuoi <= 11) {
                        $tongTreEmKhaiBao++;
                        $treEmTrongPhong++;
                    } else {
                        $tongNguoiLonKhaiBao++;
                        $nguoiLonTrongPhong++;
                    }

                    $tongSoKhachKhaiBao++;
                }

                // Sức chứa của phòng áp dụng theo quy tắc quy đổi: Người lớn + floor(Trẻ em / 2)
                $soQuyDoiTrongPhong = $nguoiLonTrongPhong + intdiv($treEmTrongPhong, 2);
                if ($soQuyDoiTrongPhong > $lpInfo->getSoNguoiToiDa()) {
                    throw new Exception("Phòng thứ " . ($roomIndex + 1) . " ({$lpInfo->getTenLoaiPhong()}) chỉ chứa tối đa {$lpInfo->getSoNguoiToiDa()} người quy đổi. Bạn đã nhập {$nguoiLonTrongPhong} Người lớn và {$treEmTrongPhong} Trẻ em (tương đương {$soQuyDoiTrongPhong} khách quy đổi, vượt quá sức chứa).");
                }

                $danhSachPhongGan[] = [
                    'maLoaiPhong' => $maLP,
                    'tenLoaiPhong' => $lpInfo->getTenLoaiPhong(),
                    'maPhong' => $maPhong,
                    'soNguoi' => count($khachTrongPhong),
                    'donGia' => $lpInfo->getGia(),
                    'khach' => $khachTrongPhong,
                ];

                $roomIndex++;
            }
        }

        // Kiểm tra tổng số lượng người lưu trú phải khớp với số khách yêu cầu
        $tongYeuCau = $soNguoiLon + $soTreEm;
        if ($tongSoKhachKhaiBao !== $tongYeuCau) {
            throw new Exception("Tổng số người lưu trú đã khai báo ({$tongSoKhachKhaiBao} người) không khớp với số người đăng ký ban đầu ({$tongYeuCau} người).");
        }

        // Kiểm tra cơ cấu Người lớn và Trẻ em được tính tự động từ ngày sinh (<=11 tuổi là Trẻ em, >11 tuổi là Người lớn)
        if ($tongNguoiLonKhaiBao !== $soNguoiLon || $tongTreEmKhaiBao !== $soTreEm) {
            throw new Exception("Cơ cấu người lưu trú tính theo ngày sinh ({$tongNguoiLonKhaiBao} Người lớn, {$tongTreEmKhaiBao} Trẻ em) không khớp với số lượng khách đã chọn ban đầu ({$soNguoiLon} Người lớn, {$soTreEm} Trẻ em). Hệ thống quy định: Khách từ 11 tuổi trở xuống (≤ 11 tuổi) là Trẻ em, trên 11 tuổi (> 11 tuổi) là Người lớn tính đến ngày nhận phòng ({$ngayNhan}). Vui lòng kiểm tra lại ngày sinh của từng khách!");
        }

        return $danhSachPhongGan;
    }

    /**
     * Kiểm tra chi tiết định dạng và độ dài các loại giấy tờ tùy thân
     * Báo lỗi rõ ràng khi người dùng nhập thừa, thiếu hoặc sai ký tự
     *
     * @param string $loaiGiayTo CCCD, CMND, HoChieu, GiayKhaiSinh
     * @param string $soGiayTo
     * @param string $contextName Tiền tố định danh (ví dụ: Phòng 1 - Khách 1)
     * @throws Exception
     */
    public function kiemTraGiayToTuyThan(string $loaiGiayTo, string $soGiayTo, string $contextName = ''): void
    {
        $soGiayTo = trim($soGiayTo);
        $prefix = $contextName ? "{$contextName}: " : '';
        $len = mb_strlen($soGiayTo, 'UTF-8');

        // Chuẩn hóa loại giấy tờ (loại bỏ dấu hoặc ký tự đặc biệt để so khớp)
        $normalizedType = mb_strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $loaiGiayTo));

        if (str_contains($normalizedType, 'CCCD')) {
            if (!preg_match('/^[0-9]+$/', $soGiayTo)) {
                throw new Exception("{$prefix}Số CCCD chỉ được chứa các chữ số (0-9), không chứa chữ cái hoặc ký tự đặc biệt.");
            }
            if ($len < 12) {
                $thieu = 12 - $len;
                throw new Exception("{$prefix}Số CCCD chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định CCCD phải gồm đúng 12 chữ số.");
            }
            if ($len > 12) {
                $thua = $len - 12;
                throw new Exception("{$prefix}Số CCCD có {$len} chữ số (thừa {$thua} số). Quy định CCCD chỉ gồm đúng 12 chữ số.");
            }
        } elseif (str_contains($normalizedType, 'CMND')) {
            if (!preg_match('/^[0-9]+$/', $soGiayTo)) {
                throw new Exception("{$prefix}Số CMND chỉ được chứa các chữ số (0-9).");
            }
            if ($len < 9) {
                $thieu = 9 - $len;
                throw new Exception("{$prefix}Số CMND chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định CMND phải gồm đúng 9 chữ số.");
            }
            if ($len > 9) {
                $thua = $len - 9;
                throw new Exception("{$prefix}Số CMND có {$len} chữ số (thừa {$thua} số). Quy định CMND chỉ gồm đúng 9 chữ số.");
            }
        } elseif (str_contains($normalizedType, 'HOCHIEU') || str_contains($normalizedType, 'PASSPORT')) {
            if (preg_match('/\s/', $soGiayTo) || preg_match('/[^A-Za-z0-9]/', $soGiayTo)) {
                throw new Exception("{$prefix}Số Hộ chiếu không được chứa khoảng trắng hoặc ký tự đặc biệt.");
            }
            if ($len < 7) {
                $thieu = 7 - $len;
                throw new Exception("{$prefix}Số Hộ chiếu chỉ có {$len} ký tự (quá ngắn, thiếu tối thiểu {$thieu} ký tự). Quy định Hộ chiếu từ 7 đến 9 ký tự (ví dụ: B1234567, C12345678).");
            }
            if ($len > 9) {
                $thua = $len - 9;
                throw new Exception("{$prefix}Số Hộ chiếu có {$len} ký tự (quá dài, thừa {$thua} ký tự). Quy định Hộ chiếu tối đa 9 ký tự.");
            }
        } elseif (str_contains($normalizedType, 'GIAYKHAISINH')) {
            if ($len < 6) {
                $thieu = 6 - $len;
                throw new Exception("{$prefix}Số Giấy khai sinh chỉ có {$len} ký tự (quá ngắn, thiếu {$thieu} ký tự). Độ dài hợp lệ từ 6 đến 15 ký tự.");
            }
            if ($len > 15) {
                $thua = $len - 15;
                throw new Exception("{$prefix}Số Giấy khai sinh có {$len} ký tự (quá dài, thừa {$thua} ký tự). Độ dài hợp lệ tối đa 15 ký tự.");
            }
        } else {
            // Giấy tờ tùy thân khác
            if ($len < 5) {
                throw new Exception("{$prefix}Số giấy tờ tùy thân quá ngắn (tối thiểu 5 ký tự).");
            }
            if ($len > 25) {
                throw new Exception("{$prefix}Số giấy tờ tùy thân quá dài (tối đa 25 ký tự).");
            }
        }
    }

    /**
     * Kiểm tra số điện thoại (chỉ 10 chữ số, đúng đầu số)
     */
    public function kiemTraSoDienThoai(string $sdt, string $contextName = ''): void
    {
        $sdt = trim($sdt);
        $prefix = $contextName ? "{$contextName}: " : '';
        $len = strlen($sdt);

        if (!preg_match('/^[0-9]+$/', $sdt)) {
            throw new Exception("{$prefix}Số điện thoại chỉ được chứa các chữ số (0-9).");
        }
        if ($len < 10) {
            $thieu = 10 - $len;
            throw new Exception("{$prefix}Số điện thoại chỉ có {$len} chữ số (thiếu {$thieu} số). Quy định số điện thoại phải có đúng 10 số.");
        }
        if ($len > 10) {
            $thua = $len - 10;
            throw new Exception("{$prefix}Số điện thoại có {$len} chữ số (thừa {$thua} số). Quy định số điện thoại chỉ gồm đúng 10 số.");
        }
        if (!preg_match('/^(03|05|07|08|09)/', $sdt)) {
            throw new Exception("{$prefix}Số điện thoại không hợp lệ. Phải bắt đầu bằng các đầu số di động Việt Nam (03, 05, 07, 08, 09).");
        }
    }
}
