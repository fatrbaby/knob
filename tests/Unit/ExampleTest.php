<?php

use Knob\Driver;
use Knob\Knob;

describe('testing for knob', function () {
    it('create knob', function () {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Knob::using($pdo);
        expect(Knob::getDriver())->toBe(Driver::SQLite);
    });
});
