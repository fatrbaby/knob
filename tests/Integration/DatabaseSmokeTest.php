<?php

use Knob\Knob;

function smokeDatabaseConfig(string $driver): ?array
{
    if ($driver === 'sqlite') {
        return [
            'dsn' => 'sqlite::memory:',
            'user' => null,
            'password' => null,
            'driver' => $driver,
        ];
    }

    $prefix = 'KNOB_' . strtoupper($driver);
    $dsn = getenv("{$prefix}_DSN");

    if (! $dsn) {
        return null;
    }

    return [
        'dsn' => $dsn,
        'user' => getenv("{$prefix}_USER") ?: null,
        'password' => getenv("{$prefix}_PASSWORD") ?: null,
        'driver' => $driver,
    ];
}

function smokeCreateTableSql(string $driver): string
{
    return match ($driver) {
        'sqlite' => 'CREATE TABLE knob_smoke_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT, age INTEGER)',
        'mysql' => 'CREATE TABLE knob_smoke_users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), status VARCHAR(50), age INT)',
        'pgsql' => 'CREATE TABLE knob_smoke_users (id SERIAL PRIMARY KEY, name VARCHAR(255), status VARCHAR(50), age INT)',
        'sqlsrv' => 'CREATE TABLE knob_smoke_users (id INT IDENTITY(1,1) PRIMARY KEY, name NVARCHAR(255), status NVARCHAR(50), age INT)',
    };
}

function smokeDropTableSql(string $driver): string
{
    return match ($driver) {
        'sqlite' => 'DROP TABLE IF EXISTS knob_smoke_users',
        'mysql', 'pgsql' => 'DROP TABLE IF EXISTS knob_smoke_users',
        'sqlsrv' => "IF OBJECT_ID('knob_smoke_users', 'U') IS NOT NULL DROP TABLE knob_smoke_users",
    };
}

describe('Database smoke tests', function () {
    it('runs a query builder smoke test against configured databases', function (string $driver) {
        $only = getenv('KNOB_SMOKE_ONLY');

        if ($only && $only !== $driver) {
            $this->markTestSkipped("KNOB_SMOKE_ONLY={$only} selected.");
        }

        $config = smokeDatabaseConfig($driver);

        if ($config === null) {
            $prefix = 'KNOB_' . strtoupper($driver);

            $this->markTestSkipped("Set {$prefix}_DSN to run this integration smoke test.");
        }

        $pdo = new PDO($config['dsn'], $config['user'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Knob::using($pdo);

        $pdo->exec(smokeDropTableSql($config['driver']));
        $pdo->exec(smokeCreateTableSql($config['driver']));

        try {
            Knob::table('knob_smoke_users')->insert([
                ['name' => 'Alice', 'status' => 'active', 'age' => 30],
                ['name' => 'Bob', 'status' => 'pending', 'age' => 20],
                ['name' => 'Carol', 'status' => 'active', 'age' => 25],
            ]);

            $active = Knob::table('knob_smoke_users')
                ->select('name')
                ->where('status', 'active')
                ->orderBy('age')
                ->limit(1)
                ->offset(1)
                ->pluck('name')
                ->toArray();

            expect($active)->toBe(['Alice'])
                ->and(Knob::table('knob_smoke_users')->where('name', 'Bob')->update(['status' => 'active']))->toBe(1)
                ->and(Knob::table('knob_smoke_users')->where('status', 'active')->count())->toBe(3)
                ->and(fn () => Knob::transaction(function () {
                    Knob::table('knob_smoke_users')->insert(['name' => 'Dave', 'status' => 'active', 'age' => 40]);

                    throw new RuntimeException('rollback');
                }))->toThrow(RuntimeException::class, 'rollback')
                ->and(Knob::table('knob_smoke_users')->where('name', 'Dave')->exists())->toBeFalse()
                ->and(Knob::table('knob_smoke_users')->where('name', 'Carol')->delete())->toBe(1);
        } finally {
            $pdo->exec(smokeDropTableSql($config['driver']));
        }
    })->with(['sqlite', 'mysql', 'pgsql', 'sqlsrv']);
});
