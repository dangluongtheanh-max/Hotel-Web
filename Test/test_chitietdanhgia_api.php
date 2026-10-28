<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend')->load();

$tests = [
    'Room Type LP001' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=room_type&ma_doi_tuong=LP001&tu_ngay=2026-10-01&den_ngay=2026-10-31',
    'Room Type LP002' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=room_type&ma_doi_tuong=LP002&tu_ngay=2026-10-01&den_ngay=2026-10-31',
    'Room Type LP003' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=room_type&ma_doi_tuong=LP003&tu_ngay=2026-10-01&den_ngay=2026-10-31',
    'Room Type LP004 (Empty)' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=room_type&ma_doi_tuong=LP004&tu_ngay=2026-10-01&den_ngay=2026-10-31',
    'Room P203' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=room&ma_doi_tuong=P203&tu_ngay=2026-10-01&den_ngay=2026-10-31',
    'All (Toàn bộ)' => 'http://localhost:8000/Backend/public/index.php/admin/chitietdanhgia?doi_tuong=all&ma_doi_tuong=all&tu_ngay=2026-10-01&den_ngay=2026-10-31'
];

foreach ($tests as $title => $url) {
    echo "--- $title ---\n";
    $json = json_decode(file_get_contents($url), true);
    $data = $json['data'] ?? [];
    $list = $data['danh_sach'] ?? [];
    echo "Count: " . count($list) . ", Avg Score: " . ($data['tong_quan']['diem_trung_binh'] ?? 'null') . "\n";
    foreach ($list as $r) {
        echo "  [{$r['SoSao']}★] {$r['MaDanhGia']} - {$r['HoTenKhachHang']} (Phòng {$r['MaPhong']}): " . substr($r['NoiDung'] ?? '(Trống)', 0, 40) . "...\n";
    }
}
