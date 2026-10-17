<?php

namespace Shared;

/**
 * Lop Request don gian xu ly body JSON, POST, GET.
 */
class Request
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function capture(): self
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = [];
        }

        $data = array_merge($_GET, $_POST, $json);
        return new self($data);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
