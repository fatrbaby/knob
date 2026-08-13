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

describe('Grammar compilation', function (): void {
    it('quotes identifiers for each supported database', function (Grammar $grammar, string $expectedSql): void {
        expect($grammar->compileSelect(selectComponents()))->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT `id`, `name` FROM `users`'],
        'postgres' => [new PostgresGrammar(), 'SELECT "id", "name" FROM "users"'],
        'sqlite' => [new SqliteGrammar(), 'SELECT "id", "name" FROM "users"'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT [id], [name] FROM [users]'],
    ]);

    it('escapes identifier quote characters', function (Grammar $grammar, string $identifier, string $expected): void {
        expect($grammar->quoteIdentifier($identifier))->toBe($expected);
    })->with([
        'mysql' => [new MySqlGrammar(), 'user`name', '`user``name`'],
        'postgres' => [new PostgresGrammar(), 'user"name', '"user""name"'],
        'sqlite' => [new SqliteGrammar(), 'user"name', '"user""name"'],
        'sqlserver' => [new SqlServerGrammar(), 'user]name', '[user]]name]'],
    ]);

    it('compiles numeric select expressions as literals', function (Grammar $grammar, string $expectedSql): void {
        expect($grammar->compileSelect(selectComponents(['columns' => [1]])))->toBe($expectedSql);
    })->with([
        'mysql' => [new MySqlGrammar(), 'SELECT 1 FROM `users`'],
        'postgres' => [new PostgresGrammar(), 'SELECT 1 FROM "users"'],
        'sqlite' => [new SqliteGrammar(), 'SELECT 1 FROM "users"'],
        'sqlserver' => [new SqlServerGrammar(), 'SELECT 1 FROM [users]'],
    ]);

    it('compiles insert or ignore for supported databases', function (Grammar $grammar, string $expectedSql): void {
        $sql = $grammar->compileInsertOrIgnore([
            'table' => 'users',
            'columns' => ['id', 'name'],
            'values' => [[1, 'John']],
        ]);

        expect($sql)->toBe($expectedSql)
            ->and($grammar->getBindings())->toBe([1, 'John']);
    })->with([
        'mysql' => [new MySqlGrammar(), 'INSERT IGNORE INTO `users` (`id`, `name`) VALUES (?, ?)'],
        'postgres' => [new PostgresGrammar(), 'INSERT INTO "users" ("id", "name") VALUES (?, ?) ON CONFLICT DO NOTHING'],
        'sqlite' => [new SqliteGrammar(), 'INSERT INTO "users" ("id", "name") VALUES (?, ?) ON CONFLICT DO NOTHING'],
    ]);

    it('compiles upsert for each supported database', function (Grammar $grammar, string $expectedSql): void {
        $sql = $grammar->compileUpsert([
            'table' => 'users',
            'columns' => ['id', 'name', 'email'],
            'values' => [[1, 'John', 'john@example.com']],
            'uniqueBy' => ['id'],
            'update' => ['name', 'email'],
        ]);

        expect($sql)->toBe($expectedSql)
            ->and($grammar->getBindings())->toBe([1, 'John', 'john@example.com']);
    })->with([
        'mysql' => [new MySqlGrammar(), 'INSERT INTO `users` (`id`, `name`, `email`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `email` = VALUES(`email`)'],
        'postgres' => [new PostgresGrammar(), 'INSERT INTO "users" ("id", "name", "email") VALUES (?, ?, ?) ON CONFLICT ("id") DO UPDATE SET "name" = excluded."name", "email" = excluded."email"'],
        'sqlite' => [new SqliteGrammar(), 'INSERT INTO "users" ("id", "name", "email") VALUES (?, ?, ?) ON CONFLICT ("id") DO UPDATE SET "name" = excluded."name", "email" = excluded."email"'],
        'sqlserver' => [new SqlServerGrammar(), 'MERGE INTO [users] AS target USING (VALUES (?, ?, ?)) AS source ([id], [name], [email]) ON target.[id] = source.[id] WHEN MATCHED THEN UPDATE SET target.[name] = source.[name], target.[email] = source.[email] WHEN NOT MATCHED THEN INSERT ([id], [name], [email]) VALUES (source.[id], source.[name], source.[email]);'],
    ]);

    it('compiles join table aliases for each supported database', function (Grammar $grammar, string $expectedSql): void {
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

    it('compiles limit and offset for each supported database', function (Grammar $grammar, string $expectedSql): void {
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

    it('adds a default order for SQL Server limit without explicit order', function (): void {
        $sql = (new SqlServerGrammar())->compileSelect(selectComponents([
            'columns' => [1],
            'limit' => 1,
        ]));

        expect($sql)->toBe('SELECT 1 FROM [users] ORDER BY (SELECT 0) OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY');
    });

    it('compiles complex subquery components and bindings for each supported database', function (Grammar $grammar, string $expectedSql, array $expectedBindings): void {
        $sql = $grammar->compileSelect(selectComponents([
            'columns' => [
                'u.name',
                [
                    'column' => '(SELECT COUNT(*) FROM posts WHERE posts.user_id = u.id AND published = ?)',
                    'alias' => 'published_posts',
                    'bindings' => [1],
                ],
            ],
            'from' => ['(SELECT id, name, status, age FROM users WHERE status <> ?)', 'u', ['banned']],
            'joins' => [[
                'type' => 'INNER JOIN',
                'table' => '(SELECT user_id, SUM(score) AS total_score FROM posts WHERE published = ? GROUP BY user_id HAVING SUM(score) > 10)',
                'alias' => 'post_scores',
                'clauses' => [['u.id', '=', 'post_scores.user_id']],
                'bindings' => [1],
            ]],
            'wheres' => [
                [
                    'type' => 'group',
                    'boolean' => 'AND',
                    'wheres' => [
                        [
                            'type' => 'basic',
                            'column' => 'u.status',
                            'operator' => '=',
                            'value' => 'active',
                            'boolean' => 'AND',
                        ],
                        [
                            'type' => 'group',
                            'boolean' => 'OR',
                            'wheres' => [
                                [
                                    'type' => 'basic',
                                    'column' => 'u.status',
                                    'operator' => '=',
                                    'value' => 'pending',
                                    'boolean' => 'AND',
                                ],
                                [
                                    'type' => 'between',
                                    'column' => 'u.age',
                                    'values' => [20, 30],
                                    'boolean' => 'AND',
                                    'not' => false,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'inSub',
                    'column' => 'u.id',
                    'query' => [
                        'sql' => 'SELECT user_id FROM posts WHERE published = ? AND score NOT IN (?, ?)',
                        'bindings' => [1, 0, -1],
                    ],
                    'boolean' => 'AND',
                ],
            ],
            'orders' => [['column' => 'u.name', 'direction' => 'ASC']],
            'limit' => 5,
            'offset' => 10,
            'unions' => [[
                'all' => true,
                'sql' => 'SELECT name FROM archived_users WHERE restored = ?',
                'bindings' => [0],
            ]],
        ]));

        expect($sql)->toBe($expectedSql)
            ->and($grammar->getBindings())->toBe($expectedBindings);
    })->with([
        'mysql' => [
            new MySqlGrammar(),
            'SELECT u.name, (SELECT COUNT(*) FROM posts WHERE posts.user_id = u.id AND published = ?) AS `published_posts` FROM (SELECT id, name, status, age FROM users WHERE status <> ?) AS `u` INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM posts WHERE published = ? GROUP BY user_id HAVING SUM(score) > 10) AS `post_scores` ON u.id = post_scores.user_id WHERE (u.status = ? OR (u.status = ? AND u.age BETWEEN ? AND ?)) AND u.id IN (SELECT user_id FROM posts WHERE published = ? AND score NOT IN (?, ?)) ORDER BY u.name ASC LIMIT 5 OFFSET 10 UNION ALL SELECT name FROM archived_users WHERE restored = ?',
            [1, 'banned', 1, 'active', 'pending', 20, 30, 1, 0, -1, 0],
        ],
        'postgres' => [
            new PostgresGrammar(),
            'SELECT u.name, (SELECT COUNT(*) FROM posts WHERE posts.user_id = u.id AND published = ?) AS "published_posts" FROM (SELECT id, name, status, age FROM users WHERE status <> ?) AS "u" INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM posts WHERE published = ? GROUP BY user_id HAVING SUM(score) > 10) AS "post_scores" ON u.id = post_scores.user_id WHERE (u.status = ? OR (u.status = ? AND u.age BETWEEN ? AND ?)) AND u.id IN (SELECT user_id FROM posts WHERE published = ? AND score NOT IN (?, ?)) ORDER BY u.name ASC LIMIT 5 OFFSET 10 UNION ALL SELECT name FROM archived_users WHERE restored = ?',
            [1, 'banned', 1, 'active', 'pending', 20, 30, 1, 0, -1, 0],
        ],
        'sqlite' => [
            new SqliteGrammar(),
            'SELECT u.name, (SELECT COUNT(*) FROM posts WHERE posts.user_id = u.id AND published = ?) AS "published_posts" FROM (SELECT id, name, status, age FROM users WHERE status <> ?) AS "u" INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM posts WHERE published = ? GROUP BY user_id HAVING SUM(score) > 10) AS "post_scores" ON u.id = post_scores.user_id WHERE (u.status = ? OR (u.status = ? AND u.age BETWEEN ? AND ?)) AND u.id IN (SELECT user_id FROM posts WHERE published = ? AND score NOT IN (?, ?)) ORDER BY u.name ASC LIMIT 5 OFFSET 10 UNION ALL SELECT name FROM archived_users WHERE restored = ?',
            [1, 'banned', 1, 'active', 'pending', 20, 30, 1, 0, -1, 0],
        ],
        'sqlserver' => [
            new SqlServerGrammar(),
            'SELECT u.name, (SELECT COUNT(*) FROM posts WHERE posts.user_id = u.id AND published = ?) AS [published_posts] FROM (SELECT id, name, status, age FROM users WHERE status <> ?) AS [u] INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM posts WHERE published = ? GROUP BY user_id HAVING SUM(score) > 10) AS [post_scores] ON u.id = post_scores.user_id WHERE (u.status = ? OR (u.status = ? AND u.age BETWEEN ? AND ?)) AND u.id IN (SELECT user_id FROM posts WHERE published = ? AND score NOT IN (?, ?)) ORDER BY u.name ASC OFFSET 10 ROWS FETCH NEXT 5 ROWS ONLY UNION ALL SELECT name FROM archived_users WHERE restored = ?',
            [1, 'banned', 1, 'active', 'pending', 20, 30, 1, 0, -1, 0],
        ],
    ]);
});
