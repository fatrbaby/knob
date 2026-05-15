<?php

use Knob\Knob;

describe('Builder', function () {
    beforeEach(function () {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Knob::using($pdo);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO users (name, email, status, age) VALUES ('John', 'john@example.com', 'active', 25)");
        $pdo->exec("INSERT INTO users (name, email, status, age) VALUES ('Jane', 'jane@example.com', 'active', 30)");
    });

    describe('select', function () {
        it('generates select all', function () {
            $sql = Knob::table('users')->toSql();
            expect($sql['columns'])->toBe(['*']);
        });

        it('generates select with columns', function () {
            $sql = Knob::table('users')->select('name', 'email')->toSql();
            expect($sql['columns'])->toBe(['name', 'email']);
        });
    });

    describe('where', function () {
        it('generates basic where clause', function () {
            $sql = Knob::table('users')->where('status', 'active')->toSql();
            expect($sql['wheres'])->toHaveCount(1);
            expect($sql['wheres'][0]['type'])->toBe('basic');
            expect($sql['wheres'][0]['column'])->toBe('status');
            expect($sql['wheres'][0]['operator'])->toBe('=');
            expect($sql['wheres'][0]['value'])->toBe('active');
        });

        it('generates where with operator', function () {
            $sql = Knob::table('users')->where('age', '>', 18)->toSql();
            expect($sql['wheres'][0]['operator'])->toBe('>');
            expect($sql['wheres'][0]['value'])->toBe(18);
        });

        it('generates or where', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSql();
            expect($sql['wheres'])->toHaveCount(2);
            expect($sql['wheres'][0]['boolean'])->toBe('AND');
            expect($sql['wheres'][1]['boolean'])->toBe('OR');
        });
    });

    describe('whereIn', function () {
        it('generates whereIn clause', function () {
            $sql = Knob::table('users')->whereIn('id', [1, 2, 3])->toSql();
            expect($sql['wheres'][0]['type'])->toBe('in');
            expect($sql['wheres'][0]['values'])->toBe([1, 2, 3]);
        });
    });

    describe('whereNull', function () {
        it('generates whereNull clause', function () {
            $sql = Knob::table('users')->whereNull('email')->toSql();
            expect($sql['wheres'][0]['type'])->toBe('null');
            expect($sql['wheres'][0]['column'])->toBe('email');
        });
    });

    describe('joins', function () {
        it('generates left join', function () {
            $sql = Knob::table('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id')->toSql();
            expect($sql['joins'])->toHaveCount(1);
            expect($sql['joins'][0]['type'])->toBe('LEFT JOIN');
            expect($sql['joins'][0]['table'])->toBe('posts');
        });
    });

    describe('orderBy', function () {
        it('generates order by', function () {
            $sql = Knob::table('users')->orderBy('name', 'ASC')->toSql();
            expect($sql['orders'])->toHaveCount(1);
            expect($sql['orders'][0]['column'])->toBe('name');
            expect($sql['orders'][0]['direction'])->toBe('ASC');
        });

        it('generates order by desc', function () {
            $sql = Knob::table('users')->orderByDesc('created_at')->toSql();
            expect($sql['orders'][0]['direction'])->toBe('DESC');
        });
    });

    describe('limit and offset', function () {
        it('generates limit and offset', function () {
            $sql = Knob::table('users')->limit(10)->offset(20)->toSql();
            expect($sql['limit'])->toBe(10);
            expect($sql['offset'])->toBe(20);
        });
    });

    describe('groupBy', function () {
        it('generates group by', function () {
            $sql = Knob::table('posts')->groupBy('user_id')->toSql();
            expect($sql['groups'])->toBe(['user_id']);
        });
    });

    describe('execution', function () {
        it('gets all results', function () {
            $results = Knob::table('users')->get();
            expect($results)->toBeInstanceOf(\Knob\Collection::class);
            expect($results->count())->toBe(2);
        });

        it('gets first result', function () {
            $result = Knob::table('users')->first();
            expect($result['name'])->toBe('John');
        });

        it('counts records', function () {
            $count = Knob::table('users')->count();
            expect($count)->toBe(2);
        });

        it('checks exists', function () {
            $exists = Knob::table('users')->where('status', 'active')->exists();
            expect($exists)->toBe(true);
        });

        it('plucks values', function () {
            $names = Knob::table('users')->pluck('name')->toArray();
            expect($names)->toBe(['John', 'Jane']);
        });
    });
});
