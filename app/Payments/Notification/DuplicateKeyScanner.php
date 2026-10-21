<?php

namespace App\Payments\Notification;

use JsonException;
use RuntimeException;

final class DuplicateKeyScanner
{
    public function decode(string $json): array
    {
        $index = 0;
        $length = strlen($json);
        $value = $this->parseValue($json, $index, $length);
        $this->skipWhitespace($json, $index, $length);

        if ($index !== $length || ! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Notification payload must be a JSON object.');
        }

        return $value;
    }

    private function parseValue(string $json, int &$index, int $length): mixed
    {
        $this->skipWhitespace($json, $index, $length);
        $character = $json[$index] ?? '';

        if ($character === '{') {
            return $this->parseObject($json, $index, $length);
        }
        if ($character === '[') {
            return $this->parseArray($json, $index, $length);
        }
        if ($character === '"') {
            return $this->parseString($json, $index, $length);
        }

        $start = $index;
        while ($index < $length && ! str_contains(" \t\r\n,]}\n", $json[$index])) {
            $index++;
        }

        $token = substr($json, $start, $index - $start);
        try {
            return json_decode($token, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Malformed JSON payload.', 0, $exception);
        }
    }

    private function parseObject(string $json, int &$index, int $length): array
    {
        $index++;
        $object = [];
        $this->skipWhitespace($json, $index, $length);
        if (($json[$index] ?? '') === '}') {
            $index++;
            return $object;
        }

        while (true) {
            $this->skipWhitespace($json, $index, $length);
            $key = $this->parseString($json, $index, $length);
            if (array_key_exists($key, $object)) {
                throw new RuntimeException('Duplicate JSON object key.');
            }
            $this->skipWhitespace($json, $index, $length);
            if (($json[$index] ?? '') !== ':') {
                throw new RuntimeException('Malformed JSON object.');
            }
            $index++;
            $object[$key] = $this->parseValue($json, $index, $length);
            $this->skipWhitespace($json, $index, $length);
            $delimiter = $json[$index] ?? '';
            if ($delimiter === '}') {
                $index++;
                return $object;
            }
            if ($delimiter !== ',') {
                throw new RuntimeException('Malformed JSON object.');
            }
            $index++;
        }
    }

    private function parseArray(string $json, int &$index, int $length): array
    {
        $index++;
        $array = [];
        $this->skipWhitespace($json, $index, $length);
        if (($json[$index] ?? '') === ']') {
            $index++;
            return $array;
        }
        while (true) {
            $array[] = $this->parseValue($json, $index, $length);
            $this->skipWhitespace($json, $index, $length);
            $delimiter = $json[$index] ?? '';
            if ($delimiter === ']') {
                $index++;
                return $array;
            }
            if ($delimiter !== ',') {
                throw new RuntimeException('Malformed JSON array.');
            }
            $index++;
        }
    }

    private function parseString(string $json, int &$index, int $length): string
    {
        $start = $index;
        if (($json[$index] ?? '') !== '"') {
            throw new RuntimeException('Malformed JSON string.');
        }
        $index++;
        $escaped = false;
        while ($index < $length) {
            $character = $json[$index++];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                try {
                    return json_decode(substr($json, $start, $index - $start), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new RuntimeException('Malformed JSON string.', 0, $exception);
                }
            }
        }
        throw new RuntimeException('Unterminated JSON string.');
    }

    private function skipWhitespace(string $json, int &$index, int $length): void
    {
        while ($index < $length && str_contains(" \t\r\n", $json[$index])) {
            $index++;
        }
    }
}
