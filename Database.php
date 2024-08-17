<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    // For a skip condition
    private const SKIP_VALUE = '__SKIP__';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $index = 0;
        
        // Handle conditional blocks first
        $query = $this->processConditionalBlocks($query, $args);

        // Replace placeholders with actual values
        $query = preg_replace_callback('/\?(d|f|a|#)?/', function ($matches) use (&$index, $args) {
            $specifier = $matches[1] ?? '';
            $arg = $args[$index++] ?? null;

            return $this->replacePlaceholder($arg, $specifier);
        }, $query);

        return $query;
    }

    public function skip()
    {
        return self::SKIP_VALUE;
    }

    private function processConditionalBlocks(string $query, array $args): string
    {
        return preg_replace_callback('/\{([^{}]*)\}/', function ($matches) use ($args) {
            foreach ($args as $arg) {
                if ($arg === self::SKIP_VALUE) {
                    return '';
                }
            }
            return $matches[1];
        }, $query);
    }

    private function replacePlaceholder($arg, string $specifier): string
    {
        switch ($specifier) {
            case 'd':
                return $this->formatInteger($arg);
            case 'f':
                return $this->formatFloat($arg);
            case 'a':
                return $this->formatArray($arg);
            case '#':
                return $this->formatIdentifiers($arg);
            default:
                return $this->formatValue($arg);
        }
    }

    private function formatInteger($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        return (string)(int)$value;
    }

    private function formatFloat($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        return (string)(float)$value;
    }

    private function formatArray($array): string
    {
        if (!is_array($array)) {
            throw new Exception('Expected an array.');
        }

        $formatted = [];

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $formatted[] = $this->escapeIdentifier($key) . ' = ' . $this->formatValue($value);
            } else {
                $formatted[] = $this->formatValue($value);
            }
        }

        return implode(', ', $formatted);
    }

    private function formatIdentifiers($value): string
    {
        if (is_array($value)) {
            $escaped = array_map([$this, 'escapeIdentifier'], $value);
            return implode(', ', $escaped);
        }
        return $this->escapeIdentifier($value);
    }

    private function formatValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_int($value) || is_float($value)) {
            return (string)$value;
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return $this->escapeString($value);
        } else {
            throw new Exception('Unsupported value type.');
        }
    }

    private function escapeString(string $value): string
    {
        return "'" . $this->mysqli->real_escape_string($value) . "'";
    }

    private function escapeIdentifier(string $value): string
    {
        return "`" . str_replace('`', '``', $value) . "`";
    }
}
