<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminPhongRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service quản lý phòng vật lý và loại phòng.
 */
class AdminPhongService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminPhongRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getPhongRepo();
        } else {
            $this->repository = new AdminPhongRepository();
        }
    }

    public function xemDSPhongTheoLoai(array $params = []): array
    {
        $list = $this->repository->getPhongTheoLoai();

        return [
            'status' => 'success',
            'total_types' => count($list),
            'data' => $list,
        ];
    }
    /* =========================================================================
     * SERVICE: QUẢN LÝ LOẠI PHÒNG
     * ========================================================================= */

    public function sinhMaLoaiPhong(): array
    {
        return [
            'status' => 'success',
            'maLoaiPhong' => $this->repository->generateMaLoaiPhong(),
        ];
    }

    public function themLoaiPhong(array $data): array
    {
        $tenLoai = trim((string)($data['TenLoaiPhong'] ?? $data['tenLoaiPhong'] ?? ''));
        if ($tenLoai === '') {
            return ['status' => 'error', 'message' => 'Tên loại phòng không được để trống.'];
        }

        $gia = (float)($data['Gia'] ?? $data['gia'] ?? 0);
        if ($gia < 0) {
            return ['status' => 'error', 'message' => 'Giá phòng không được âm.'];
        }

        $soNguoi = (int)($data['SoNguoiToiDa'] ?? $data['soNguoiToiDa'] ?? 2);
        if ($soNguoi < 1 || $soNguoi > 20) {
            return ['status' => 'error', 'message' => 'Số người tối đa phải từ 1 đến 20.'];
        }

        $tinhTrang = (string)($data['TinhTrang'] ?? $data['tinhTrang'] ?? 'ConPhong');
        if (!in_array($tinhTrang, ['ConPhong', 'HetPhong', 'NgungKinhDoanh'], true)) {
            return ['status' => 'error', 'message' => 'Tình trạng loại phòng không hợp lệ.'];
        }

        $normalized = [
            'MaLoaiPhong' => $data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? null,
            'TenLoaiPhong' => $tenLoai,
            'Gia' => $gia,
            'SoNguoiToiDa' => $soNguoi,
            'MoTa' => $data['MoTa'] ?? $data['moTa'] ?? null,
            'TienNghi' => $data['TienNghi'] ?? $data['tienNghi'] ?? null,
            'TinhTrang' => $tinhTrang,
        ];

        $this->mergeExtraLoaiPhongFields($normalized, $data, true);

        try {
            $created = $this->repository->createLoaiPhong($normalized);
            return ['status' => 'success', 'message' => 'Thêm loại phòng thành công.', 'data' => $created];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function suaLoaiPhong(string $maLoaiPhong, array $data): array
    {
        if (empty($maLoaiPhong)) {
            $maLoaiPhong = (string)($data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? '');
        }
        if (empty($maLoaiPhong)) {
            return ['status' => 'error', 'message' => 'Mã loại phòng không hợp lệ.'];
        }

        if (!$this->repository->findLoaiPhongById($maLoaiPhong)) {
            return ['status' => 'error', 'message' => "Không tìm thấy loại phòng '{$maLoaiPhong}'."];
        }

        $normalized = [];
        if (isset($data['TenLoaiPhong']) || isset($data['tenLoaiPhong'])) {
            $tenLoai = trim((string)($data['TenLoaiPhong'] ?? $data['tenLoaiPhong']));
            if ($tenLoai === '') {
                return ['status' => 'error', 'message' => 'Tên loại phòng không được để trống.'];
            }
            $normalized['TenLoaiPhong'] = $tenLoai;
        }
        if (isset($data['Gia']) || isset($data['gia'])) {
            $gia = (float)($data['Gia'] ?? $data['gia']);
            if ($gia < 0) {
                return ['status' => 'error', 'message' => 'Giá phòng không được âm.'];
            }
            $normalized['Gia'] = $gia;
        }
        if (isset($data['SoNguoiToiDa']) || isset($data['soNguoiToiDa'])) {
            $soNguoi = (int)($data['SoNguoiToiDa'] ?? $data['soNguoiToiDa']);
            if ($soNguoi < 1 || $soNguoi > 20) {
                return ['status' => 'error', 'message' => 'Số người tối đa phải từ 1 đến 20.'];
            }
            $normalized['SoNguoiToiDa'] = $soNguoi;
        }
        if (array_key_exists('MoTa', $data) || array_key_exists('moTa', $data)) {
            $normalized['MoTa'] = $data['MoTa'] ?? $data['moTa'];
        }
        if (array_key_exists('TienNghi', $data) || array_key_exists('tienNghi', $data)) {
            $normalized['TienNghi'] = $data['TienNghi'] ?? $data['tienNghi'];
        }
        if (isset($data['TinhTrang']) || isset($data['tinhTrang'])) {
            $tinhTrang = (string)($data['TinhTrang'] ?? $data['tinhTrang']);
            if (!in_array($tinhTrang, ['ConPhong', 'HetPhong', 'NgungKinhDoanh'], true)) {
                return ['status' => 'error', 'message' => 'Tình trạng loại phòng không hợp lệ.'];
            }
            $normalized['TinhTrang'] = $tinhTrang;
        }

        $this->mergeExtraLoaiPhongFields($normalized, $data, false);

        try {
            $this->repository->updateLoaiPhong($maLoaiPhong, $normalized);
            return [
                'status' => 'success',
                'message' => 'Cập nhật loại phòng thành công.',
                'data' => $this->repository->findLoaiPhongById($maLoaiPhong)
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function mergeExtraLoaiPhongFields(array &$normalized, array $data, bool $isCreate): void
    {
        $textCols = [
            'tom_tat',
            'dien_tich',
            'tang',
            'bo_tri_giuong',
            'huong_view',
            'tien_nghi_phong_tam',
            'quy_dinh_hut_thuoc',
            'gio_nhan_phong',
            'gio_tra_phong',
            'chinh_sach_huy',
            'chinh_sach_tre_em',
            'phuong_thuc_coc_thanh_toan',
        ];
        foreach ($textCols as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $normalized[$col] = ($val === null || trim((string)$val) === '') ? null : trim((string)$val);
            } elseif ($isCreate) {
                $normalized[$col] = null;
            }
        }

        if (array_key_exists('diem_nhan', $data)) {
            $raw = $data['diem_nhan'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            if (is_array($raw)) {
                $clean = [];
                foreach ($raw as $card) {
                    if (!is_array($card)) continue;
                    $title = trim((string)($card['title'] ?? $card['tieu_de'] ?? ''));
                    $desc = trim((string)($card['desc'] ?? $card['mo_ta'] ?? ''));
                    $icon = trim((string)($card['icon'] ?? '✨'));
                    if ($title !== '' || $desc !== '') {
                        $clean[] = ['title' => $title, 'desc' => $desc, 'icon' => $icon !== '' ? $icon : '✨'];
                    }
                }
                $normalized['diem_nhan'] = empty($clean) ? null : json_encode(array_slice($clean, 0, 4), JSON_UNESCAPED_UNICODE);
            } else {
                $normalized['diem_nhan'] = null;
            }
        }

        if (array_key_exists('dac_quyen', $data)) {
            $raw = $data['dac_quyen'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            if (is_array($raw)) {
                $clean = [];
                foreach ($raw as $line) {
                    $txt = trim((string)$line);
                    if ($txt !== '') {
                        $clean[] = $txt;
                    }
                }
                $normalized['dac_quyen'] = empty($clean) ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
            } else {
                $normalized['dac_quyen'] = null;
            }
        }

        if (array_key_exists('tien_nghi_ids', $data) && is_array($data['tien_nghi_ids'])) {
            $normalized['tien_nghi_ids'] = $data['tien_nghi_ids'];
        }
    }

    public function xoaLoaiPhong(string $maLoaiPhong): array
    {
        $maLoaiPhong = trim($maLoaiPhong);
        if ($maLoaiPhong === '') {
            return ['status' => 'error', 'message' => 'Mã loại phòng không hợp lệ.'];
        }

        try {
            $images = $this->repository->deleteLoaiPhong($maLoaiPhong);
            foreach ($images as $img) {
                $this->unlinkUploadedRoomImage((string)($img['duong_dan'] ?? ''));
            }
            return ['status' => 'success', 'message' => "Đã xóa loại phòng '{$maLoaiPhong}' thành công."];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * SERVICE: THƯ VIỆN TIỆN NGHI
     * ========================================================================= */

    public function xemDSTienNghi(): array
    {
        try {
            $data = $this->repository->getAllTienNghi();
            return ['status' => 'success', 'data' => $data];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function themTienNghi(array $data): array
    {
        $ten = trim((string)($data['ten'] ?? ''));
        $nhom = trim((string)($data['nhom'] ?? ''));
        $icon = isset($data['icon']) ? trim((string)$data['icon']) : null;

        if ($ten === '' || mb_strlen($ten) > 150) {
            return ['status' => 'error', 'message' => 'Tên tiện nghi là bắt buộc và tối đa 150 ký tự.'];
        }
        if (!in_array($nhom, AdminPhongRepository::NHOM_TIEN_NGHI, true)) {
            return ['status' => 'error', 'message' => 'Nhóm tiện nghi không hợp lệ.'];
        }
        if ($icon !== null && mb_strlen($icon) > 32) {
            return ['status' => 'error', 'message' => 'Icon tối đa 32 ký tự.'];
        }

        try {
            $res = $this->repository->createOrGetTienNghi($ten, $nhom, $icon);
            return [
                'status' => 'success',
                'existed' => $res['existed'],
                'message' => $res['existed']
                    ? 'Tiện nghi đã tồn tại trong thư viện, đã chọn sẵn.'
                    : 'Đã thêm tiện nghi mới vào thư viện.',
                'data' => $res['item'],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * SERVICE: UPLOAD & QUẢN LÝ ẢNH LOẠI PHÒNG
     * ========================================================================= */

    public function uploadAnhLoaiPhong(string $maLoaiPhong, array $files): array
    {
        $maLoaiPhong = trim($maLoaiPhong);
        if ($maLoaiPhong === '' || !$this->repository->findLoaiPhongById($maLoaiPhong)) {
            return ['status' => 'error', 'message' => 'Loại phòng không tồn tại.'];
        }

        $normalizedFiles = $this->normalizeFilesArray($files);
        if (empty($normalizedFiles)) {
            return ['status' => 'error', 'message' => 'Vui lòng chọn ít nhất một file ảnh để tải lên.'];
        }

        $maxPerRoom = 8;
        $maxBytes = 3 * 1024 * 1024; // 3MB
        $currentCount = $this->repository->countAnhByLoaiPhong($maLoaiPhong);
        if ($currentCount + count($normalizedFiles) > $maxPerRoom) {
            $remain = max(0, $maxPerRoom - $currentCount);
            return [
                'status' => 'error',
                'message' => "Mỗi loại phòng tối đa {$maxPerRoom} ảnh (hiện có {$currentCount}, chỉ có thể thêm tối đa {$remain} ảnh)."
            ];
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $uploadDir = $this->getUploadRoomTypesDir();
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
            return ['status' => 'error', 'message' => 'Không thể tạo thư mục lưu trữ ảnh uploads/room-types/.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $validated = [];

        foreach ($normalizedFiles as $f) {
            $origName = (string)($f['name'] ?? 'image');
            $errCode = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK) {
                return ['status' => 'error', 'message' => "Tải file '{$origName}' thất bại (mã lỗi {$errCode})."];
            }
            $tmpPath = (string)($f['tmp_name'] ?? '');
            $size = (int)($f['size'] ?? 0);
            if ($size <= 0 || $size > $maxBytes) {
                return ['status' => 'error', 'message' => "Ảnh '{$origName}' vượt quá dung lượng tối đa 3MB hoặc rỗng."];
            }
            $realMime = $tmpPath !== '' ? ($finfo->file($tmpPath) ?: '') : '';
            if (!isset($allowedMime[$realMime])) {
                return ['status' => 'error', 'message' => "File '{$origName}' không đúng định dạng ảnh hợp lệ (chỉ nhận JPG, PNG, WEBP)."];
            }
            $validated[] = [
                'tmp_name' => $tmpPath,
                'ext' => $allowedMime[$realMime],
            ];
        }

        $saved = [];
        $nextOrder = $this->repository->getMaxThuTuAnh($maLoaiPhong);
        $hasCover = $this->repository->hasAnhDaiDien($maLoaiPhong);

        foreach ($validated as $idx => $vf) {
            $filename = bin2hex(random_bytes(16)) . '.' . $vf['ext'];
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            $moved = is_uploaded_file($vf['tmp_name'])
                ? @move_uploaded_file($vf['tmp_name'], $destPath)
                : @rename($vf['tmp_name'], $destPath);

            if (!$moved) {
                return ['status' => 'error', 'message' => 'Không thể lưu file ảnh lên máy chủ.'];
            }

            $isCover = !$hasCover && $idx === 0;
            if ($isCover) {
                $hasCover = true;
            }
            $nextOrder++;
            $relPath = 'uploads/room-types/' . $filename;
            $saved[] = $this->repository->addAnhLoaiPhong($maLoaiPhong, $relPath, $isCover, $nextOrder);
        }

        return [
            'status' => 'success',
            'message' => 'Đã tải lên ' . count($saved) . ' ảnh thành công.',
            'data' => $this->repository->getAnhByLoaiPhong($maLoaiPhong),
            'uploaded' => $saved,
        ];
    }

    public function xoaAnhLoaiPhong(int $id, string $maLoaiPhong): array
    {
        if ($id <= 0 || trim($maLoaiPhong) === '') {
            return ['status' => 'error', 'message' => 'Thiếu thông tin ảnh hoặc loại phòng.'];
        }
        try {
            $deleted = $this->repository->deleteAnhLoaiPhong($id, trim($maLoaiPhong));
            if (!$deleted) {
                return ['status' => 'error', 'message' => 'Không tìm thấy ảnh cần xóa.'];
            }
            $this->unlinkUploadedRoomImage((string)($deleted['duong_dan'] ?? ''));
            return [
                'status' => 'success',
                'message' => 'Đã xóa ảnh thành công.',
                'data' => $this->repository->getAnhByLoaiPhong(trim($maLoaiPhong)),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function datAnhDaiDien(int $id, string $maLoaiPhong): array
    {
        if ($id <= 0 || trim($maLoaiPhong) === '') {
            return ['status' => 'error', 'message' => 'Thiếu thông tin ảnh hoặc loại phòng.'];
        }
        try {
            $ok = $this->repository->setAnhDaiDien($id, trim($maLoaiPhong));
            if (!$ok) {
                return ['status' => 'error', 'message' => 'Không tìm thấy ảnh để đặt làm ảnh đại diện.'];
            }
            return [
                'status' => 'success',
                'message' => 'Đã cập nhật ảnh đại diện.',
                'data' => $this->repository->getAnhByLoaiPhong(trim($maLoaiPhong)),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function sapXepAnhLoaiPhong(string $maLoaiPhong, array $order): array
    {
        $maLoaiPhong = trim($maLoaiPhong);
        if ($maLoaiPhong === '' || empty($order)) {
            return ['status' => 'error', 'message' => 'Thiếu mã loại phòng hoặc danh sách thứ tự ảnh.'];
        }
        try {
            $this->repository->reorderAnhLoaiPhong($maLoaiPhong, $order);
            return [
                'status' => 'success',
                'message' => 'Đã lưu thứ tự ảnh.',
                'data' => $this->repository->getAnhByLoaiPhong($maLoaiPhong),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function normalizeFilesArray(array $files): array
    {
        $bucket = $files['images'] ?? $files['file'] ?? reset($files);
        if (!is_array($bucket) || !isset($bucket['name'])) {
            return [];
        }
        if (!is_array($bucket['name'])) {
            return $bucket['error'] === UPLOAD_ERR_NO_FILE ? [] : [$bucket];
        }
        $list = [];
        $count = count($bucket['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($bucket['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => $bucket['name'][$i] ?? '',
                'type' => $bucket['type'][$i] ?? '',
                'tmp_name' => $bucket['tmp_name'][$i] ?? '',
                'error' => $bucket['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $bucket['size'][$i] ?? 0,
            ];
        }
        return $list;
    }

    private function getUploadRoomTypesDir(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'room-types';
    }

    private function unlinkUploadedRoomImage(string $duongDan): void
    {
        $clean = str_replace('\\', '/', ltrim($duongDan, '/'));
        if (strpos($clean, 'uploads/room-types/') !== 0) {
            return;
        }
        $basename = basename($clean);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return;
        }
        $fullPath = $this->getUploadRoomTypesDir() . DIRECTORY_SEPARATOR . $basename;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    public function doiTrangThaiLoaiPhong(string $maLoaiPhong): array
    {
        if (empty($maLoaiPhong)) {
            return ['status' => 'error', 'message' => 'Mã loại phòng không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiLoaiPhong($maLoaiPhong);
            $msg = ($item['TinhTrang'] === 'NgungKinhDoanh')
                ? 'Đã chuyển loại phòng sang trạng thái ngừng kinh doanh.'
                : 'Đã mở loại phòng hoạt động kinh doanh trở lại.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * SERVICE: QUẢN LÝ PHÒNG CỤ THỂ
     * ========================================================================= */

    public function themPhong(array $data): array
    {
        $maPhong = trim($data['MaPhong'] ?? $data['maPhong'] ?? '');
        $maLoai = trim($data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? '');

        if (empty($maPhong) || empty($maLoai)) {
            return ['status' => 'error', 'message' => 'Vui lòng cung cấp mã phòng và loại phòng.'];
        }

        $normalized = [
            'MaPhong' => $maPhong,
            'MaLoaiPhong' => $maLoai,
            'TrangThaiPhong' => $data['TrangThaiPhong'] ?? $data['trangThaiPhong'] ?? 'Trong',
        ];

        try {
            $created = $this->repository->createPhong($normalized);
            return ['status' => 'success', 'message' => 'Thêm phòng mới thành công.', 'data' => $created];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function suaPhong(string $maPhong, array $data): array
    {
        if (empty($maPhong)) {
            $maPhong = $data['MaPhong'] ?? $data['maPhong'] ?? '';
        }
        if (empty($maPhong)) {
            return ['status' => 'error', 'message' => 'Mã phòng không hợp lệ.'];
        }

        $normalized = [];
        if (isset($data['MaLoaiPhong']) || isset($data['maLoaiPhong'])) {
            $normalized['MaLoaiPhong'] = trim($data['MaLoaiPhong'] ?? $data['maLoaiPhong']);
        }
        if (isset($data['TrangThaiPhong']) || isset($data['trangThaiPhong'])) {
            $normalized['TrangThaiPhong'] = trim($data['TrangThaiPhong'] ?? $data['trangThaiPhong']);
        }

        try {
            $updated = $this->repository->updatePhong($maPhong, $normalized);
            if (!$updated) {
                return ['status' => 'error', 'message' => 'Cập nhật không thành công hoặc không có dữ liệu thay đổi.'];
            }
            return [
                'status' => 'success',
                'message' => 'Cập nhật phòng thành công.',
                'data' => $this->repository->findPhongById($maPhong)
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function doiTrangThaiPhong(string $maPhong): array
    {
        if (empty($maPhong)) {
            return ['status' => 'error', 'message' => 'Mã phòng không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiPhong($maPhong);
            $msg = ($item['TrangThaiPhong'] === 'BaoTri')
                ? 'Đã chuyển phòng sang trạng thái bảo trì / ngừng hoạt động.'
                : 'Đã mở phòng sẵn sàng đón khách trở lại.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

}
