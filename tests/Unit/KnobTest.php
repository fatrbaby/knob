<?php

use Knob\Driver;
use Knob\Knob;

describe('Knob facade', function () {
    beforeEach(function () {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        Knob::using($pdo);
    });

    it('detects the active PDO driver', function () {
        expect(Knob::getDriver())->toBe(Driver::SQLite);
    });

    it('rolls back failed transactions', function () {
        expect(fn () => Knob::transaction(function () {
            Knob::table('users')->insert(['name' => 'Alice']);

            throw new RuntimeException('rollback');
        }))->toThrow(RuntimeException::class, 'rollback')
            ->and(Knob::table('users')->count())->toBe(0);
    });
});
