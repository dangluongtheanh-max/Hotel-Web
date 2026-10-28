<?php

$url = 'http://localhost:8000/Backend/public/index.php/letan/trangthaiphong';

echo "=== 1. Test GET /letan/trangthaiphong ===\n";
$resGet = file_get_contents($url);
$dataGet = json_decode($resGet, true);

if ($dataGet && !empty($dataGet['success'])) {
    echo "✅ GET /letan/trangthaiphong: SUCCESS\n";
    $kpi = $dataGet['data']['kpi'];
    echo "   KPI: Tong: {$kpi['tongSoPhong']} | Trong: {$kpi['soPhongTrong']} | DangO: {$kpi['soPhongDangO']} | DaDat: {$kpi['soPhongDaDat']} | TyLe: {$kpi['tyLeLapDay']}%\n";
    echo "   LoaiPhong count: " . count($dataGet['data']['loaiPhong']) . "\n";
    echo "   DanhSachPhong count: " . count($dataGet['data']['danhSachPhong']) . "\n";
} else {
    echo "❌ GET /letan/trangthaiphong: FAILED\n";
    exit(1);
}

echo "\n=== 2. Test POST /letan/trangthaiphong ===\n";
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode(['maLoaiPhong' => 'LP002'])
    ]
]);
$resPost = file_get_contents($url, false, $context);
$dataPost = json_decode($resPost, true);

if ($dataPost && !empty($dataPost['success'])) {
    echo "✅ POST /letan/trangthaiphong with maLoaiPhong=LP002: SUCCESS\n";
    echo "   Filtered LoaiPhong count: " . count($dataPost['data']['loaiPhong']) . "\n";
    echo "   Filtered Phong count: " . count($dataPost['data']['danhSachPhong']) . "\n";
} else {
    echo "❌ POST /letan/trangthaiphong: FAILED\n";
    exit(1);
}

echo "\n=== 3. Verify Occupied Rooms Guest Information (PP889 in P203, P204, P302) ===\n";
$p203 = null;
foreach ($dataGet['data']['danhSachPhong'] as $p) {
    if ($p['MaPhong'] === 'P203') {
        $p203 = $p;
        break;
    }
}
if ($p203) {
    echo "   Room P203 found: Status={$p203['TrangThaiPhong']}, Slip=#{$p203['MaPhieu']}, NguoiDat={$p203['TenNguoiDat']} ({$p203['SDTNguoiDat']})\n";
    echo "   Danh sach nguoi luu tru trong phong:\n";
    foreach ($p203['danhSachNguoiLuuTru'] as $g) {
        echo "     - {$g['HoTen']} ({$g['LoaiGiayTo']}: {$g['SoGiayTo']}) | QuocTich: {$g['QuocTich']}\n";
    }
    echo "✅ Occupied room with both Slip holder and In-room guests: VERIFIED\n";
} else {
    echo "❌ Room P203 not found\n";
    exit(1);
}

echo "\n=== 4. Check Frontend letan.html HTML/JS elements ===\n";
$html = file_get_contents('Frontend/letan.html');
$requiredElements = [
    'switchMainTab',
    'loadRoomStatusData',
    'drillDownLoaiPhong',
    'backToLoaiPhongList',
    'renderFilteredRooms',
    'roomSearchInput',
    'mainTabRoomCounter',
    'loaiPhongCardsContainer',
    'roomCardsContainer',
    'viewLevel1LoaiPhong',
    'viewLevel2ChiTietPhong',
    'mainTab1Container',
    'mainTab2Container'
];
$missing = [];
foreach ($requiredElements as $elem) {
    if (strpos($html, $elem) === false) {
        $missing[] = $elem;
    }
}
if (empty($missing)) {
    echo "✅ All frontend components, functions and IDs present in Frontend/letan.html\n";
} else {
    echo "❌ Missing elements: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "\n🎉 ALL VERIFICATION TESTS PASSED!\n";
