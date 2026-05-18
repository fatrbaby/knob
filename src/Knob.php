<?php

namespace Knob;

use PDO;

class Knob
{
    private static PDO $connection;
    private static Driver $driver = Driver::PostgreSQL;

    public static function using(PDO $connection): void
    {
        self::$connection = $connection;
        self::$driver = self::getDriver();
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
        return match (self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'pgsql' => Driver::PostgreSQL,
            'mysql' => Driver::MySQL,
            'sqlite' => Driver::SQLite,
            'sqlsrv' => Driver::SQLServer,
        };
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
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
