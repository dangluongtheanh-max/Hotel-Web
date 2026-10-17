<?php

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanRepository;
use App\HoaDon\Services\HoaDonService;
use App\ThanhToan\Services\ThanhToanService;
use App\KhuyenMai\Services\KhuyenMaiService;
use Exception;

/**
 * Service (business logic) cho module LeTan (BE2).
 * Đóng vai trò orchestration điều phối quy trình Lễ Tân:
 * Tra cứu phòng, tra cứu phiếu, thực hiện check-in/check-out giao dịch an toàn (ACID transaction),
 * gọi liên module qua HoaDonService, ThanhToanService, KhuyenMaiService.
 */
class LeTanService
{
    private LeTanRepository $repository;
    private HoaDonService $hoaDonService;
    private ThanhToanService $thanhToanService;
    private KhuyenMaiService $khuyenMaiService;

    public function __construct(
        ?LeTanRepository $repository = null,
        ?HoaDonService $hoaDonService = null,
        ?ThanhToanService $thanhToanService = null,
        ?KhuyenMaiService $khuyenMaiService = null
    ) {
        $this->repository = $repository ?? new LeTanRepository();
        $this->hoaDonService = $hoaDonService ?? new HoaDonService();
        $this->thanhToanService = $thanhToanService ?? new ThanhToanService();
        $this->khuyenMaiService = $khuyenMaiService ?? new KhuyenMaiService();
    }

    /* =========================================================================
     * 1. XEM & TÌM KIẾM PHÒNG (BE2)
     * ========================================================================= */

