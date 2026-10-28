<?php

declare(strict_types=1);

namespace Shared;

/**
 * Các hàm tiện ích dùng chung toàn hệ thống.
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
