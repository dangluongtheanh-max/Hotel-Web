<?php

namespace Shared;

/**
 * Lop Request don gian xu ly body JSON, POST, GET.
 */
class Request
{
    private array $data;
    public ?array $user = null;

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

    public function header(string $name, $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$normalized])) {
            return $_SERVER[$normalized];
        }
        if (strcasecmp($name, 'Authorization') === 0) {
            return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $default;
        }
        return $default;
    }
}
