<?php

use Knob\Grammars\Grammar;
use Knob\Grammars\MySqlGrammar;
use Knob\Grammars\PostgresGrammar;
use Knob\Grammars\SqliteGrammar;
use Knob\Grammars\SqlServerGrammar;

function selectComponents(array $overrides = []): array
{
    return [
        'columns' => ['id', 'name'],
        'from' => ['users', null, []],
        'joins' => [],
        'wheres' => [],
        'groups' => [],
        'havings' => [],
        'orders' => [],
        'limit' => null,
        'offset' => null,
        'unions' => [],
        ...$overrides,
    ];
}

describe('Grammar compilation', function () {
    it('quotes identifiers for each supported database', function (Grammar $grammar, string $expectedSql) {
        expect($grammar->compileSelect(selectComponents()))->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT `id`, `name` FROM `users`'],
        'postgres' => [new PostgresGrammar(), 'SELECT "id", "name" FROM "users"'],
        'sqlite' => [new SqliteGrammar(), 'SELECT "id", "name" FROM "users"'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT [id], [name] FROM [users]'],
    ]);

    it('escapes identifier quote characters', function (Grammar $grammar, string $identifier, string $expected) {
        expect($grammar->quoteIdentifier($identifier))->toBe($expected);
    })->with([
        'mysql' => [new MySqlGrammar(), 'user`name', '`user``name`'],
        'postgres' => [new PostgresGrammar(), 'user"name', '"user""name"'],
        'sqlite' => [new SqliteGrammar(), 'user"name', '"user""name"'],
        'sqlserver' => [new SqlServerGrammar(), 'user]name', '[user]]name]'],
    ]);

    it('compiles numeric select expressions as literals', function (Grammar $grammar, string $expectedSql) {
        expect($grammar->compileSelect(selectComponents(['columns' => [1]])))->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT 1 FROM `users`'],
        'postgres' => [new PostgresGrammar(), 'SELECT 1 FROM "users"'],
        'sqlite' => [new SqliteGrammar(), 'SELECT 1 FROM "users"'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT 1 FROM [users]'],
    ]);

    it('compiles join table aliases for each supported database', function (Grammar $grammar, string $expectedSql) {
        $sql = $grammar->compileSelect(selectComponents([
            'from' => ['users', 'u', []],
            'joins' => [[
                'type' => 'INNER JOIN',
                'table' => 'posts',
                'alias' => 'p',
                'clauses' => [['u.id', '=', 'p.user_id']],
            ]],
        ]));

        expect($sql)->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT `id`, `name` FROM `users` AS `u` INNER JOIN `posts` AS `p` ON u.id = p.user_id'],
        'postgres' => [new PostgresGrammar(), 'SELECT "id", "name" FROM "users" AS "u" INNER JOIN "posts" AS "p" ON u.id = p.user_id'],
        'sqlite' => [new SqliteGrammar(), 'SELECT "id", "name" FROM "users" AS "u" INNER JOIN "posts" AS "p" ON u.id = p.user_id'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT [id], [name] FROM [users] AS [u] INNER JOIN [posts] AS [p] ON u.id = p.user_id'],
    ]);

    it('compiles limit and offset for each supported database', function (Grammar $grammar, string $expectedSql) {
        $sql = $grammar->compileSelect(selectComponents([
            'orders' => [['column' => 'id', 'direction' => 'ASC']],
            'limit' => 10,
            'offset' => 20,
        ]));

        expect($sql)->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT `id`, `name` FROM `users` ORDER BY id ASC LIMIT 10 OFFSET 20'],
        'postgres' => [new PostgresGrammar(), 'SELECT "id", "name" FROM "users" ORDER BY id ASC LIMIT 10 OFFSET 20'],
        'sqlite' => [new SqliteGrammar(), 'SELECT "id", "name" FROM "users" ORDER BY id ASC LIMIT 10 OFFSET 20'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT [id], [name] FROM [users] ORDER BY id ASC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY'],
    ]);
});
