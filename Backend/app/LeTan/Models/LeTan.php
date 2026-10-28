<?php

declare(strict_types=1);

namespace App\LeTan\Models;

/**
 * Model LeTan
 * Sinh ra tu so do lop (class diagram) - module LeTan.
 */
class LeTan
{
    /** @var string (PK) */
    private string $MaLeTan;
    /** @var string */
    private string $HoTen;
    /** @var string|null */
    private ?string $SDT;
    /** @var string (FK) */
    private string $MaTK;
    public function __construct(
        string $MaLeTan,
        string $HoTen,
        string $MaTK,
        ?string $SDT = null
    ) {
        $this->MaLeTan = $MaLeTan;
        $this->HoTen = $HoTen;
        $this->MaTK = $MaTK;
        $this->SDT = $SDT;
    }

    public function getMaLeTan(): string
    {
        return $this->MaLeTan;
    }

    public function getHoTen(): string
    {
        return $this->HoTen;
    }

    public function getSDT(): ?string
    {
        return $this->SDT;
    }

    public function getMaTK(): string
    {
        return $this->MaTK;
    }

    public function setMaLeTan(string $MaLeTan): void
    {
        $this->MaLeTan = $MaLeTan;
    }

    public function setHoTen(string $HoTen): void
    {
        $this->HoTen = $HoTen;
    }

    public function setSDT(?string $SDT): void
    {
        $this->SDT = $SDT;
    }

    public function setMaTK(string $MaTK): void
    {
        $this->MaTK = $MaTK;
    }

    public function toArray(): array
    {
        return [
            'MaLeTan' => $this->MaLeTan,
            'HoTen' => $this->HoTen,
            'SDT' => $this->SDT,
            'MaTK' => $this->MaTK,
        ];
    }
}
