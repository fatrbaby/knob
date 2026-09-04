<?php

use Knob\Driver;
use Knob\Knob;
use PHPUnit\Framework\Assert;

final class TransactionPdoStub extends PDO
{
    public bool $beginResult = true;
    public bool $commitResult = true;
    public bool $rollbackResult = true;
    public bool $transactionActive = false;
    public bool $commitThrows = false;
    public bool $rollbackThrows = false;
    /** @var list<string> */
    public array $statements = [];

    public function __construct(public string $driverName = 'sqlite')
    {
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->driverName;
    }

    public function beginTransaction(): bool
    {
        $this->transactionActive = $this->beginResult;

        return $this->beginResult;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function commit(): bool
    {
        if ($this->commitThrows) {
            throw new RuntimeException('commit failed');
        }

        if ($this->commitResult) {
            $this->transactionActive = false;
        }

        return $this->commitResult;
    }

    public function rollBack(): bool
    {
        if ($this->rollbackThrows) {
            throw new RuntimeException('rollback failed');
        }

        if ($this->rollbackResult) {
            $this->transactionActive = false;
        }

        return $this->rollbackResult;
    }

    public function exec(string $statement): int
    {
        $this->statements[] = $statement;

        return 0;
    }
}

describe('Knob facade', function (): void {
    beforeEach(function (): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        Knob::using($pdo);
    });

    it('detects the active PDO driver', function (): void {
        expect(Knob::getDriver())->toBe(Driver::SQLite);
    });

    it('rejects unsupported PDO drivers with a clear exception', function (): void {
        expect(fn () => Knob::using(new TransactionPdoStub('odbc')))
            ->toThrow(RuntimeException::class, 'Unsupported PDO driver: odbc');
    });

    it('rolls back failed transactions', function (): void {
        expect(fn () => Knob::transaction(function (): void {
            Knob::table('users')->insert(['name' => 'Alice']);

            throw new RuntimeException('rollback');
        }))->toThrow(RuntimeException::class, 'rollback')
            ->and(Knob::table('users')->count())->toBe(0);
    });

    it('commits successful transactions and returns the callback result', function (): void {
        $result = Knob::transaction(function (): string {
            Knob::table('users')->insert(['name' => 'Alice']);

            return 'committed';
        });

        expect($result)->toBe('committed')
            ->and(Knob::table('users')->pluck('name')->toArray())->toBe(['Alice']);
    });

    it('rolls back only the failed nested transaction', function (): void {
        Knob::transaction(function (): void {
            Knob::table('users')->insert(['name' => 'Alice']);

            try {
                Knob::transaction(function (): void {
                    Knob::table('users')->insert(['name' => 'Bob']);

                    throw new RuntimeException('inner failure');
                });
            } catch (RuntimeException $exception) {
                expect($exception->getMessage())->toBe('inner failure');
            }

            Knob::table('users')->insert(['name' => 'Carol']);
        });

        expect(Knob::table('users')->orderBy('id')->pluck('name')->toArray())
            ->toBe(['Alice', 'Carol']);
    });

    it('throws when starting a transaction fails without running the callback', function (): void {
        $pdo = new TransactionPdoStub();
        $pdo->beginResult = false;
        Knob::using($pdo);
        $called = false;

        expect(fn () => Knob::transaction(function () use (&$called): void {
            $called = true;
        }))->toThrow(RuntimeException::class, 'Failed to begin transaction')
            ->and($called)->toBeFalse();
    });

    it('rolls back and throws when committing a transaction fails', function (): void {
        $pdo = new TransactionPdoStub();
        $pdo->commitResult = false;
        Knob::using($pdo);

        expect(fn () => Knob::transaction(fn () => 'result'))
            ->toThrow(RuntimeException::class, 'Failed to commit transaction')
            ->and($pdo->transactionActive)->toBeFalse();
    });

    it('rolls back and preserves a commit exception', function (): void {
        $pdo = new TransactionPdoStub();
        $pdo->commitThrows = true;
        Knob::using($pdo);

        expect(fn () => Knob::transaction(fn () => 'result'))
            ->toThrow(RuntimeException::class, 'commit failed')
            ->and($pdo->transactionActive)->toBeFalse();
    });

    it('preserves the original exception when rollback also fails', function (): void {
        $pdo = new TransactionPdoStub();
        $pdo->rollbackThrows = true;
        Knob::using($pdo);

        try {
            Knob::transaction(fn () => throw new LogicException('callback failed'));
            Assert::fail('Expected transaction failure');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toContain('callback failed')
                ->toContain('rollback failed')
                ->and($exception->getPrevious())->toBeInstanceOf(LogicException::class)
                ->and($exception->getPrevious()?->getMessage())->toBe('callback failed');
        }
    });

    it('uses the driver savepoint syntax inside an existing transaction', function (string $driver, array $expected): void {
        $pdo = new TransactionPdoStub($driver);
        $pdo->transactionActive = true;
        Knob::using($pdo);

        expect(Knob::transaction(fn () => 'nested'))->toBe('nested')
            ->and($pdo->statements)->toBe($expected);
    })->with([
        'mysql' => ['mysql', ['SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'postgres' => ['pgsql', ['SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'sqlite' => ['sqlite', ['SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'sqlserver' => ['sqlsrv', ['SAVE TRANSACTION knob_savepoint_1']],
    ]);

    it('uses the driver rollback syntax for a failed savepoint', function (string $driver, array $expected): void {
        $pdo = new TransactionPdoStub($driver);
        $pdo->transactionActive = true;
        Knob::using($pdo);

        expect(fn () => Knob::transaction(fn () => throw new RuntimeException('nested failed')))
            ->toThrow(RuntimeException::class, 'nested failed')
            ->and($pdo->statements)->toBe($expected);
    })->with([
        'mysql' => ['mysql', ['SAVEPOINT knob_savepoint_1', 'ROLLBACK TO SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'postgres' => ['pgsql', ['SAVEPOINT knob_savepoint_1', 'ROLLBACK TO SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'sqlite' => ['sqlite', ['SAVEPOINT knob_savepoint_1', 'ROLLBACK TO SAVEPOINT knob_savepoint_1', 'RELEASE SAVEPOINT knob_savepoint_1']],
        'sqlserver' => ['sqlsrv', ['SAVE TRANSACTION knob_savepoint_1', 'ROLLBACK TRANSACTION knob_savepoint_1']],
    ]);
});