    /**
     * Use case 1: Xem danh sách phòng kèm loại phòng, giá và trạng thái.
     * Route: POST /letan/xemdsphong
     */
    public function xemDSPhong(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
        $offset = ($limit > 0) ? ($page - 1) * $limit : 0;

        $filters = [
            'TrangThaiPhong' => $params['TrangThaiPhong'] ?? ($params['trang_thai'] ?? null),
            'MaLoaiPhong' => $params['MaLoaiPhong'] ?? ($params['ma_loai_phong'] ?? null),
            'limit' => $limit,
            'offset' => $offset,
        ];

        $rooms = $this->repository->getDanhSachPhong($filters);

        return [
            'success' => true,
            'data' => $rooms,
            'total' => count($rooms),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Use case 2: Xem chi tiết phòng, loại phòng và booking đang liên quan.
     * Route: POST /letan/xemctphong
     */
    public function xemCTPhong(array $params = []): array
    {
        $maPhong = trim($params['MaPhong'] ?? ($params['ma_phong'] ?? ''));
        if (empty($maPhong)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã phòng (MaPhong).'
            ];
        }

        $result = $this->repository->getChiTietPhong($maPhong);
        if (!$result) {
            return [
                'success' => false,
                'error' => "Không tìm thấy phòng có mã '{$maPhong}'."
            ];
        }

        return [
            'success' => true,
            'data' => $result
        ];
    }

    /**
     * Use case 3: Tìm kiếm phòng theo từ khóa, loại phòng, khoảng giá, trạng thái.
     * Route: POST /letan/timkiemphong
     */
    public function timKiemPhong(array $params = []): array
    {
        $filters = [
            'keyword' => $params['keyword'] ?? ($params['tu_khoa'] ?? null),
            'TrangThaiPhong' => $params['TrangThaiPhong'] ?? ($params['trang_thai'] ?? null),
            'MaLoaiPhong' => $params['MaLoaiPhong'] ?? ($params['ma_loai_phong'] ?? null),
            'GiaMin' => $params['GiaMin'] ?? ($params['gia_min'] ?? null),
            'GiaMax' => $params['GiaMax'] ?? ($params['gia_max'] ?? null),
            'SoNguoi' => $params['SoNguoi'] ?? ($params['so_nguoi'] ?? null),
        ];

        $rooms = $this->repository->timKiemPhong($filters);

        return [
            'success' => true,
            'data' => $rooms,
            'total' => count($rooms)
        ];
    }

    /* =========================================================================
     * 2. XEM & TÌM KIẾM PHIẾU NHẬN PHÒNG (BE2)
     * ========================================================================= */

    /**
     * Use case 4: Xem danh sách phiếu nhận phòng theo trạng thái, kênh đặt, ngày.
     * Route: POST /letan/xemdsphieu
     */
    public function xemDSPhieu(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
        $offset = ($limit > 0) ? ($page - 1) * $limit : 0;

        $filters = [
            'TrangThai' => $params['TrangThai'] ?? ($params['trang_thai'] ?? null),
            'KenhDat' => $params['KenhDat'] ?? ($params['kenh_dat'] ?? null),
            'MaKH' => $params['MaKH'] ?? ($params['ma_kh'] ?? null),
            'NgayLap' => $params['NgayLap'] ?? ($params['ngay_lap'] ?? null),
            'tu_ngay' => $params['tu_ngay'] ?? null,
            'den_ngay' => $params['den_ngay'] ?? null,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $phieus = $this->repository->getDanhSachPhieu($filters);

        return [
            'success' => true,
            'data' => $phieus,
            'total' => count($phieus),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Use case 5: Xem chi tiết phiếu nhận phòng (khách, phòng, thời gian, dịch vụ, khuyến mãi).
     * Route: POST /letan/xemctphieu
     */
    public function xemCTPhieu(array $params = []): array
    {
        $maPhieu = trim($params['MaPhieu'] ?? ($params['ma_phieu'] ?? ''));
        if (empty($maPhieu)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).'
            ];
        }

        $phieu = $this->repository->getChiTietPhieu($maPhieu);
        if (!$phieu) {
            return [
                'success' => false,
                'error' => "Không tìm thấy phiếu nhận phòng có mã '{$maPhieu}'."
            ];
        }

        return [
            'success' => true,
            'data' => $phieu
        ];
    }

    /**
     * Use case 6: Tìm kiếm phiếu theo mã phiếu, SĐT, tên khách, trạng thái.
     * Route: POST /letan/timkiemphieu
     */
    public function timKiemPhieu(array $params = []): array
    {
        $filters = [
            'keyword' => $params['keyword'] ?? ($params['tu_khoa'] ?? null),
            'MaPhieu' => $params['MaPhieu'] ?? ($params['ma_phieu'] ?? null),
            'SDT' => $params['SDT'] ?? ($params['sdt'] ?? null),
            'TenKhachHang' => $params['TenKhachHang'] ?? ($params['HoTen'] ?? ($params['ten_khach'] ?? null)),
            'TrangThai' => $params['TrangThai'] ?? ($params['trang_thai'] ?? null),
            'KenhDat' => $params['KenhDat'] ?? ($params['kenh_dat'] ?? null),
        ];

        $phieus = $this->repository->timKiemPhieu($filters);

        return [
            'success' => true,
            'data' => $phieus,
            'total' => count($phieus)
        ];
    }

    /* =========================================================================
     * 3. CHECK-IN (BE2)
     * ========================================================================= */

    /**
     * Use case 7: Check-in phiếu đặt phòng an toàn trong Database Transaction.
     * Khóa dòng (FOR UPDATE), kiểm tra trạng thái phiếu và phòng, xác nhận giấy tờ,
     * cập nhật đồng bộ: PhieuNhanPhong -> DangLuuTru, ChiTiet -> DangLuuTru, Phong -> DangSuDung.
     * Route: POST /letan/checkin
     */
    public function checkin(array $params = []): array
    {
        $maPhieu = trim($params['MaPhieu'] ?? ($params['ma_phieu'] ?? ''));
        $maLeTan = !empty($params['MaLeTan']) ? trim($params['MaLeTan']) : 'LT001';
        $xacNhanGiayTo = !empty($params['xacNhanGiayTo']);

        if (empty($maPhieu)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).'
            ];
        }

        $db = $this->repository->getConnection();

        try {
            $db->beginTransaction();

            // 1. Khóa và đọc phiếu cùng các dòng chi tiết phòng (FOR UPDATE)
            $phieu = $this->repository->lockAndGetPhieuForUpdate($maPhieu);

            if (!$phieu) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Không tìm thấy phiếu nhận phòng có mã '{$maPhieu}'."
                ];
            }

