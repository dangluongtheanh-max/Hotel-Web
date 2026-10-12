<?php

namespace Shared;

/**
 * Lop Request don gian xu ly body JSON, POST, GET.
 */
class Request
{
    private array $data;
    private array $headers;
    private ?array $user = null;

    public function __construct(array $data = [], array $headers = [])
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    public static function capture(): self
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = [];
        }

        $data = array_merge($_GET, $_POST, $json);
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            $headers = [];
        }
        if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }

        return new self($data, $headers);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0 && is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }
}
