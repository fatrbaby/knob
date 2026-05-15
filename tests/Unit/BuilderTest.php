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

    describe('whereGroup', function () {
        it('generates basic nested AND group', function () {
            $sql = Knob::table('users')->where('status', 'active')->where(fn ($q) => $q->where('type', 'A')->orWhere('type', 'B'))->toSql();
            expect($sql['sql'])->toContain('status = ?');
            expect($sql['sql'])->toContain('(type = ? OR type = ?)');
        });

        it('generates nested group with AND conditions inside', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->where('b', 2))->toSql();
            expect($sql['sql'])->toContain('(a = ? AND b = ?)');
        });

        it('generates multiple nested groups at same level', function () {
            $sql = Knob::table('users')
                ->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))
                ->where(fn ($q) => $q->where('c', 3)->orWhere('d', 4))
                ->toSql();
            expect($sql['sql'])->toContain('(a = ? OR b = ?)');
            expect($sql['sql'])->toContain('(c = ? OR d = ?)');
        });

        it('generates deeply nested groups (2 levels)', function () {
            $sql = Knob::table('users')
                ->where('x', 1)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('a', 'A')->orWhere('b', 'B'))->where('y', 2))
                ->toSql();
            expect($sql['sql'])->toContain('x = ?');
            expect($sql['sql'])->toContain('((a = ? OR b = ?) AND y = ?)');
        });

        it('preserves bindings order across groups', function () {
            $sql = Knob::table('users')
                ->where('a', 1)
                ->where(fn ($q) => $q->where('b', 2)->orWhere('c', 3))
                ->where('d', 4)
                ->toSql();
            expect($sql['bindings'])->toBe([1, 2, 3, 4]);
        });

        it('handles whereIn inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereIn('id', [1, 2, 3]))->toSql();
            expect($sql['sql'])->toContain('(id IN (?, ?, ?))');
            expect($sql['bindings'])->toBe([1, 2, 3]);
        });

        it('handles whereBetween inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereBetween('age', [18, 30]))->toSql();
            expect($sql['sql'])->toContain('(age BETWEEN ? AND ?)');
            expect($sql['bindings'])->toBe([18, 30]);
        });

        it('handles whereNull / whereNotNull inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereNull('deleted_at')->orWhereNotNull('active'))->toSql();
            expect($sql['sql'])->toContain('(deleted_at IS NULL OR active IS NOT NULL)');
        });

        it('handles whereExists inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereExists(fn ($sub) => $sub->from('posts', 'p')->whereRaw('p.user_id = users.id')))->toSql();
            expect($sql['sql'])->toContain('(EXISTS');
        });

        it('handles group at top level with no outer conditions', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))->toSql();
            expect($sql['sql'])->toContain('(a = ? OR b = ?)');
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
