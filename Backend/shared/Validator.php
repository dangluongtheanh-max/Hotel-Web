<?php

namespace Shared;

/**
 * Validate du lieu dau vao truoc khi dua xuong Service.
 */
class Validator
{
    public static function required(array $data, array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}
