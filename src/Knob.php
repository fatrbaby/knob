<?php

namespace Knob;

use PDO;
use RuntimeException;
use Throwable;

final class Knob
{
    private static PDO $connection;
    private static Driver $driver;
    private static int $savepointSequence = 0;

    private function __construct()
    {
    }

    public static function using(PDO $connection): void
    {
        $driver = self::detectDriver($connection);

        self::$connection = $connection;
        self::$driver = $driver;
        self::$savepointSequence = 0;
    }

    public static function getConnection(): PDO
    {
        return self::$connection;
    }

    public static function table(string $table, ?string $alias = null): Builder
    {
        return Builder::table(self::$connection, $table, $alias);
    }

    public static function query(): Builder
    {
        return new Builder(self::$connection);
    }

    public static function getDriver(): Driver
    {
        return self::$driver;
    }

    public static function beginTransaction(): bool
    {
        return self::$connection->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::$connection->commit();
    }

    public static function rollBack(): bool
    {
        return self::$connection->rollBack();
    }

    public static function transaction(callable $callback): mixed
    {
        $nested = self::$connection->inTransaction();
        $savepoint = $nested ? self::createSavepoint() : null;

        if (! $nested && ! self::beginTransaction()) {
            throw new RuntimeException('Failed to begin transaction');
        }

        try {
            $result = $callback();

            if ($savepoint !== null) {
                self::releaseSavepoint($savepoint);
            } elseif (! self::commit()) {
                throw new RuntimeException('Failed to commit transaction');
            }

            return $result;
        } catch (Throwable $exception) {
            try {
                if ($savepoint !== null) {
                    self::rollbackToSavepoint($savepoint);
                } elseif (self::$connection->inTransaction() && ! self::rollBack()) {
                    throw new RuntimeException('Failed to roll back transaction');
                }
            } catch (Throwable $rollbackException) {
                throw new RuntimeException(
                    "Transaction failed: {$exception->getMessage()}; rollback failed: {$rollbackException->getMessage()}",
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    private static function createSavepoint(): string
    {
        $savepoint = 'knob_savepoint_' . ++self::$savepointSequence;
        $sql = self::$driver === Driver::SQLServer
            ? "SAVE TRANSACTION {$savepoint}"
            : "SAVEPOINT {$savepoint}";

        if (self::$connection->exec($sql) === false) {
            throw new RuntimeException('Failed to create transaction savepoint');
        }

        return $savepoint;
    }

    private static function releaseSavepoint(string $savepoint): void
    {
        if (self::$driver === Driver::SQLServer) {
            return;
        }

        if (self::$connection->exec("RELEASE SAVEPOINT {$savepoint}") === false) {
            throw new RuntimeException('Failed to release transaction savepoint');
        }
    }

    private static function rollbackToSavepoint(string $savepoint): void
    {
        $sql = self::$driver === Driver::SQLServer
            ? "ROLLBACK TRANSACTION {$savepoint}"
            : "ROLLBACK TO SAVEPOINT {$savepoint}";

        if (self::$connection->exec($sql) === false) {
            throw new RuntimeException('Failed to roll back transaction savepoint');
        }

        self::releaseSavepoint($savepoint);
    }

    private static function detectDriver(PDO $connection): Driver
    {
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (! is_string($driver)) {
            throw new RuntimeException('PDO driver name must be a string, ' . get_debug_type($driver) . ' returned');
        }

        return match ($driver) {
            'pgsql' => Driver::PostgreSQL,
            'mysql' => Driver::MySQL,
            'sqlite' => Driver::SQLite,
            'sqlsrv' => Driver::SQLServer,
            default => throw new RuntimeException("Unsupported PDO driver: {$driver}"),
        };
    }
}
