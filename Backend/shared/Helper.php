<?php

namespace Shared;

/**
 * Cac ham tien ich dung chung toan he thong.
 */
class Helper
{
    public static function generateId(string $prefix): string
    {
        return $prefix . '-' . strtoupper(substr(uniqid(), -8));
    }

    public static function now(): string
    {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }
}
