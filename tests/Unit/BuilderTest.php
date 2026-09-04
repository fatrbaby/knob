<?php

use Knob\Knob;

describe('Builder', function (): void {
    beforeEach(function (): void {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Knob::using($pdo);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO users (name, email, status, age) VALUES ('John', 'john@example.com', 'active', 25)");
        $pdo->exec("INSERT INTO users (name, email, status, age) VALUES ('Jane', 'jane@example.com', 'active', 30)");
    });

    describe('select', function (): void {
        it('generates select all', function (): void {
            $sql = Knob::table('users')->toSqlParts();
            expect($sql['columns'])->toBe(['*']);
        });

        it('generates select with columns', function (): void {
            $sql = Knob::table('users')->select('name', 'email')->toSqlParts();
            expect($sql['columns'])->toBe(['name', 'email']);
        });

        it('generates distinct select', function (): void {
            $sql = Knob::table('users')->distinct()->select('status')->toSqlParts();
            expect($sql['distinct'])->toBeTrue()
                ->and($sql['sql'])->toContain('SELECT DISTINCT "status" FROM "users"');
        });

        it('generates selectRaw without implicit wildcard', function (): void {
            $sql = Knob::table('users')->selectRaw('COUNT(*)')->toSqlParts();
            expect($sql['sql'])->toBe('SELECT COUNT(*) FROM "users"');
        });

        it('omits from when compiling a query without a table', function (): void {
            expect(Knob::query()->selectRaw('1')->toSqlParts()['sql'])->toBe('SELECT 1');
        });

        it('executes a constant select without a table', function (): void {
            expect(Knob::query()->selectRaw('1 AS result')->first())->toBe(['result' => 1]);
        });

        it('supports raw string selectSub', function (): void {
            $sql = Knob::table('users')->selectSub('SELECT 1', 'one')->toSqlParts();
            expect($sql['sql'])->toContain('(SELECT 1) AS "one"');
        });

        it('omits AS when selectSub has no alias', function (string|Closure|\Knob\Builder $subquery): void {
            $sql = Knob::table('users')
                ->select('name')
                ->selectSub($subquery)
                ->toSqlParts()['sql'];

            expect($sql)->toBe('SELECT "name", (SELECT 1) FROM "users"');
        })->with([
            'raw SQL' => ['SELECT 1'],
            'closure' => [fn () => fn ($query) => $query->selectRaw('1')],
        ]);

        it('omits AS when a reusable selectSub builder has no alias', function (): void {
            $sql = Knob::table('users')
                ->select('name')
                ->selectSub(Knob::query()->selectRaw('1'))
                ->toSqlParts()['sql'];

            expect($sql)->toBe('SELECT "name", (SELECT 1) FROM "users"');
        });
    });

    describe('where', function (): void {
        it('generates basic where clause', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->toSqlParts();
            expect($sql['wheres'])->toHaveCount(1)
                ->and($sql['wheres'][0]['type'])->toBe('basic')
                ->and($sql['wheres'][0]['column'])->toBe('status')
                ->and($sql['wheres'][0]['operator'])->toBe('=')
                ->and($sql['wheres'][0]['value'])->toBe('active');
        });

        it('generates where with operator', function (): void {
            $sql = Knob::table('users')->where('age', '>', 18)->toSqlParts();
            expect($sql['wheres'][0]['operator'])->toBe('>')
                ->and($sql['wheres'][0]['value'])->toBe(18);
        });

        it('generates or where', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSqlParts();
            expect($sql['wheres'])->toHaveCount(2)
                ->and($sql['wheres'][0]['boolean'])->toBe('AND')
                ->and($sql['wheres'][1]['boolean'])->toBe('OR');
        });

        it('compiles or where with OR connector', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSqlParts();
            expect($sql['sql'])->toContain('"status" = ? OR "status" = ?');
        });

        it('compiles null values as null predicates', function (): void {
            $sql = Knob::table('users')
                ->where('status', null)
                ->orWhere('email', '!=', null)
                ->toSqlParts();

            expect($sql['sql'])->toContain('"status" IS NULL OR "email" IS NOT NULL')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates negated where groups', function (): void {
            $sql = Knob::table('users')
                ->whereNot(fn ($q) => $q->where('status', 'inactive')->orWhere('age', '<', 18))
                ->orWhereNot(fn ($q) => $q->whereNull('email'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('WHERE NOT ("status" = ? OR "age" < ?) OR NOT ("email" IS NULL)')
                ->and($sql['bindings'])->toBe(['inactive', 18]);
        });

        it('generates date based where predicates', function (): void {
            $sql = Knob::table('users')
                ->whereDate('created_at', '2026-05-28')
                ->orWhereTime('created_at', '>=', '09:00:00')
                ->whereYear('created_at', 2026)
                ->whereMonth('created_at', 5)
                ->toSqlParts();

            expect($sql['sql'])->toContain('DATE("created_at") = ?')
                ->and($sql['sql'])->toContain('TIME("created_at") >= ?')
                ->and($sql['sql'])->toContain("CAST(STRFTIME('%Y', \"created_at\") AS INTEGER) = ?")
                ->and($sql['sql'])->toContain("CAST(STRFTIME('%m', \"created_at\") AS INTEGER) = ?")
                ->and($sql['bindings'])->toBe(['2026-05-28', '09:00:00', 2026, 5]);
        });
    });

    describe('whereGroup', function (): void {
        it('generates basic nested AND group', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->where(fn ($q) => $q->where('type', 'A')->orWhere('type', 'B'))->toSqlParts();
            expect($sql['sql'])->toContain('"status" = ?')
                ->and($sql['sql'])->toContain('("type" = ? OR "type" = ?)');
        });

        it('generates nested group with AND conditions inside', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->where('b', 2))->toSqlParts();
            expect($sql['sql'])->toContain('("a" = ? AND "b" = ?)');
        });

        it('generates multiple nested groups at same level', function (): void {
            $sql = Knob::table('users')
                ->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))
                ->where(fn ($q) => $q->where('c', 3)->orWhere('d', 4))
                ->toSqlParts();
            expect($sql['sql'])->toContain('("a" = ? OR "b" = ?)')
                ->and($sql['sql'])->toContain('("c" = ? OR "d" = ?)');
        });

        it('generates deeply nested groups (2 levels)', function (): void {
            $sql = Knob::table('users')
                ->where('x', 1)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('a', 'A')->orWhere('b', 'B'))->where('y', 2))
                ->toSqlParts();
            expect($sql['sql'])->toContain('"x" = ?')
                ->and($sql['sql'])->toContain('(("a" = ? OR "b" = ?) AND "y" = ?)');
        });

        it('preserves bindings order across groups', function (): void {
            $sql = Knob::table('users')
                ->where('a', 1)
                ->where(fn ($q) => $q->where('b', 2)->orWhere('c', 3))
                ->where('d', 4)
                ->toSqlParts();
            expect($sql['bindings'])->toBe([1, 2, 3, 4]);
        });

        it('handles whereIn inside group', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereIn('id', [1, 2, 3]))->toSqlParts();
            expect($sql['sql'])->toContain('("id" IN (?, ?, ?))')
                ->and($sql['bindings'])->toBe([1, 2, 3]);
        });

        it('handles whereBetween inside group', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereBetween('age', [18, 30]))->toSqlParts();
            expect($sql['sql'])->toContain('("age" BETWEEN ? AND ?)')
                ->and($sql['bindings'])->toBe([18, 30]);
        });

        it('handles whereNull / whereNotNull inside group', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereNull('deleted_at')->orWhereNotNull('active'))->toSqlParts();
            expect($sql['sql'])->toContain('("deleted_at" IS NULL OR "active" IS NOT NULL)');
        });

        it('handles whereExists inside group', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereExists(fn ($sub) => $sub->from('posts', 'p')->whereRaw('p.user_id = users.id')))->toSqlParts();
            expect($sql['sql'])->toContain('(EXISTS');
        });

        it('handles group at top level with no outer conditions', function (): void {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))->toSqlParts();
            expect($sql['sql'])->toContain('("a" = ? OR "b" = ?)');
        });

        it('ignores empty condition groups without changing query state', function (Closure $apply): void {
            $query = Knob::table('users')->where('status', 'active');
            $before = $query->toSqlParts();

            $apply($query);

            expect($query->toSqlParts())->toBe($before);
        })->with([
            'where' => [fn ($query) => $query->where(fn () => null)],
            'orWhere' => [fn ($query) => $query->orWhere(fn () => null)],
            'whereNot' => [fn ($query) => $query->whereNot(fn () => null)],
            'orWhereNot' => [fn ($query) => $query->orWhereNot(fn () => null)],
        ]);
    });

    describe('whereIn', function (): void {
        it('generates whereIn clause', function (): void {
            $sql = Knob::table('users')->whereIn('id', [1, 2, 3])->toSqlParts();
            expect($sql['wheres'][0]['type'])->toBe('in')
                ->and($sql['wheres'][0]['values'])->toBe([1, 2, 3]);
        });

        it('generates orWhereIn clause', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->orWhereIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('"status" = ? OR "id" IN (?, ?)')
                ->and($sql['bindings'])->toBe(['active', 1, 2]);
        });

        it('generates whereNotIn clause', function (): void {
            $sql = Knob::table('users')->whereNotIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('"id" NOT IN (?, ?)')
                ->and($sql['bindings'])->toBe([1, 2]);
        });

        it('generates orWhereNotIn clause', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->orWhereNotIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('"status" = ? OR "id" NOT IN (?, ?)')
                ->and($sql['bindings'])->toBe(['active', 1, 2]);
        });

        it('generates orWhereNotIn with subquery input', function (): void {
            $subquery = Knob::query()->select('id')->from('users')->where('status', 'inactive');
            $sql = Knob::table('posts')->where('published', true)->orWhereNotIn('user_id', $subquery)->toSqlParts();

            expect($sql['sql'])->toContain(
                '"published" = ? OR "user_id" NOT IN (SELECT "id" FROM "users" WHERE "status" = ?)'
            )
                ->and($sql['bindings'])->toBe([true, 'inactive']);
        });
    });

    describe('common where predicates', function (): void {
        it('generates like and or like clauses', function (): void {
            $sql = Knob::table('users')->whereLike('name', 'A%')->orWhereLike('email', '%@example.com')->toSqlParts();
            expect($sql['sql'])->toContain('"name" LIKE ? OR "email" LIKE ?')
                ->and($sql['bindings'])->toBe(['A%', '%@example.com']);
        });

        it('generates not like and or not like clauses', function (): void {
            $sql = Knob::table('users')->whereNotLike('email', '%@example.test')->orWhereNotLike('name', 'Test%')->toSqlParts();
            expect($sql['sql'])->toContain('"email" NOT LIKE ? OR "name" NOT LIKE ?')
                ->and($sql['bindings'])->toBe(['%@example.test', 'Test%']);
        });

        it('generates column comparison clauses without bindings', function (): void {
            $sql = Knob::table('users')->whereColumn('created_at', '>', 'updated_at')->toSqlParts();
            expect($sql['sql'])->toContain('"created_at" > "updated_at"')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates column equality shorthand and or column comparison clauses', function (): void {
            $sql = Knob::table('users')->whereColumn('created_at', 'updated_at')->orWhereColumn('deleted_at', '<', 'updated_at')->toSqlParts();
            expect($sql['sql'])->toContain('"created_at" = "updated_at" OR "deleted_at" < "updated_at"')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates or between and or not between clauses', function (): void {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->orWhereBetween('age', [18, 30])
                ->orWhereNotBetween('score', [50, 80])
                ->toSqlParts();

            expect($sql['sql'])->toContain('"status" = ? OR "age" BETWEEN ? AND ? OR "score" NOT BETWEEN ? AND ?')
                ->and($sql['bindings'])->toBe(['active', 18, 30, 50, 80]);
        });

        it('normalizes associative BETWEEN values', function (): void {
            $sql = Knob::table('users')
                ->whereBetween('age', ['minimum' => 18, 'maximum' => 30])
                ->toSqlParts();

            expect($sql['sql'])->toContain('"age" BETWEEN ? AND ?')
                ->and($sql['bindings'])->toBe([18, 30]);
        });

        it('rejects BETWEEN predicates unless exactly two values are provided', function (string $method, array $values): void {
            $query = Knob::table('users')->where('status', 'active');
            $before = $query->toSqlParts();

            expect(fn () => $query->{$method}('age', $values))
                ->toThrow(InvalidArgumentException::class, 'BETWEEN requires exactly two values');
            expect($query->toSqlParts())->toBe($before);
        })->with([
            'whereBetween too few' => ['whereBetween', [18]],
            'whereBetween too many' => ['whereBetween', [18, 30, 40]],
            'orWhereBetween too few' => ['orWhereBetween', [18]],
            'orWhereBetween too many' => ['orWhereBetween', [18, 30, 40]],
            'whereNotBetween too few' => ['whereNotBetween', [18]],
            'whereNotBetween too many' => ['whereNotBetween', [18, 30, 40]],
            'orWhereNotBetween too few' => ['orWhereNotBetween', [18]],
            'orWhereNotBetween too many' => ['orWhereNotBetween', [18, 30, 40]],
        ]);

        it('generates common predicates inside grouped where clauses', function (): void {
            $sql = Knob::table('users')
                ->where(fn ($q) => $q->whereLike('name', 'A%')->orWhereColumn('created_at', '>', 'updated_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('("name" LIKE ? OR "created_at" > "updated_at")')
                ->and($sql['bindings'])->toBe(['A%']);
        });

        it('quotes identifiers in every structured where predicate', function (): void {
            $sql = Knob::table('users', 'u')
                ->where('u.order', 1)
                ->whereIn('u.status', ['active'])
                ->whereBetween('u.age', [18, 40])
                ->whereLike('u.name', 'J%')
                ->whereColumn('u.id', 'u.age')
                ->whereNull('u.email')
                ->whereSub('u.age', '>', Knob::query()->selectRaw('1'))
                ->whereDate('u.created_at', '2026-01-01')
                ->toSqlParts();

            expect($sql['sql'])->toBe('SELECT * FROM "users" AS "u" WHERE "u"."order" = ? AND "u"."status" IN (?) AND "u"."age" BETWEEN ? AND ? AND "u"."name" LIKE ? AND "u"."id" = "u"."age" AND "u"."email" IS NULL AND "u"."age" > (SELECT 1) AND DATE("u"."created_at") = ?')
                ->and($sql['bindings'])->toBe([1, 'active', 18, 40, 'J%', '2026-01-01']);
        });
    });

    describe('whereNull', function (): void {
        it('generates whereNull clause', function (): void {
            $sql = Knob::table('users')->whereNull('email')->toSqlParts();
            expect($sql['wheres'][0]['type'])->toBe('null')
                ->and($sql['wheres'][0]['column'])->toBe('email');
        });

        it('generates orWhereNull clause', function (): void {
            $sql = Knob::table('users')->where('status', 'active')->orWhereNull('email')->toSqlParts();
            expect($sql['sql'])->toContain('"status" = ? OR "email" IS NULL');
        });

        it('generates whereNotNull clause', function (): void {
            $sql = Knob::table('users')->whereNotNull('email')->toSqlParts();
            expect($sql['sql'])->toContain('"email" IS NOT NULL');
        });
    });

    describe('joins', function (): void {
        it('generates inner join', function (): void {
            $sql = Knob::table('users')->join('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'][0]['type'])->toBe('INNER JOIN');
        });

        it('generates inner join with table alias', function (): void {
            $sql = Knob::table('users', 'u')
                ->join('posts', 'u.id', '=', 'p.user_id', 'p')
                ->toSqlParts();

            expect($sql['joins'][0]['alias'])->toBe('p')
                ->and($sql['sql'])->toContain('FROM "users" AS "u" INNER JOIN "posts" AS "p" ON "u"."id" = "p"."user_id"');
        });

        it('generates left join', function (): void {
            $sql = Knob::table('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'])->toHaveCount(1)
                ->and($sql['joins'][0]['type'])->toBe('LEFT JOIN')
                ->and($sql['joins'][0]['table'])->toBe('posts');
        });

        it('generates right join', function (): void {
            $sql = Knob::table('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'][0]['type'])->toBe('RIGHT JOIN');
        });

        it('generates multi-condition callback join', function (): void {
            $sql = Knob::table('users')
                ->join('posts', fn ($join) => $join
                    ->on('users.id', '=', 'posts.user_id')
                    ->whereNull('posts.deleted_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('INNER JOIN "posts" ON "users"."id" = "posts"."user_id" AND "posts"."deleted_at" IS NULL')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates or join conditions', function (): void {
            $sql = Knob::table('users')
                ->join('contacts', fn ($join) => $join
                    ->on('users.id', '=', 'contacts.user_id')
                    ->orOn('users.email', '=', 'contacts.email'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('ON "users"."id" = "contacts"."user_id" OR "users"."email" = "contacts"."email"');
        });

        it('generates join value predicate bindings before where bindings', function (): void {
            $sql = Knob::table('users')
                ->leftJoin('memberships', fn ($join) => $join
                    ->on('users.id', '=', 'memberships.user_id')
                    ->where('memberships.active', true))
                ->where('users.status', 'active')
                ->toSqlParts();

            expect($sql['sql'])->toContain('LEFT JOIN "memberships" ON "users"."id" = "memberships"."user_id" AND "memberships"."active" = ?')
                ->and($sql['sql'])->toContain('WHERE "users"."status" = ?')
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('generates join not-null predicates', function (): void {
            $sql = Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', '=', 'profiles.user_id')
                    ->whereNotNull('profiles.verified_at')
                    ->orWhereNull('profiles.deleted_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('ON "users"."id" = "profiles"."user_id" AND "profiles"."verified_at" IS NOT NULL OR "profiles"."deleted_at" IS NULL');
        });

        it('generates join null predicates from null values', function (): void {
            $sql = Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', '=', 'profiles.user_id')
                    ->where('profiles.deleted_at', null)
                    ->orWhere('profiles.verified_at', '<>', null))
                ->toSqlParts();

            expect($sql['sql'])->toContain('"profiles"."deleted_at" IS NULL OR "profiles"."verified_at" IS NOT NULL')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates callback joinSub with subquery and join clause bindings', function (): void {
            $sql = Knob::table('users')
                ->joinSub(
                    fn ($q) => $q->from('posts')->select('user_id')->where('status', 'published'),
                    'p',
                    fn ($join) => $join
                        ->on('users.id', '=', 'p.user_id')
                        ->where('p.kind', 'article')
                )
                ->where('users.active', true)
                ->toSqlParts();

            expect($sql['sql'])->toContain('INNER JOIN (SELECT "user_id" FROM "posts" WHERE "status" = ?) AS "p" ON "users"."id" = "p"."user_id" AND "p"."kind" = ?')
                ->and($sql['bindings'])->toBe(['published', 'article', true]);
        });

        it('generates leftJoinSub with subquery and join clause bindings', function (): void {
            $sql = Knob::table('users')
                ->leftJoinSub(
                    fn ($q) => $q->from('posts')->select('user_id')->where('status', 'published'),
                    'p',
                    fn ($join) => $join
                        ->on('users.id', '=', 'p.user_id')
                        ->where('p.kind', 'article')
                )
                ->where('users.active', true)
                ->toSqlParts();

            expect($sql['sql'])->toContain('LEFT JOIN (SELECT "user_id" FROM "posts" WHERE "status" = ?) AS "p" ON "users"."id" = "p"."user_id" AND "p"."kind" = ?')
                ->and($sql['bindings'])->toBe(['published', 'article', true]);
        });

        it('generates cross join without on clause', function (): void {
            $sql = Knob::table('users')->crossJoin('posts')->toSqlParts();
            expect($sql['sql'])->toContain('CROSS JOIN "posts"')
                ->and($sql['sql'])->not->toContain(' ON ');
        });

        it('generates cross join with table alias', function (): void {
            $sql = Knob::table('users')->crossJoin('roles', 'r')->toSqlParts();
            expect($sql['sql'])->toContain('CROSS JOIN "roles" AS "r"')
                ->and($sql['sql'])->not->toContain(' ON ');
        });
    });

    describe('orderBy', function (): void {
        it('generates order by', function (): void {
            $sql = Knob::table('users')->orderBy('name', 'ASC')->toSqlParts();
            expect($sql['orders'])->toHaveCount(1)
                ->and($sql['orders'][0]['column'])->toBe('name')
                ->and($sql['orders'][0]['direction'])->toBe('ASC');
        });

        it('generates order by desc', function (): void {
            $sql = Knob::table('users')->orderByDesc('created_at')->toSqlParts();
            expect($sql['orders'][0]['direction'])->toBe('DESC');
        });

        it('generates latest order', function (): void {
            $sql = Knob::table('users')->latest()->toSqlParts();
            expect($sql['sql'])->toContain('ORDER BY "created_at" DESC');
        });

        it('generates oldest order', function (): void {
            $sql = Knob::table('users')->oldest('age')->toSqlParts();
            expect($sql['sql'])->toContain('ORDER BY "age" ASC');
        });
    });

    describe('limit and offset', function (): void {
        it('generates limit and offset', function (): void {
            $sql = Knob::table('users')->limit(10)->offset(20)->toSqlParts();
            expect($sql['limit'])->toBe(10)
                ->and($sql['offset'])->toBe(20);
        });
    });

    describe('groupBy', function (): void {
        it('generates group by', function (): void {
            $sql = Knob::table('posts')->groupBy('user_id')->toSqlParts();
            expect($sql['groups'])->toBe(['user_id']);
        });

        it('generates group by from array and having clauses', function (): void {
            $sql = Knob::table('posts')
                ->select('user_id')
                ->groupBy(['user_id', 'status'])
                ->having('count', '>', 1)
                ->havingRaw('SUM(score) > 10')
                ->toSqlParts();

            expect($sql['groups'])->toBe(['user_id', 'status'])
                ->and($sql['sql'])->toContain('GROUP BY "user_id", "status"')
                ->and($sql['sql'])->toContain('HAVING "count" > ? AND SUM(score) > 10')
                ->and($sql['bindings'])->toBe([1]);
        });

        it('supports raw group order and having bindings', function (): void {
            $sql = Knob::table('posts')
                ->selectRaw('COUNT(*)')
                ->where('status', 'published')
                ->groupByRaw('DATE(created_at) > ?', ['2026-01-01'])
                ->havingRaw('COUNT(*) > ?', [2])
                ->orderByRaw('MAX(score) > ?', [10])
                ->toSqlParts();

            expect($sql['sql'])->toContain('GROUP BY DATE(created_at) > ?')
                ->and($sql['sql'])->toContain('HAVING COUNT(*) > ?')
                ->and($sql['sql'])->toContain('ORDER BY MAX(score) > ?')
                ->and($sql['bindings'])->toBe(['published', '2026-01-01', 2, 10]);
        });
    });

    describe('execution', function (): void {
        it('gets all results', function (): void {
            $results = Knob::table('users')->get();
            expect($results)->toBeInstanceOf(\Knob\Collection::class)
                ->and($results->count())->toBe(2);
        });

        it('executes a SQLite offset without a limit', function (): void {
            $names = Knob::table('users')
                ->select('name')
                ->orderBy('id')
                ->offset(1)
                ->pluck('name')
                ->toArray();

            expect($names)->toBe(['Jane']);
        });

        it('executes structured clauses with reserved and qualified identifiers', function (): void {
            Knob::getConnection()->exec('CREATE TABLE reserved_words ("order" TEXT, "select" INTEGER)');
            Knob::table('reserved_words')->insert([
                ['order' => 'second', 'select' => 2],
                ['order' => 'first', 'select' => 1],
            ]);

            $rows = Knob::table('reserved_words', 'r')
                ->select('r.order AS sorted_order')
                ->where('r.select', '>', 0)
                ->orderBy('r.order')
                ->get()
                ->toArray();

            expect($rows)->toBe([
                ['sorted_order' => 'first'],
                ['sorted_order' => 'second'],
            ]);
        });

        it('streams results with cursor', function (): void {
            $names = [];

            foreach (Knob::table('users')->orderBy('id')->cursor() as $row) {
                $names[] = $row['name'];
            }

            expect($names)->toBe(['John', 'Jane']);
        });

        it('processes results in chunks', function (): void {
            $pages = [];
            $names = [];

            $completed = Knob::table('users')->orderBy('id')->chunk(1, function ($items, $page) use (&$pages, &$names): void {
                $pages[] = $page;
                $names[] = $items->first()['name'];
            });

            expect($completed)->toBeTrue()
                ->and($pages)->toBe([1, 2])
                ->and($names)->toBe(['John', 'Jane']);
        });

        it('can stop chunk processing early', function (): void {
            $pages = 0;

            $completed = Knob::table('users')->orderBy('id')->chunk(1, function () use (&$pages) {
                $pages++;

                return false;
            });

            expect($completed)->toBeFalse()
                ->and($pages)->toBe(1);
        });

        it('gets first result', function (): void {
            $result = Knob::table('users')->first();
            expect($result['name'])->toBe('John');
        });

        it('gets a scalar value', function (): void {
            $name = Knob::table('users')->where('email', 'jane@example.com')->value('name');
            $missing = Knob::table('users')->where('email', 'missing@example.com')->value('name');

            expect($name)->toBe('Jane')
                ->and($missing)->toBeNull();
        });

        it('gets qualified and explicitly aliased scalar values', function (): void {
            expect(Knob::table('users', 'u')->value('u.name'))->toBe('John')
                ->and(Knob::table('users')->value('name AS display_name'))->toBe('John');
        });

        it('reads a selected raw expression by its alias', function (): void {
            $value = Knob::table('users')
                ->selectRaw('UPPER(name) AS upper_name')
                ->value('upper_name');

            expect($value)->toBe('JOHN');
        });

        it('keeps false scalar values distinct from missing rows', function (): void {
            $pdo = Knob::getConnection();
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            $pdo->exec('CREATE TABLE flags (id INTEGER PRIMARY KEY, enabled BOOLEAN)');
            $pdo->exec('INSERT INTO flags (enabled) VALUES (false)');

            $enabled = Knob::table('flags')->value('enabled');
            $missing = Knob::table('flags')->where('id', 2)->value('enabled');

            expect((bool) $enabled)->toBeFalse()
                ->and($missing)->toBeNull();
        });

        it('counts records', function (): void {
            $count = Knob::table('users')->count();
            expect($count)->toBe(2);
        });

        it('checks exists', function (): void {
            $exists = Knob::table('users')->where('status', 'active')->exists();
            expect($exists)->toBe(true);
        });

        it('checks doesntExist', function (): void {
            expect(Knob::table('users')->where('status', 'archived')->doesntExist())->toBeTrue()
                ->and(Knob::table('users')->where('status', 'active')->doesntExist())->toBeFalse();
        });

        it('plucks values', function (): void {
            $names = Knob::table('users')->pluck('name')->toArray();
            expect($names)->toBe(['John', 'Jane']);
        });

        it('plucks keyed values', function (): void {
            $names = Knob::table('users')->pluck('name', 'id')->toArray();
            expect($names)->toBe([1 => 'John', 2 => 'Jane']);
        });

        it('plucks qualified columns and keys', function (): void {
            $names = Knob::table('users', 'u')->pluck('u.name', 'u.id')->toArray();

            expect($names)->toBe([1 => 'John', 2 => 'Jane']);
        });

        it('plucks an explicitly aliased column', function (): void {
            expect(Knob::table('users')->pluck('name AS display_name')->toArray())
                ->toBe(['John', 'Jane']);
        });

        it('plucks a selected raw expression by its alias', function (): void {
            $names = Knob::table('users')
                ->selectRaw('UPPER(name) AS upper_name')
                ->pluck('upper_name')
                ->toArray();

            expect($names)->toBe(['JOHN', 'JANE']);
        });

        it('plucks a selected raw expression with a structured key', function (): void {
            $names = Knob::table('users')
                ->selectRaw('UPPER(name) AS upper_name')
                ->orderBy('id')
                ->pluck('upper_name', 'id')
                ->toArray();

            expect($names)->toBe([1 => 'JOHN', 2 => 'JANE']);
        });

        it('plucks a structured value with a selected raw expression key', function (): void {
            $names = Knob::table('users')
                ->select('id')
                ->selectRaw('LOWER(name) AS name_key')
                ->pluck('id', 'name_key')
                ->toArray();

            expect($names)->toBe(['john' => 1, 'jane' => 2]);
        });

        it('gets distinct values', function (): void {
            $values = Knob::table('users')->distinct()->pluck('status')->toArray();
            expect($values)->toBe(['active']);
        });

        it('sums column values', function (): void {
            expect(Knob::table('users')->sum('age'))->toBe(55);
        });

        it('averages column values', function (): void {
            expect(Knob::table('users')->avg('age'))->toBe(27.5);
        });

        it('gets max column value', function (): void {
            expect(Knob::table('users')->max('age'))->toBe(30);
        });

        it('gets min column value', function (): void {
            expect(Knob::table('users')->min('age'))->toBe(25);
        });

        it('paginates results', function (): void {
            $page = Knob::table('users')->orderBy('id')->paginate(1, 2);

            expect($page['total'])->toBe(2)
                ->and($page['per_page'])->toBe(1)
                ->and($page['current_page'])->toBe(2)
                ->and($page['last_page'])->toBe(2)
                ->and($page['items'])->toHaveCount(1)
                ->and($page['items'][0]['name'])->toBe('Jane');
        });

        it('counts grouped rows when paginating', function (): void {
            Knob::table('users')->where('name', 'Jane')->update(['status' => 'pending']);

            $page = Knob::table('users')
                ->select('status')
                ->groupBy('status')
                ->orderBy('status')
                ->paginate(1, 1);

            expect($page['total'])->toBe(2);
        });

        it('counts rows after having filters when paginating', function (): void {
            Knob::table('users')->where('name', 'Jane')->update(['status' => 'pending']);

            $page = Knob::table('users')
                ->select('status')
                ->selectRaw('COUNT(*) AS user_count')
                ->groupBy('status')
                ->havingRaw('status != ?', ['blocked'])
                ->paginate(1, 1);

            expect($page['total'])->toBe(2);
        });

        it('counts distinct rows when paginating', function (): void {
            $page = Knob::table('users')->select('status')->distinct()->paginate();

            expect($page['total'])->toBe(1);
        });

        it('counts all union rows when paginating', function (): void {
            $page = Knob::table('users')
                ->select('name')
                ->where('name', 'John')
                ->unionAll(fn ($query) => $query->from('users')->select('name')->where('name', 'Jane'))
                ->paginate(1, 1);

            expect($page['total'])->toBe(2);
        });

        it('rejects invalid pagination parameters', function (int $perPage, int $page, string $message): void {
            expect(fn () => Knob::table('users')->paginate($perPage, $page))
                ->toThrow(InvalidArgumentException::class, $message);
        })->with([
            'zero page size' => [0, 1, 'Items per page must be at least 1'],
            'negative page size' => [-1, 1, 'Items per page must be at least 1'],
            'zero page' => [10, 0, 'Page must be at least 1'],
            'negative page' => [10, -1, 'Page must be at least 1'],
        ]);

        it('inserts a record', function (): void {
            $inserted = Knob::table('users')->insert([
                'name' => 'Alex',
                'email' => 'alex@example.com',
                'status' => 'pending',
                'age' => 19,
            ]);

            expect($inserted)->toBeTrue()
                ->and(Knob::table('users')->count())->toBe(3);
        });

        it('inserts or ignores duplicate records', function (): void {
            $ignored = Knob::table('users')->insertOrIgnore([
                'id' => 1,
                'name' => 'Ignored',
                'email' => 'ignored@example.com',
                'status' => 'pending',
                'age' => 40,
            ]);

            expect($ignored)->toBe(0)
                ->and(Knob::table('users')->where('id', 1)->first()['name'])->toBe('John');
        });

        it('upserts records', function (): void {
            Knob::table('users')->upsert([
                [
                    'id' => 1,
                    'name' => 'Updated John',
                    'email' => 'john@example.com',
                    'status' => 'active',
                    'age' => 26,
                ],
                [
                    'id' => 3,
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                    'status' => 'pending',
                    'age' => 35,
                ],
            ], 'id', ['name', 'age']);

            expect(Knob::table('users')->where('id', 1)->first()['name'])->toBe('Updated John')
                ->and(Knob::table('users')->where('id', 1)->first()['age'])->toBe(26)
                ->and(Knob::table('users')->where('id', 3)->first()['name'])->toBe('Alice');
        });

        it('inserts a record and returns its id', function (): void {
            $id = Knob::table('users')->insertGetId([
                'name' => 'Mia',
                'email' => 'mia@example.com',
                'status' => 'active',
                'age' => 28,
            ]);

            expect($id)->not->toBeFalse()
                ->and((int)$id)->toBeGreaterThan(0);
        });

        it('updates matching records', function (): void {
            $updated = Knob::table('users')->where('name', 'John')->update([
                'status' => 'inactive',
            ]);

            expect($updated)->toBe(1)
                ->and(Knob::table('users')->where('status', 'inactive')->count())->toBe(1);
        });

        it('rejects empty updates', function (): void {
            expect(fn () => Knob::table('users')->where('id', 1)->update([]))
                ->toThrow(InvalidArgumentException::class, 'Update values cannot be empty');
        });

        it('blocks full table updates unless explicitly allowed', function (): void {
            expect(fn () => Knob::table('users')->update(['status' => 'inactive']))
                ->toThrow(RuntimeException::class, 'Full table update requires allowFullTable()');
            expect(Knob::table('users')->where('status', 'inactive')->count())->toBe(0);

            expect(Knob::table('users')->allowFullTable()->update(['status' => 'inactive']))->toBe(2);
        });

        it('deletes matching records', function (): void {
            $deleted = Knob::table('users')->where('name', 'Jane')->delete();

            expect($deleted)->toBe(1)
                ->and(Knob::table('users')->count())->toBe(1);
        });

        it('blocks full table deletes unless explicitly allowed', function (): void {
            expect(fn () => Knob::table('users')->delete())
                ->toThrow(RuntimeException::class, 'Full table delete requires allowFullTable()');
            expect(Knob::table('users')->count())->toBe(2);

            expect(Knob::table('users')->allowFullTable()->delete())->toBe(2);
        });

        it('truncates a SQLite table and preserves its autoincrement sequence', function (): void {
            Knob::getConnection()->exec('CREATE TABLE truncatable (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
            Knob::table('truncatable')->insert([['name' => 'First'], ['name' => 'Second']]);

            expect(Knob::table('truncatable')->truncate())->toBeTrue()
                ->and(Knob::table('truncatable')->count())->toBe(0);

            $id = Knob::table('truncatable')->insertGetId([
                'name' => 'Next',
            ]);

            expect($id)->toBe('3');
        });
    });

    describe('toSql', function (): void {
        it('returns interpolated sql string', function (): void {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->where('age', '>', 18)
                ->toSql();

            expect($sql)->toContain('"status" = \'active\'')
                ->and($sql)->toContain('"age" > 18')
                ->and($sql)->not->toContain('?');
        });

        it('interpolates booleans and nulls', function (): void {
            $sql = Knob::table('users')
                ->whereRaw('status = ?', [null])
                ->orWhereRaw('is_admin = ?', [true])
                ->toSql();

            expect($sql)->toContain('status = NULL')
                ->and($sql)->toContain('is_admin = 1');
        });
    });

    describe('sub queries and bindings', function (): void {
        it('supports selectSub with closure-defined subquery', function (): void {
            $sql = Knob::table('users')
                ->select('name')
                ->selectSub(
                    fn ($q) => $q->from('posts')->selectRaw('COUNT(*)')->where('published', true)->whereRaw('posts.user_id = users.id'),
                    'published_posts'
                )
                ->where('status', 'active')
                ->toSqlParts();

            expect($sql['sql'])->toContain(
                '(SELECT COUNT(*) FROM "posts" WHERE "published" = ? AND posts.user_id = users.id) AS "published_posts"'
            )
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('supports selectSub with reusable builder subquery', function (): void {
            $subquery = Knob::query()
                ->from('posts')
                ->selectRaw('COUNT(*)')
                ->where('published', true)
                ->whereRaw('posts.user_id = users.id');

            $sql = Knob::table('users')
                ->select('name')
                ->selectSub($subquery, 'published_posts')
                ->where('status', 'active')
                ->toSqlParts();

            expect($sql['sql'])->toContain(
                '(SELECT COUNT(*) FROM "posts" WHERE "published" = ? AND posts.user_id = users.id) AS "published_posts"'
            )
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('passes bindings through fromSub', function (): void {
            $sql = Knob::table('users')
                ->fromSub(fn ($q) => $q->from('users')->where('status', 'active'), 'u')
                ->where('age', '>', 20)
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 20]);
        });

        it('passes bindings through joinSub', function (): void {
            $sql = Knob::table('users')
                ->joinSub(
                    fn ($q) => $q->from('users')->where('status', 'active'),
                    'u2',
                    'users.id',
                    '=',
                    'u2.id'
                )
                ->where('users.age', '>', 20)
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 20]);
        });

        it('supports whereIn with subquery input', function (): void {
            $sql = Knob::table('posts')
                ->whereIn('user_id', fn ($q) => $q->select('id')->from('users')->where('status', 'active'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('"user_id" IN (SELECT "id" FROM "users" WHERE "status" = ?)')
                ->and($sql['bindings'])->toBe(['active']);
        });

        it('supports whereNotIn with reusable builder input', function (): void {
            $subquery = Knob::query()
                ->select('id')
                ->from('users')
                ->where('status', 'inactive');

            $sql = Knob::table('posts')
                ->whereNotIn('user_id', $subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain('"user_id" NOT IN (SELECT "id" FROM "users" WHERE "status" = ?)')
                ->and($sql['bindings'])->toBe(['inactive']);
        });

        it('supports whereSub with reusable builder input', function (): void {
            $subquery = Knob::query()
                ->selectRaw('MAX(score)')
                ->from('scores')
                ->where('scores.user_id', 10);

            $sql = Knob::table('users')
                ->whereSub('score', '>=', $subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain('"score" >= (SELECT MAX(score) FROM "scores" WHERE "scores"."user_id" = ?)')
                ->and($sql['bindings'])->toBe([10]);
        });

        it('supports orWhereSub with reusable builder input', function (): void {
            $subquery = Knob::query()
                ->selectRaw('MAX(score)')
                ->from('scores')
                ->where('scores.user_id', 10);

            $sql = Knob::table('users')
                ->where('status', 'active')
                ->orWhereSub('score', '>=', $subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain('"status" = ? OR "score" >= (SELECT MAX(score) FROM "scores" WHERE "scores"."user_id" = ?)')
                ->and($sql['bindings'])->toBe(['active', 10]);
        });

        it('supports whereExists with reusable builder input', function (): void {
            $subquery = Knob::query()
                ->from('posts')
                ->whereRaw('posts.user_id = users.id')
                ->where('published', true);

            $sql = Knob::table('users')
                ->whereExists($subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain(
                'EXISTS (SELECT * FROM "posts" WHERE posts.user_id = users.id AND "published" = ?)'
            )
                ->and($sql['bindings'])->toBe([true]);
        });

        it('supports orWhereExists and orWhereNotExists', function (): void {
            $exists = Knob::query()
                ->from('posts')
                ->whereRaw('posts.user_id = users.id')
                ->where('published', true);

            $notExists = Knob::query()
                ->from('bans')
                ->whereRaw('bans.user_id = users.id')
                ->where('active', true);

            $sql = Knob::table('users')
                ->where('status', 'active')
                ->orWhereExists($exists)
                ->orWhereNotExists($notExists)
                ->toSqlParts();

            expect($sql['sql'])->toContain('"status" = ? OR EXISTS (SELECT * FROM "posts" WHERE posts.user_id = users.id AND "published" = ?) OR NOT EXISTS (SELECT * FROM "bans" WHERE bans.user_id = users.id AND "active" = ?)')
                ->and($sql['bindings'])->toBe(['active', true, true]);
        });

        it('passes bindings through union', function (): void {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->union(fn ($q) => $q->from('users')->where('status', 'pending'))
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('passes bindings through unionAll', function (): void {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->unionAll(fn ($q) => $q->from('users')->where('status', 'pending'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('UNION ALL SELECT * FROM "users" WHERE "status" = ?')
                ->and($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('supports unionAll with reusable builder input', function (): void {
            $union = Knob::query()
                ->from('users')
                ->where('status', 'pending');

            $sql = Knob::table('users')
                ->where('status', 'active')
                ->unionAll($union)
                ->toSqlParts();

            expect($sql['sql'])->toContain('UNION ALL SELECT * FROM "users" WHERE "status" = ?')
                ->and($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('orders union bindings before compound order bindings', function (): void {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->unionAll(fn ($query) => $query->from('users')->where('status', 'pending'))
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['special'])
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 'pending', 'special']);
        });

        it('keeps union branch ordering and pagination local to that branch', function (): void {
            $rows = Knob::table('users')
                ->select('name')
                ->where('name', 'John')
                ->unionAll(fn ($query) => $query
                    ->from('users')
                    ->select('name')
                    ->orderBy('name')
                    ->limit(1))
                ->get()
                ->toArray();

            expect($rows)->toBe([
                ['name' => 'John'],
                ['name' => 'Jane'],
            ]);
        });

        it('omits meaningless ordering from an unpaginated union branch', function (): void {
            $sql = Knob::table('users')
                ->select('name')
                ->unionAll(fn ($query) => $query
                    ->from('users')
                    ->select('name')
                    ->orderBy('name'))
                ->toSqlParts()['sql'];

            expect($sql)->not->toContain('ORDER BY');
        });

        it('executes ordering and pagination on the complete SQLite union', function (): void {
            $rows = Knob::table('users')
                ->select('name')
                ->where('name', 'John')
                ->unionAll(fn ($query) => $query
                    ->from('users')
                    ->select('name')
                    ->where('name', 'Jane'))
                ->orderBy('name')
                ->limit(1)
                ->get()
                ->toArray();

            expect($rows)->toBe([['name' => 'Jane']]);
        });

        it('preserves binding order across select from join and where subqueries', function (): void {
            $sql = Knob::table('users')
                ->selectSub(
                    fn ($q) => $q->from('posts')->selectRaw('COUNT(*)')->where('published', true)->whereRaw('posts.user_id = users.id'),
                    'post_count'
                )
                ->fromSub(fn ($q) => $q->from('users')->where('status', 'active'), 'u')
                ->joinSub(
                    fn ($q) => $q->from('profiles')->where('verified', true),
                    'p',
                    'u.id',
                    '=',
                    'p.user_id'
                )
                ->whereExists(fn ($q) => $q->from('orders')->whereRaw('orders.user_id = u.id')->where('state', 'paid'))
                ->toSqlParts();

            expect($sql['bindings'])->toBe([true, 'active', true, 'paid']);
        });

        it('does not accumulate bindings across repeated builder snapshots', function (): void {
            $subquery = Knob::query()
                ->select('id')
                ->from('users')
                ->where('status', 'active');

            expect($subquery->toSqlParts()['bindings'])->toBe(['active'])
                ->and($subquery->toSqlParts()['bindings'])->toBe(['active']);
        });

        it('preserves complex subquery bindings across repeated snapshots and execution', function (): void {
            Knob::table('users')->insert([
                ['name' => 'Alex', 'email' => 'alex@example.com', 'status' => 'pending', 'age' => 22],
                ['name' => 'Sam', 'email' => 'sam@example.com', 'status' => 'pending', 'age' => 40],
            ]);

            $pdo = Knob::getConnection();
            $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, published INTEGER, score INTEGER)');
            Knob::table('posts')->insert([
                ['user_id' => 1, 'title' => 'John A', 'published' => 1, 'score' => 6],
                ['user_id' => 1, 'title' => 'John B', 'published' => 1, 'score' => 7],
                ['user_id' => 2, 'title' => 'Jane A', 'published' => 1, 'score' => 8],
                ['user_id' => 3, 'title' => 'Alex A', 'published' => 1, 'score' => 20],
                ['user_id' => 4, 'title' => 'Sam A', 'published' => 1, 'score' => 30],
            ]);

            $query = Knob::table('users', 'u')
                ->select('u.name')
                ->selectSub(
                    fn ($q) => $q
                        ->from('posts')
                        ->selectRaw('COUNT(*)')
                        ->whereRaw('posts.user_id = u.id')
                        ->where('published', 1),
                    'published_posts'
                )
                ->joinSub(
                    fn ($q) => $q
                        ->from('posts')
                        ->selectRaw('user_id, SUM(score) AS total_score')
                        ->where('published', 1)
                        ->groupBy('user_id')
                        ->havingRaw('SUM(score) > 10'),
                    'post_scores',
                    'u.id',
                    '=',
                    'post_scores.user_id'
                )
                ->where(
                    fn ($q) => $q
                    ->where('u.status', 'active')
                    ->orWhere(
                        fn ($r) => $r
                        ->where('u.status', 'pending')
                        ->whereBetween('u.age', [20, 30])
                    )
                )
                ->whereIn(
                    'u.id',
                    fn ($q) => $q
                    ->select('user_id')
                    ->from('posts')
                    ->where('published', 1)
                    ->whereNotIn('score', [])
                )
                ->orderBy('u.name');

            $firstSnapshot = $query->toSqlParts();
            $secondSnapshot = $query->toSqlParts();

            expect($firstSnapshot['sql'])->toContain(
                '(SELECT COUNT(*) FROM "posts" WHERE posts.user_id = u.id AND "published" = ?) AS "published_posts"'
            )
                ->and($firstSnapshot['sql'])->toContain(
                    'INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM "posts" WHERE "published" = ? GROUP BY "user_id" HAVING SUM(score) > 10) AS "post_scores" ON "u"."id" = "post_scores"."user_id"'
                )
                ->and($firstSnapshot['sql'])->toContain('("u"."status" = ? OR ("u"."status" = ? AND "u"."age" BETWEEN ? AND ?))')
                ->and($firstSnapshot['sql'])->toContain(
                    '"u"."id" IN (SELECT "user_id" FROM "posts" WHERE "published" = ? AND 1 = 1)'
                )
                ->and($firstSnapshot['bindings'])->toBe([1, 1, 'active', 'pending', 20, 30, 1])
                ->and($secondSnapshot['bindings'])->toBe($firstSnapshot['bindings'])
                ->and($query->get()->toArray())->toBe([
                    ['name' => 'Alex', 'published_posts' => 1],
                    ['name' => 'John', 'published_posts' => 2],
                ]);
        });
    });

    describe('whereIn edge cases', function (): void {
        it('compiles empty whereIn as always false', function (): void {
            $sql = Knob::table('users')->whereIn('id', [])->toSqlParts();
            expect($sql['sql'])->toContain('0 = 1');
        });

        it('compiles empty whereNotIn as always true', function (): void {
            $sql = Knob::table('users')->whereNotIn('id', [])->toSqlParts();
            expect($sql['sql'])->toContain('1 = 1');
        });
    });

    describe('other builder methods', function (): void {
        it('generates whereNotBetween clause', function (): void {
            $sql = Knob::table('users')->whereNotBetween('age', [18, 21])->toSqlParts();
            expect($sql['sql'])->toContain('"age" NOT BETWEEN ? AND ?')
                ->and($sql['bindings'])->toBe([18, 21]);
        });

        it('generates whereRaw and orWhereRaw clauses', function (): void {
            $sql = Knob::table('users')
                ->whereRaw('status = ?', ['active'])
                ->orWhereRaw('age > ?', [18])
                ->toSqlParts();

            expect($sql['sql'])->toContain('status = ? OR age > ?')
                ->and($sql['bindings'])->toBe(['active', 18]);
        });

        it('executes whereRaw after SQL introspection without duplicate bindings', function (): void {
            $query = Knob::table('users')
                ->select(['id', 'name'])
                ->whereRaw('id > ? AND id < ?', ['0', '2']);

            expect($query->toSqlParts()['bindings'])->toBe(['0', '2'])
                ->and($query->get()->toArray())->toBe([
                    ['id' => 1, 'name' => 'John'],
                ]);
        });

        it('supports whereNotExists clauses', function (): void {
            $sql = Knob::table('users')
                ->whereNotExists(fn ($q) => $q->from('posts')->whereRaw('posts.user_id = users.id'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('NOT EXISTS (SELECT * FROM "posts" WHERE posts.user_id = users.id)');
        });

        it('throws for write operations without a table', function (string $operation, string $message): void {
            expect(fn () => match ($operation) {
                'update' => Knob::query()->update(['name' => 'NoTable']),
                'delete' => Knob::query()->delete(),
                'truncate' => Knob::query()->truncate(),
            })->toThrow(RuntimeException::class, $message);
        })->with([
            'update' => ['update', 'Table not set for update'],
            'delete' => ['delete', 'Table not set for delete'],
            'truncate' => ['truncate', 'Table not set for truncate'],
        ]);

        it('preserves full table write permission when cloning', function (): void {
            expect(Knob::table('users')->allowFullTable()->clone()->delete())->toBe(2);
        });

        it('throws when inserting without a table', function (): void {
            expect(fn () => Knob::query()->insert(['name' => 'NoTable']))->toThrow(RuntimeException::class, 'Table not set for insert');
        });

        it('throws when inserting empty values', function (): void {
            expect(fn () => Knob::table('users')->insert([]))->toThrow(RuntimeException::class, 'Insert values cannot be empty');
        });

        it('throws when inserting rows with inconsistent columns', function (): void {
            expect(fn () => Knob::table('users')->insert([
                ['name' => 'A', 'email' => 'a@example.com'],
                ['name' => 'B'],
            ]))->toThrow(RuntimeException::class, 'Insert rows must have the same columns');
        });

        it('clones builder state independently', function (): void {
            $original = Knob::table('users')->where('status', 'active')->orderBy('name');
            $clone = $original->clone()->where('age', '>', 20);

            expect($original->toSqlParts()['sql'])->not->toContain('"age" > ?')
                ->and($clone->toSqlParts()['sql'])->toContain('"age" > ?');
        });

        it('keeps terminal reads from mutating the builder state', function (): void {
            $query = Knob::table('users')->where('status', 'active');

            $query->count();
            $afterCount = $query->toSqlParts();

            $query->first();
            $afterFirst = $query->toSqlParts();

            $query->pluck('name');
            $afterPluck = $query->toSqlParts();

            $query->exists();
            $afterExists = $query->toSqlParts();

            expect($afterCount['columns'])->toBe(['*'])
                ->and($afterCount['limit'])->toBeNull()
                ->and($afterFirst['columns'])->toBe(['*'])
                ->and($afterFirst['limit'])->toBeNull()
                ->and($afterPluck['columns'])->toBe(['*'])
                ->and($afterExists['columns'])->toBe(['*']);
        });
    });

    describe('SQL operator validation', function (): void {
        it('normalizes operators at every Builder convergence point', function (): void {
            $subquery = Knob::query()->selectRaw('1');

            $parts = Knob::table('users')
                ->join('posts', 'users.id', ' = ', 'posts.user_id')
                ->joinSub($subquery, 'p', 'users.id', ' <> ', 'p.user_id')
                ->where('name', ' like ', 'J%')
                ->orWhereColumn('age', ' >= ', 'id')
                ->whereSub('age', ' < ', $subquery)
                ->orWhereDate('id', ' > ', 0)
                ->having('id', ' iLiKe ', '%')
                ->toSqlParts();

            expect($parts['joins'][0]['clauses'][0]['operator'])->toBe('=')
                ->and($parts['joins'][1]['clauses'][0]['operator'])->toBe('<>')
                ->and($parts['wheres'][0]['operator'])->toBe('LIKE')
                ->and($parts['wheres'][1]['operator'])->toBe('>=')
                ->and($parts['wheres'][2]['operator'])->toBe('<')
                ->and($parts['wheres'][3]['operator'])->toBe('>')
                ->and($parts['havings'][0]['operator'])->toBe('ILIKE')
                ->and($parts['sql'])->toContain('"name" LIKE ?')
                ->and($parts['sql'])->toContain('HAVING "id" ILIKE ?');
        });

        it('rejects malicious operators without changing Builder state', function (Closure $apply): void {
            $builder = Knob::table('users')->where('status', 'active');
            $before = $builder->toSqlParts();

            expect(fn () => $apply($builder))
                ->toThrow(InvalidArgumentException::class, '= ? OR 1=1 --');

            expect($builder->toSqlParts())->toBe($before);
        })->with([
            'where' => [fn ($query) => $query->where('age', '= ? OR 1=1 --', 18)],
            'having' => [fn ($query) => $query->having('age', '= ? OR 1=1 --', 18)],
            'simple join' => [fn ($query) => $query->join('posts', 'users.id', '= ? OR 1=1 --', 'posts.user_id')],
            'whereColumn' => [fn ($query) => $query->whereColumn('users.id', '= ? OR 1=1 --', 'users.age')],
            'whereSub' => [fn ($query) => $query->whereSub('age', '= ? OR 1=1 --', Knob::query()->selectRaw('1'))],
            'date where' => [fn ($query) => $query->whereDate('id', '= ? OR 1=1 --', 1)],
            'joinSub' => [fn ($query) => $query->joinSub(Knob::query()->selectRaw('1'), 'p', 'users.id', '= ? OR 1=1 --', 'p.id')],
        ]);

        it('rejects invalid subquery operators before invoking callbacks', function (): void {
            $callbackCalls = 0;

            expect(fn () => Knob::table('users')->whereSub(
                'age',
                '= ? OR 1=1 --',
                function ($query) use (&$callbackCalls): void {
                    $callbackCalls++;
                    $query->selectRaw('1');
                }
            ))->toThrow(InvalidArgumentException::class);

            expect(fn () => Knob::table('users')->joinSub(
                function ($query) use (&$callbackCalls): void {
                    $callbackCalls++;
                    $query->selectRaw('1');
                },
                'p',
                'users.id',
                '= ? OR 1=1 --',
                'p.id'
            ))->toThrow(InvalidArgumentException::class);

            expect($callbackCalls)->toBe(0);
        });

        it('preserves callback execution order for callback joinSub clauses', function (): void {
            $calls = [];

            Knob::table('users')->joinSub(
                function ($query) use (&$calls): void {
                    $calls[] = 'subquery';
                    $query->selectRaw('1');
                },
                'p',
                function ($join) use (&$calls): void {
                    $calls[] = 'join';
                    $join->on('users.id', '=', 'p.id');
                }
            );

            expect($calls)->toBe(['subquery', 'join']);
        });

        it('preserves shorthand and null comparison behavior after normalization', function (): void {
            $parts = Knob::table('users')
                ->where('age', 18)
                ->orWhere('email', ' = ', null)
                ->orWhere('email', ' != ', null)
                ->orWhere('email', ' <> ', null)
                ->orWhere('email', ' like ', null)
                ->toSqlParts();

            expect($parts['sql'])->toContain('"age" = ? OR "email" IS NULL OR "email" IS NOT NULL OR "email" IS NOT NULL OR "email" LIKE ?')
                ->and($parts['bindings'])->toBe([18, null])
                ->and($parts['wheres'][4]['operator'])->toBe('LIKE');
        });

        it('normalizes callback join operators and preserves null conversion', function (): void {
            $parts = Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', ' = ', 'profiles.user_id')
                    ->orOn('users.email', ' iLiKe ', 'profiles.email')
                    ->where('profiles.kind', ' like ', 'public%')
                    ->orWhere('profiles.deleted_at', ' <> ', null))
                ->toSqlParts();

            expect($parts['joins'][0]['clauses'][0]['operator'])->toBe('=')
                ->and($parts['joins'][0]['clauses'][1]['operator'])->toBe('ILIKE')
                ->and($parts['joins'][0]['clauses'][2]['operator'])->toBe('LIKE')
                ->and($parts['joins'][0]['clauses'][3]['type'])->toBe('null')
                ->and($parts['joins'][0]['clauses'][3]['not'])->toBeTrue()
                ->and($parts['sql'])->toContain('"profiles"."kind" LIKE ? OR "profiles"."deleted_at" IS NOT NULL');
        });

        it('rejects malicious callback join operators without changing Builder state', function (Closure $apply): void {
            $builder = Knob::table('users')->where('status', 'active');
            $before = $builder->toSqlParts();

            expect(fn () => $builder->join('profiles', fn ($join) => $apply($join)))
                ->toThrow(InvalidArgumentException::class, '= ? OR 1=1 --');

            expect($builder->toSqlParts())->toBe($before);
        })->with([
            'on clause' => [fn ($join) => $join->on('users.id', '= ? OR 1=1 --', 'profiles.user_id')],
            'value clause' => [fn ($join) => $join->where('profiles.kind', '= ? OR 1=1 --', 'private')],
        ]);

        it('blocks a malicious callback join operator before SQLite execution', function (): void {
            Knob::getConnection()->exec('CREATE TABLE profiles (id INTEGER PRIMARY KEY, user_id INTEGER, kind TEXT)');
            Knob::getConnection()->exec("INSERT INTO profiles (user_id, kind) VALUES (1, 'private'), (2, 'private')");

            expect(fn () => Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', '=', 'profiles.user_id')
                    ->where('profiles.kind', '= ? OR 1=1 --', 'missing'))
                ->get())
                ->toThrow(InvalidArgumentException::class, '= ? OR 1=1 --');
        });
    });
});
