<?php

use Knob\SqlOperator;

dataset('supported SQL operators', [
    ['=', '='],
    ['!=', '!='],
    ['<>', '<>'],
    ['<', '<'],
    ['<=', '<='],
    ['>', '>'],
    ['>=', '>='],
    ['LIKE', 'LIKE'],
    ['ILIKE', 'ILIKE'],
    [' like ', 'LIKE'],
    ["\tIlIkE\n", 'ILIKE'],
]);

it('normalizes supported SQL operators', function (string $operator, string $expected): void {
    expect(SqlOperator::normalize($operator))->toBe($expected);
})->with('supported SQL operators');

it('rejects unsupported string operators with the original value and complete allowlist', function (string $operator): void {
    expect(fn () => SqlOperator::normalize($operator))
        ->toThrow(
            InvalidArgumentException::class,
            'Unsupported SQL operator "'.$operator.'". Allowed operators: =, !=, <>, <, <=, >, >=, LIKE, ILIKE.'
        );
})->with([
    'empty string' => [''],
    'injection payload' => ['= ? OR 1=1 --'],
    'unsupported keyword' => ['REGEXP'],
]);

it('rejects non-string operators with a type-aware description', function (mixed $operator, string $description): void {
    expect(fn () => SqlOperator::normalize($operator))
        ->toThrow(
            InvalidArgumentException::class,
            "Unsupported SQL operator {$description}. Allowed operators: =, !=, <>, <, <=, >, >=, LIKE, ILIKE."
        );
})->with([
    'null' => [null, 'null'],
    'boolean' => [true, 'bool(true)'],
    'integer' => [42, 'int(42)'],
    'array' => [[], 'array'],
    'object' => [new stdClass(), 'object(stdClass)'],
]);