            // 2. Kiểm tra trạng thái phiếu
            if ($phieu['TrangThai'] === 'DangLuuTru') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Phiếu '{$maPhieu}' đã được check-in và hiện đang lưu trú.",
                    'code' => 'ALREADY_CHECKED_IN'
                ];
            }

            if ($phieu['TrangThai'] === 'DaTraPhong') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Phiếu '{$maPhieu}' đã hoàn tất trả phòng, không thể check-in lại."
                ];
            }

            if (in_array($phieu['TrangThai'], ['DaHuy', 'TuChoi'], true)) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Phiếu '{$maPhieu}' đang ở trạng thái '{$phieu['TrangThai']}', không thể check-in."
                ];
            }

            // Chỉ cho phép check-in nếu trạng thái là DaDat hoặc ChoXacNhan
            if (!in_array($phieu['TrangThai'], ['DaDat', 'ChoXacNhan'], true)) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Trạng thái phiếu '{$phieu['TrangThai']}' không hợp lệ để check-in (yêu cầu 'DaDat' hoặc 'ChoXacNhan')."
                ];
            }

            // 3. Kiểm tra danh sách phòng
            $rooms = $phieu['DanhSachPhong'] ?? [];
            if (empty($rooms)) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Phiếu '{$maPhieu}' không có phòng nào được chỉ định."
                ];
            }

            $maPhongs = [];
            foreach ($rooms as $r) {
                $mp = $r['MaPhong'];
                $maPhongs[] = $mp;

                // Kiểm tra trạng thái phòng trong chi tiết phiếu
                if (!in_array($r['TrangThai'], ['DaDat', 'ChoXacNhan'], true)) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'error' => "Phòng '{$mp}' trong phiếu đang ở trạng thái '{$r['TrangThai']}', không thể check-in."
                    ];
                }

                // Kiểm tra trạng thái phòng vật lý trong khách sạn (phải là DaDat hoặc Trong)
                if (!in_array($r['TrangThaiPhong'], ['DaDat', 'Trong'], true)) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'error' => "Phòng vật lý '{$mp}' đang ở trạng thái '{$r['TrangThaiPhong']}' (bận hoặc bảo trì), không thể nhận phòng."
                    ];
                }

                // Kiểm tra sức chứa người tối đa
                if ((int)$r['SoNguoi'] > (int)$r['SoNguoiToiDa']) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'error' => "Số lượng khách ({$r['SoNguoi']} người) vượt quá sức chứa tối đa ({$r['SoNguoiToiDa']} người) của phòng '{$mp}'."
                    ];
                }
            }

            // 4. Cập nhật đồng bộ trong Transaction
            $this->repository->updateCheckInStatus($maPhieu, $maLeTan, $maPhongs);

            $db->commit();

            $updatedRooms = [];
            foreach ($maPhongs as $mp) {
                $updatedRooms[] = [
                    'MaPhong' => $mp,
                    'TrangThaiPhong' => 'DangSuDung'
                ];
            }

            return [
                'success' => true,
                'message' => "Check-in thành công cho phiếu '{$maPhieu}'!",
                'data' => [
                    'MaPhieu' => $maPhieu,
                    'TrangThai' => 'DangLuuTru',
                    'MaLeTan' => $maLeTan,
                    'XacNhanGiayTo' => $xacNhanGiayTo,
                    'Phong' => $updatedRooms
                ]
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Lỗi hệ thống trong quá trình check-in: ' . $e->getMessage()
            ];
        }
    }

    /* =========================================================================
     * 4. CHECK-OUT & QUẢN LÝ HÓA ĐƠN (BE2)
     * ========================================================================= */

    /**
     * Use case 8: Check-out trả phòng, tính tiền phòng + dịch vụ, áp khuyến mãi,
     * tự động tạo Hóa Đơn & CTHD (gọi HoaDonService), tạo Giao Dịch Thanh Toán (gọi ThanhToanService)
     * và trả phòng về 'Trong' trong Database Transaction.
     * Hỗ trợ mode preview: nếu params['preview'] = true thì chỉ tính toán Folio xem trước.
     * Route: POST /letan/checkout
     */
    public function checkout(array $params = []): array
    {
        $maPhieu = trim($params['MaPhieu'] ?? ($params['ma_phieu'] ?? ''));
        $hinhThuc = $params['HinhThuc'] ?? ($params['hinhThuc'] ?? 'TienMat');
        $isPreview = !empty($params['preview']);

        if (empty($maPhieu)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).'
            ];
        }

        // 1. Chế độ Xem trước chi phí (Preview Folio)
        if ($isPreview) {
            return $this->hoaDonService->tinhToanChiPhi($maPhieu);
        }

        // 2. Thực hiện Check-out chính thức
        $phieu = $this->repository->getChiTietPhieu($maPhieu);
        if (!$phieu) {
            return [
                'success' => false,
                'error' => "Không tìm thấy phiếu nhận phòng có mã '{$maPhieu}'."
            ];
        }

        // Idempotency: Nếu phiếu đã trả phòng trước đó
        if ($phieu['TrangThai'] === 'DaTraPhong') {
            $hd = $this->repository->xemHoaDon($maPhieu);
            if ($hd) {
                return [
                    'success' => true,
                    'message' => "Phiếu '{$maPhieu}' đã được trả phòng và xuất hóa đơn '{$hd['MaHD']}' trước đó.",
                    'is_existing' => true,
                    'data' => [
                        'MaPhieu' => $maPhieu,
                        'TrangThai' => 'DaTraPhong',
                        'MaHD' => $hd['MaHD'],
                        'TongTienSauVAT' => (float)$hd['TongTienSauVAT'],
                        'ThanhToan' => [
                            'MaThanhToan' => $hd['MaThanhToan'] ?? null,
                            'HinhThuc' => $hd['HinhThuc'] ?? null,
                            'TrangThaiThanhToan' => $hd['TrangThaiThanhToan'] ?? null,
                        ],
                        'ChiTiet' => $hd['ChiTiet'] ?? []
                    ]
                ];
            }
        }

        // Chỉ cho phép check-out phiếu đang ở trạng thái DangLuuTru
        if ($phieu['TrangThai'] !== 'DangLuuTru') {
            return [
                'success' => false,
                'error' => "Chỉ có thể check-out cho phiếu đang lưu trú ('DangLuuTru'). Trạng thái hiện tại: '{$phieu['TrangThai']}'."
            ];
        }

        // BƯỚC 1: Lập hóa đơn chính thức qua HoaDonService
        // (HoaDonService tự động đọc lại tiền phòng theo số đêm, dịch vụ, khuyến mãi, tính thuế 10% VAT)
        $hdResult = $this->hoaDonService->taoHoaDon(['MaPhieu' => $maPhieu]);
        if (!($hdResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => 'Không thể lập hóa đơn thanh toán: ' . ($hdResult['error'] ?? 'Lỗi không xác định')
            ];
        }

        $maHD = $hdResult['data']['MaHD'] ?? '';
        if (empty($maHD)) {
            return [
                'success' => false,
                'error' => 'Lỗi: Không tìm thấy mã hóa đơn sau khi tạo.'
            ];
        }

        // BƯỚC 2: Tạo giao dịch thanh toán qua ThanhToanService (Tiền mặt hoặc Chuyển khoản VietQR)
        $ttResult = $this->thanhToanService->taoThanhToan([
            'MaHD' => $maHD,
            'HinhThuc' => $hinhThuc
        ]);

        if (!($ttResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => 'Lỗi khi khởi tạo thanh toán: ' . ($ttResult['error'] ?? 'Lỗi không xác định')
            ];
        }

        // BƯỚC 3: Cập nhật trạng thái phiếu và phòng sang 'DaTraPhong' & 'Trong' bằng Transaction
        $db = $this->repository->getConnection();
        try {
            $db->beginTransaction();

            $maPhongs = array_column($phieu['DanhSachPhong'], 'MaPhong');
            $this->repository->updateCheckOutStatus($maPhieu, $maPhongs);

            $db->commit();

            $updatedRooms = [];
            foreach ($maPhongs as $mp) {
                $updatedRooms[] = [
                    'MaPhong' => $mp,
                    'TrangThaiPhong' => 'Trong'
                ];
            }

            return [
                'success' => true,
                'message' => "Check-out và lập hóa đơn '{$maHD}' thành công cho phiếu '{$maPhieu}'!",
                'data' => [
                    'MaPhieu' => $maPhieu,
                    'TrangThai' => 'DaTraPhong',
                    'MaHD' => $maHD,
                    'TongTienSauVAT' => (float)($hdResult['data']['TongTienSauVAT'] ?? 0),
                    'BangKeChiTiet' => $hdResult['data']['BangKeChiTiet'] ?? ($hdResult['data']['ChiTiet'] ?? []),
                    'ThanhToan' => $ttResult['data'] ?? [],
                    'Phong' => $updatedRooms
                ]
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Lỗi khi cập nhật trạng thái trả phòng: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Use case 9: Tìm kiếm hóa đơn theo mã hóa đơn, mã phiếu hoặc tên khách hàng.
     * Route: POST /letan/timhoadon
     */
    public function timHoaDon(array $params = []): array
    {
        $filters = [
            'keyword' => $params['keyword'] ?? ($params['tu_khoa'] ?? null),
            'MaHD' => $params['MaHD'] ?? ($params['ma_hd'] ?? null),
            'MaPhieu' => $params['MaPhieu'] ?? ($params['ma_phieu'] ?? null),
            'TenKhachHang' => $params['TenKhachHang'] ?? ($params['ten_khach'] ?? null),
        ];

        $invoices = $this->repository->timKiemHoaDon($filters);

        return [
            'success' => true,
            'data' => $invoices,
            'total' => count($invoices)
        ];
    }

    /**
     * Use case 10: Xem hóa đơn theo MaHD hoặc MaPhieu (kèm khách, trạng thái phiếu và thanh toán).
     * Route: POST /letan/xemhoadon
     */
    public function xemHoaDon(array $params = []): array
    {
        $query = trim($params['MaHD'] ?? ($params['MaPhieu'] ?? ($params['ma_hd'] ?? ($params['ma_phieu'] ?? ''))));
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã hóa đơn (MaHD) hoặc mã phiếu (MaPhieu).'
            ];
        }

        $hd = $this->repository->xemHoaDon($query);
        if (!$hd) {
            return [
                'success' => false,
                'error' => "Không tìm thấy hóa đơn tương ứng với '{$query}'."
            ];
        }

        return [
            'success' => true,
            'data' => $hd
        ];
    }

    /**
     * Use case 11: Xem chi tiết các dòng tiền phòng/dịch vụ trong hóa đơn (CTHD).
     * Route: POST /letan/xemcthd
     */
    public function xemCTHD(array $params = []): array
    {
        $maHD = trim($params['MaHD'] ?? ($params['ma_hd'] ?? ''));

        // Nếu client truyền MaPhieu thay vì MaHD, tự động tra cứu MaHD
        if (empty($maHD) && !empty($params['MaPhieu'])) {
            $hd = $this->repository->xemHoaDon(trim($params['MaPhieu']));
            if ($hd) {
                $maHD = $hd['MaHD'];
            }
        }

        if (empty($maHD)) {
            return [
                'success' => false,
                'error' => 'Vui lòng cung cấp mã hóa đơn (MaHD).'
            ];
        }

        // Gọi HoaDonService để lấy dữ liệu chuẩn hóa
        $details = $this->hoaDonService->xemChiTietHoaDon($maHD);
        if ($details['success'] ?? false) {
            return $details;
        }

        // Dự phòng lấy trực tiếp từ repository
        $rows = $this->repository->getChiTietHoaDon($maHD);
        return [
            'success' => true,
            'data' => [
                'MaHD' => $maHD,
                'BangKeChiTiet' => $rows
            ]
        ];
    }
}
