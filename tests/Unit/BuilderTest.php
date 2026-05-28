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
            $sql = Knob::table('users')->toSqlParts();
            expect($sql['columns'])->toBe(['*']);
        });

        it('generates select with columns', function () {
            $sql = Knob::table('users')->select('name', 'email')->toSqlParts();
            expect($sql['columns'])->toBe(['name', 'email']);
        });

        it('generates distinct select', function () {
            $sql = Knob::table('users')->distinct()->select('status')->toSqlParts();
            expect($sql['distinct'])->toBeTrue()
                ->and($sql['sql'])->toContain('SELECT DISTINCT "status" FROM "users"');
        });

        it('generates selectRaw without implicit wildcard', function () {
            $sql = Knob::table('users')->selectRaw('COUNT(*)')->toSqlParts();
            expect($sql['columns'])->toBe(['COUNT(*)'])
                ->and($sql['sql'])->toContain('SELECT COUNT(*) FROM "users"');
        });

        it('supports raw string selectSub', function () {
            $sql = Knob::table('users')->selectSub('SELECT 1', 'one')->toSqlParts();
            expect($sql['sql'])->toContain('(SELECT 1) AS "one"');
        });
    });

    describe('where', function () {
        it('generates basic where clause', function () {
            $sql = Knob::table('users')->where('status', 'active')->toSqlParts();
            expect($sql['wheres'])->toHaveCount(1)
                ->and($sql['wheres'][0]['type'])->toBe('basic')
                ->and($sql['wheres'][0]['column'])->toBe('status')
                ->and($sql['wheres'][0]['operator'])->toBe('=')
                ->and($sql['wheres'][0]['value'])->toBe('active');
        });

        it('generates where with operator', function () {
            $sql = Knob::table('users')->where('age', '>', 18)->toSqlParts();
            expect($sql['wheres'][0]['operator'])->toBe('>')
                ->and($sql['wheres'][0]['value'])->toBe(18);
        });

        it('generates or where', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSqlParts();
            expect($sql['wheres'])->toHaveCount(2)
                ->and($sql['wheres'][0]['boolean'])->toBe('AND')
                ->and($sql['wheres'][1]['boolean'])->toBe('OR');
        });

        it('compiles or where with OR connector', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSqlParts();
            expect($sql['sql'])->toContain('status = ? OR status = ?');
        });

        it('compiles null values as null predicates', function () {
            $sql = Knob::table('users')
                ->where('status', null)
                ->orWhere('email', '!=', null)
                ->toSqlParts();

            expect($sql['sql'])->toContain('status IS NULL OR email IS NOT NULL')
                ->and($sql['bindings'])->toBe([]);
        });
    });

    describe('whereGroup', function () {
        it('generates basic nested AND group', function () {
            $sql = Knob::table('users')->where('status', 'active')->where(fn ($q) => $q->where('type', 'A')->orWhere('type', 'B'))->toSqlParts();
            expect($sql['sql'])->toContain('status = ?')
                ->and($sql['sql'])->toContain('(type = ? OR type = ?)');
        });

        it('generates nested group with AND conditions inside', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->where('b', 2))->toSqlParts();
            expect($sql['sql'])->toContain('(a = ? AND b = ?)');
        });

        it('generates multiple nested groups at same level', function () {
            $sql = Knob::table('users')
                ->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))
                ->where(fn ($q) => $q->where('c', 3)->orWhere('d', 4))
                ->toSqlParts();
            expect($sql['sql'])->toContain('(a = ? OR b = ?)')
                ->and($sql['sql'])->toContain('(c = ? OR d = ?)');
        });

        it('generates deeply nested groups (2 levels)', function () {
            $sql = Knob::table('users')
                ->where('x', 1)
                ->where(fn ($q) => $q->where(fn ($r) => $r->where('a', 'A')->orWhere('b', 'B'))->where('y', 2))
                ->toSqlParts();
            expect($sql['sql'])->toContain('x = ?')
                ->and($sql['sql'])->toContain('((a = ? OR b = ?) AND y = ?)');
        });

        it('preserves bindings order across groups', function () {
            $sql = Knob::table('users')
                ->where('a', 1)
                ->where(fn ($q) => $q->where('b', 2)->orWhere('c', 3))
                ->where('d', 4)
                ->toSqlParts();
            expect($sql['bindings'])->toBe([1, 2, 3, 4]);
        });

        it('handles whereIn inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereIn('id', [1, 2, 3]))->toSqlParts();
            expect($sql['sql'])->toContain('(id IN (?, ?, ?))')
                ->and($sql['bindings'])->toBe([1, 2, 3]);
        });

        it('handles whereBetween inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereBetween('age', [18, 30]))->toSqlParts();
            expect($sql['sql'])->toContain('(age BETWEEN ? AND ?)')
                ->and($sql['bindings'])->toBe([18, 30]);
        });

        it('handles whereNull / whereNotNull inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereNull('deleted_at')->orWhereNotNull('active'))->toSqlParts();
            expect($sql['sql'])->toContain('(deleted_at IS NULL OR active IS NOT NULL)');
        });

        it('handles whereExists inside group', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->whereExists(fn ($sub) => $sub->from('posts', 'p')->whereRaw('p.user_id = users.id')))->toSqlParts();
            expect($sql['sql'])->toContain('(EXISTS');
        });

        it('handles group at top level with no outer conditions', function () {
            $sql = Knob::table('users')->where(fn ($q) => $q->where('a', 1)->orWhere('b', 2))->toSqlParts();
            expect($sql['sql'])->toContain('(a = ? OR b = ?)');
        });
    });

    describe('whereIn', function () {
        it('generates whereIn clause', function () {
            $sql = Knob::table('users')->whereIn('id', [1, 2, 3])->toSqlParts();
            expect($sql['wheres'][0]['type'])->toBe('in')
                ->and($sql['wheres'][0]['values'])->toBe([1, 2, 3]);
        });

        it('generates orWhereIn clause', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhereIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('status = ? OR id IN (?, ?)')
                ->and($sql['bindings'])->toBe(['active', 1, 2]);
        });

        it('generates whereNotIn clause', function () {
            $sql = Knob::table('users')->whereNotIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('id NOT IN (?, ?)')
                ->and($sql['bindings'])->toBe([1, 2]);
        });

        it('generates orWhereNotIn clause', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhereNotIn('id', [1, 2])->toSqlParts();
            expect($sql['sql'])->toContain('status = ? OR id NOT IN (?, ?)')
                ->and($sql['bindings'])->toBe(['active', 1, 2]);
        });

        it('generates orWhereNotIn with subquery input', function () {
            $subquery = Knob::query()->select('id')->from('users')->where('status', 'inactive');
            $sql = Knob::table('posts')->where('published', true)->orWhereNotIn('user_id', $subquery)->toSqlParts();

            expect($sql['sql'])->toContain(
                'published = ? OR user_id NOT IN (SELECT "id" FROM "users" WHERE status = ?)'
            )
                ->and($sql['bindings'])->toBe([true, 'inactive']);
        });
    });

    describe('common where predicates', function () {
        it('generates like and or like clauses', function () {
            $sql = Knob::table('users')->whereLike('name', 'A%')->orWhereLike('email', '%@example.com')->toSqlParts();
            expect($sql['sql'])->toContain('name LIKE ? OR email LIKE ?')
                ->and($sql['bindings'])->toBe(['A%', '%@example.com']);
        });

        it('generates not like and or not like clauses', function () {
            $sql = Knob::table('users')->whereNotLike('email', '%@example.test')->orWhereNotLike('name', 'Test%')->toSqlParts();
            expect($sql['sql'])->toContain('email NOT LIKE ? OR name NOT LIKE ?')
                ->and($sql['bindings'])->toBe(['%@example.test', 'Test%']);
        });

        it('generates column comparison clauses without bindings', function () {
            $sql = Knob::table('users')->whereColumn('created_at', '>', 'updated_at')->toSqlParts();
            expect($sql['sql'])->toContain('created_at > updated_at')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates column equality shorthand and or column comparison clauses', function () {
            $sql = Knob::table('users')->whereColumn('created_at', 'updated_at')->orWhereColumn('deleted_at', '<', 'updated_at')->toSqlParts();
            expect($sql['sql'])->toContain('created_at = updated_at OR deleted_at < updated_at')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates or between and or not between clauses', function () {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->orWhereBetween('age', [18, 30])
                ->orWhereNotBetween('score', [50, 80])
                ->toSqlParts();

            expect($sql['sql'])->toContain('status = ? OR age BETWEEN ? AND ? OR score NOT BETWEEN ? AND ?')
                ->and($sql['bindings'])->toBe(['active', 18, 30, 50, 80]);
        });

        it('generates common predicates inside grouped where clauses', function () {
            $sql = Knob::table('users')
                ->where(fn ($q) => $q->whereLike('name', 'A%')->orWhereColumn('created_at', '>', 'updated_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('(name LIKE ? OR created_at > updated_at)')
                ->and($sql['bindings'])->toBe(['A%']);
        });
    });

    describe('whereNull', function () {
        it('generates whereNull clause', function () {
            $sql = Knob::table('users')->whereNull('email')->toSqlParts();
            expect($sql['wheres'][0]['type'])->toBe('null')
                ->and($sql['wheres'][0]['column'])->toBe('email');
        });

        it('generates orWhereNull clause', function () {
            $sql = Knob::table('users')->where('status', 'active')->orWhereNull('email')->toSqlParts();
            expect($sql['sql'])->toContain('status = ? OR email IS NULL');
        });

        it('generates whereNotNull clause', function () {
            $sql = Knob::table('users')->whereNotNull('email')->toSqlParts();
            expect($sql['sql'])->toContain('email IS NOT NULL');
        });
    });

    describe('joins', function () {
        it('generates inner join', function () {
            $sql = Knob::table('users')->join('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'][0]['type'])->toBe('INNER JOIN');
        });

        it('generates inner join with table alias', function () {
            $sql = Knob::table('users', 'u')
                ->join('posts', 'u.id', '=', 'p.user_id', 'p')
                ->toSqlParts();

            expect($sql['joins'][0]['alias'])->toBe('p')
                ->and($sql['sql'])->toContain('FROM "users" AS "u" INNER JOIN "posts" AS "p" ON u.id = p.user_id');
        });

        it('generates left join', function () {
            $sql = Knob::table('users')->leftJoin('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'])->toHaveCount(1)
                ->and($sql['joins'][0]['type'])->toBe('LEFT JOIN')
                ->and($sql['joins'][0]['table'])->toBe('posts');
        });

        it('generates right join', function () {
            $sql = Knob::table('users')->rightJoin('posts', 'users.id', '=', 'posts.user_id')->toSqlParts();
            expect($sql['joins'][0]['type'])->toBe('RIGHT JOIN');
        });

        it('generates multi-condition callback join', function () {
            $sql = Knob::table('users')
                ->join('posts', fn ($join) => $join
                    ->on('users.id', '=', 'posts.user_id')
                    ->whereNull('posts.deleted_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('INNER JOIN "posts" ON users.id = posts.user_id AND posts.deleted_at IS NULL')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates or join conditions', function () {
            $sql = Knob::table('users')
                ->join('contacts', fn ($join) => $join
                    ->on('users.id', '=', 'contacts.user_id')
                    ->orOn('users.email', '=', 'contacts.email'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('ON users.id = contacts.user_id OR users.email = contacts.email');
        });

        it('generates join value predicate bindings before where bindings', function () {
            $sql = Knob::table('users')
                ->leftJoin('memberships', fn ($join) => $join
                    ->on('users.id', '=', 'memberships.user_id')
                    ->where('memberships.active', true))
                ->where('users.status', 'active')
                ->toSqlParts();

            expect($sql['sql'])->toContain('LEFT JOIN "memberships" ON users.id = memberships.user_id AND memberships.active = ?')
                ->and($sql['sql'])->toContain('WHERE users.status = ?')
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('generates join not-null predicates', function () {
            $sql = Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', '=', 'profiles.user_id')
                    ->whereNotNull('profiles.verified_at')
                    ->orWhereNull('profiles.deleted_at'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('ON users.id = profiles.user_id AND profiles.verified_at IS NOT NULL OR profiles.deleted_at IS NULL');
        });

        it('generates join null predicates from null values', function () {
            $sql = Knob::table('users')
                ->join('profiles', fn ($join) => $join
                    ->on('users.id', '=', 'profiles.user_id')
                    ->where('profiles.deleted_at', null)
                    ->orWhere('profiles.verified_at', '<>', null))
                ->toSqlParts();

            expect($sql['sql'])->toContain('profiles.deleted_at IS NULL OR profiles.verified_at IS NOT NULL')
                ->and($sql['bindings'])->toBe([]);
        });

        it('generates callback joinSub with subquery and join clause bindings', function () {
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

            expect($sql['sql'])->toContain('INNER JOIN (SELECT "user_id" FROM "posts" WHERE status = ?) AS "p" ON users.id = p.user_id AND p.kind = ?')
                ->and($sql['bindings'])->toBe(['published', 'article', true]);
        });

        it('generates cross join without on clause', function () {
            $sql = Knob::table('users')->crossJoin('posts')->toSqlParts();
            expect($sql['sql'])->toContain('CROSS JOIN "posts"')
                ->and($sql['sql'])->not->toContain(' ON ');
        });

        it('generates cross join with table alias', function () {
            $sql = Knob::table('users')->crossJoin('roles', 'r')->toSqlParts();
            expect($sql['sql'])->toContain('CROSS JOIN "roles" AS "r"')
                ->and($sql['sql'])->not->toContain(' ON ');
        });
    });

    describe('orderBy', function () {
        it('generates order by', function () {
            $sql = Knob::table('users')->orderBy('name', 'ASC')->toSqlParts();
            expect($sql['orders'])->toHaveCount(1)
                ->and($sql['orders'][0]['column'])->toBe('name')
                ->and($sql['orders'][0]['direction'])->toBe('ASC');
        });

        it('generates order by desc', function () {
            $sql = Knob::table('users')->orderByDesc('created_at')->toSqlParts();
            expect($sql['orders'][0]['direction'])->toBe('DESC');
        });

        it('generates latest order', function () {
            $sql = Knob::table('users')->latest()->toSqlParts();
            expect($sql['sql'])->toContain('ORDER BY created_at DESC');
        });

        it('generates oldest order', function () {
            $sql = Knob::table('users')->oldest('age')->toSqlParts();
            expect($sql['sql'])->toContain('ORDER BY age ASC');
        });
    });

    describe('limit and offset', function () {
        it('generates limit and offset', function () {
            $sql = Knob::table('users')->limit(10)->offset(20)->toSqlParts();
            expect($sql['limit'])->toBe(10)
                ->and($sql['offset'])->toBe(20);
        });
    });

    describe('groupBy', function () {
        it('generates group by', function () {
            $sql = Knob::table('posts')->groupBy('user_id')->toSqlParts();
            expect($sql['groups'])->toBe(['user_id']);
        });

        it('generates group by from array and having clauses', function () {
            $sql = Knob::table('posts')
                ->select('user_id')
                ->groupBy(['user_id', 'status'])
                ->having('count', '>', 1)
                ->havingRaw('SUM(score) > 10')
                ->toSqlParts();

            expect($sql['groups'])->toBe(['user_id', 'status'])
                ->and($sql['sql'])->toContain('GROUP BY "user_id", "status"')
                ->and($sql['sql'])->toContain('HAVING count > ? AND SUM(score) > 10')
                ->and($sql['bindings'])->toBe([1]);
        });
    });

    describe('execution', function () {
        it('gets all results', function () {
            $results = Knob::table('users')->get();
            expect($results)->toBeInstanceOf(\Knob\Collection::class)
                ->and($results->count())->toBe(2);
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

        it('plucks keyed values', function () {
            $names = Knob::table('users')->pluck('name', 'id')->toArray();
            expect($names)->toBe([1 => 'John', 2 => 'Jane']);
        });

        it('gets distinct values', function () {
            $values = Knob::table('users')->distinct()->pluck('status')->toArray();
            expect($values)->toBe(['active']);
        });

        it('sums column values', function () {
            expect(Knob::table('users')->sum('age'))->toBe(55);
        });

        it('averages column values', function () {
            expect(Knob::table('users')->avg('age'))->toBe(27.5);
        });

        it('gets max column value', function () {
            expect(Knob::table('users')->max('age'))->toBe(30);
        });

        it('gets min column value', function () {
            expect(Knob::table('users')->min('age'))->toBe(25);
        });

        it('paginates results', function () {
            $page = Knob::table('users')->orderBy('id')->paginate(1, 2);

            expect($page['total'])->toBe(2)
                ->and($page['per_page'])->toBe(1)
                ->and($page['current_page'])->toBe(2)
                ->and($page['last_page'])->toBe(2)
                ->and($page['items'])->toHaveCount(1)
                ->and($page['items'][0]['name'])->toBe('Jane');
        });

        it('inserts a record', function () {
            $inserted = Knob::table('users')->insert([
                'name' => 'Alex',
                'email' => 'alex@example.com',
                'status' => 'pending',
                'age' => 19,
            ]);

            expect($inserted)->toBeTrue()
                ->and(Knob::table('users')->count())->toBe(3);
        });

        it('inserts a record and returns its id', function () {
            $id = Knob::table('users')->insertGetId([
                'name' => 'Mia',
                'email' => 'mia@example.com',
                'status' => 'active',
                'age' => 28,
            ]);

            expect($id)->not->toBeFalse()
                ->and((int)$id)->toBeGreaterThan(0);
        });

        it('updates matching records', function () {
            $updated = Knob::table('users')->where('name', 'John')->update([
                'status' => 'inactive',
            ]);

            expect($updated)->toBe(1)
                ->and(Knob::table('users')->where('status', 'inactive')->count())->toBe(1);
        });

        it('deletes matching records', function () {
            $deleted = Knob::table('users')->where('name', 'Jane')->delete();

            expect($deleted)->toBe(1)
                ->and(Knob::table('users')->count())->toBe(1);
        });
    });

    describe('toSql', function () {
        it('returns interpolated sql string', function () {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->where('age', '>', 18)
                ->toSql();

            expect($sql)->toContain("status = 'active'")
                ->and($sql)->toContain('age > 18')
                ->and($sql)->not->toContain('?');
        });

        it('interpolates booleans and nulls', function () {
            $sql = Knob::table('users')
                ->whereRaw('status = ?', [null])
                ->orWhereRaw('is_admin = ?', [true])
                ->toSql();

            expect($sql)->toContain('status = NULL')
                ->and($sql)->toContain('is_admin = 1');
        });
    });

    describe('sub queries and bindings', function () {
        it('supports selectSub with closure-defined subquery', function () {
            $sql = Knob::table('users')
                ->select('name')
                ->selectSub(
                    fn ($q) => $q->from('posts')->selectRaw('COUNT(*)')->where('published', true)->whereRaw('posts.user_id = users.id'),
                    'published_posts'
                )
                ->where('status', 'active')
                ->toSqlParts();

            expect($sql['sql'])->toContain(
                '(SELECT COUNT(*) FROM "posts" WHERE published = ? AND posts.user_id = users.id) AS "published_posts"'
            )
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('supports selectSub with reusable builder subquery', function () {
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
                '(SELECT COUNT(*) FROM "posts" WHERE published = ? AND posts.user_id = users.id) AS "published_posts"'
            )
                ->and($sql['bindings'])->toBe([true, 'active']);
        });

        it('passes bindings through fromSub', function () {
            $sql = Knob::table('users')
                ->fromSub(fn ($q) => $q->from('users')->where('status', 'active'), 'u')
                ->where('age', '>', 20)
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 20]);
        });

        it('passes bindings through joinSub', function () {
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

        it('supports whereIn with subquery input', function () {
            $sql = Knob::table('posts')
                ->whereIn('user_id', fn ($q) => $q->select('id')->from('users')->where('status', 'active'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('user_id IN (SELECT "id" FROM "users" WHERE status = ?)')
                ->and($sql['bindings'])->toBe(['active']);
        });

        it('supports whereNotIn with reusable builder input', function () {
            $subquery = Knob::query()
                ->select('id')
                ->from('users')
                ->where('status', 'inactive');

            $sql = Knob::table('posts')
                ->whereNotIn('user_id', $subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain('user_id NOT IN (SELECT "id" FROM "users" WHERE status = ?)')
                ->and($sql['bindings'])->toBe(['inactive']);
        });

        it('supports whereSub with reusable builder input', function () {
            $subquery = Knob::query()
                ->selectRaw('MAX(score)')
                ->from('scores')
                ->where('scores.user_id', 10);

            $sql = Knob::table('users')
                ->whereSub('score', '>=', $subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain('score >= (SELECT MAX(score) FROM "scores" WHERE scores.user_id = ?)')
                ->and($sql['bindings'])->toBe([10]);
        });

        it('supports whereExists with reusable builder input', function () {
            $subquery = Knob::query()
                ->from('posts')
                ->whereRaw('posts.user_id = users.id')
                ->where('published', true);

            $sql = Knob::table('users')
                ->whereExists($subquery)
                ->toSqlParts();

            expect($sql['sql'])->toContain(
                'EXISTS (SELECT * FROM "posts" WHERE posts.user_id = users.id AND published = ?)'
            )
                ->and($sql['bindings'])->toBe([true]);
        });

        it('passes bindings through union', function () {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->union(fn ($q) => $q->from('users')->where('status', 'pending'))
                ->toSqlParts();

            expect($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('passes bindings through unionAll', function () {
            $sql = Knob::table('users')
                ->where('status', 'active')
                ->unionAll(fn ($q) => $q->from('users')->where('status', 'pending'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('UNION ALL SELECT * FROM "users" WHERE status = ?')
                ->and($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('supports unionAll with reusable builder input', function () {
            $union = Knob::query()
                ->from('users')
                ->where('status', 'pending');

            $sql = Knob::table('users')
                ->where('status', 'active')
                ->unionAll($union)
                ->toSqlParts();

            expect($sql['sql'])->toContain('UNION ALL SELECT * FROM "users" WHERE status = ?')
                ->and($sql['bindings'])->toBe(['active', 'pending']);
        });

        it('preserves binding order across select from join and where subqueries', function () {
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

        it('does not accumulate bindings across repeated builder snapshots', function () {
            $subquery = Knob::query()
                ->select('id')
                ->from('users')
                ->where('status', 'active');

            expect($subquery->toSqlParts()['bindings'])->toBe(['active'])
                ->and($subquery->toSqlParts()['bindings'])->toBe(['active']);
        });

        it('preserves complex subquery bindings across repeated snapshots and execution', function () {
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
                '(SELECT COUNT(*) FROM "posts" WHERE posts.user_id = u.id AND published = ?) AS "published_posts"'
            )
                ->and($firstSnapshot['sql'])->toContain(
                    'INNER JOIN (SELECT user_id, SUM(score) AS total_score FROM "posts" WHERE published = ? GROUP BY "user_id" HAVING SUM(score) > 10) AS "post_scores" ON u.id = post_scores.user_id'
                )
                ->and($firstSnapshot['sql'])->toContain('(u.status = ? OR (u.status = ? AND u.age BETWEEN ? AND ?))')
                ->and($firstSnapshot['sql'])->toContain(
                    'u.id IN (SELECT "user_id" FROM "posts" WHERE published = ? AND 1 = 1)'
                )
                ->and($firstSnapshot['bindings'])->toBe([1, 1, 'active', 'pending', 20, 30, 1])
                ->and($secondSnapshot['bindings'])->toBe($firstSnapshot['bindings'])
                ->and($query->get()->toArray())->toBe([
                    ['name' => 'Alex', 'published_posts' => 1],
                    ['name' => 'John', 'published_posts' => 2],
                ]);
        });
    });

    describe('whereIn edge cases', function () {
        it('compiles empty whereIn as always false', function () {
            $sql = Knob::table('users')->whereIn('id', [])->toSqlParts();
            expect($sql['sql'])->toContain('0 = 1');
        });

        it('compiles empty whereNotIn as always true', function () {
            $sql = Knob::table('users')->whereNotIn('id', [])->toSqlParts();
            expect($sql['sql'])->toContain('1 = 1');
        });
    });

    describe('other builder methods', function () {
        it('generates whereNotBetween clause', function () {
            $sql = Knob::table('users')->whereNotBetween('age', [18, 21])->toSqlParts();
            expect($sql['sql'])->toContain('age NOT BETWEEN ? AND ?')
                ->and($sql['bindings'])->toBe([18, 21]);
        });

        it('generates whereRaw and orWhereRaw clauses', function () {
            $sql = Knob::table('users')
                ->whereRaw('status = ?', ['active'])
                ->orWhereRaw('age > ?', [18])
                ->toSqlParts();

            expect($sql['sql'])->toContain('status = ? OR age > ?')
                ->and($sql['bindings'])->toBe(['active', 18]);
        });

        it('executes whereRaw after SQL introspection without duplicate bindings', function () {
            $query = Knob::table('users')
                ->select(['id', 'name'])
                ->whereRaw('id > ? AND id < ?', ['0', '2']);

            expect($query->toSqlParts()['bindings'])->toBe(['0', '2'])
                ->and($query->get()->toArray())->toBe([
                    ['id' => 1, 'name' => 'John'],
                ]);
        });

        it('supports whereNotExists clauses', function () {
            $sql = Knob::table('users')
                ->whereNotExists(fn ($q) => $q->from('posts')->whereRaw('posts.user_id = users.id'))
                ->toSqlParts();

            expect($sql['sql'])->toContain('NOT EXISTS (SELECT * FROM "posts" WHERE posts.user_id = users.id)');
        });

        it('returns false when truncating without a table', function () {
            expect(Knob::query()->truncate())->toBeFalse();
        });

        it('throws when inserting without a table', function () {
            expect(fn () => Knob::query()->insert(['name' => 'NoTable']))->toThrow(RuntimeException::class, 'Table not set for insert');
        });

        it('throws when inserting empty values', function () {
            expect(fn () => Knob::table('users')->insert([]))->toThrow(RuntimeException::class, 'Insert values cannot be empty');
        });

        it('throws when inserting rows with inconsistent columns', function () {
            expect(fn () => Knob::table('users')->insert([
                ['name' => 'A', 'email' => 'a@example.com'],
                ['name' => 'B'],
            ]))->toThrow(RuntimeException::class, 'Insert rows must have the same columns');
        });

        it('clones builder state independently', function () {
            $original = Knob::table('users')->where('status', 'active')->orderBy('name');
            $clone = $original->clone()->where('age', '>', 20);

            expect($original->toSqlParts()['sql'])->not->toContain('age > ?')
                ->and($clone->toSqlParts()['sql'])->toContain('age > ?');
        });

        it('keeps terminal reads from mutating the builder state', function () {
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
});
