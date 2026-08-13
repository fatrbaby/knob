<?php

namespace Knob;

use InvalidArgumentException;

final class SqlOperator
{
    private const ALLOWED = [
        '=',
        '!=',
        '<>',
        '<',
        '<=',
        '>',
        '>=',
        'LIKE',
        'ILIKE',
    ];

    public static function normalize(mixed $operator): string
    {
        if (is_string($operator)) {
            $normalized = strtoupper(trim($operator));

            if (in_array($normalized, self::ALLOWED, true)) {
                return $normalized;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Unsupported SQL operator %s. Allowed operators: %s.',
            self::describe($operator),
            implode(', ', self::ALLOWED)
        ));
    }

    private static function describe(mixed $operator): string
    {
        return match (true) {
            is_string($operator) => '"'.$operator.'"',
            $operator === null => 'null',
            is_bool($operator) => 'bool('.($operator ? 'true' : 'false').')',
            is_int($operator) => "int({$operator})",
            is_float($operator) => 'float('.var_export($operator, true).')',
            is_array($operator) => 'array',
            is_object($operator) => 'object('.$operator::class.')',
            default => get_debug_type($operator),
        };
    }
}
