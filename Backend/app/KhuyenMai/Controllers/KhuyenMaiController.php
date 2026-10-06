<?php
namespace App\KhuyenMai\Controllers;
use App\KhuyenMai\Services\KhuyenMaiService;
class KhuyenMaiController {
    private KhuyenMaiService $service;
    public function __construct(KhuyenMaiService $service) { $this->service = $service; }
}
