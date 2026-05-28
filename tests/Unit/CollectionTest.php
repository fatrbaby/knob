<?php

use Knob\Collection;

describe('Collection', function () {
    beforeEach(function () {
        $this->collection = new Collection([
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
        ]);
    });

    describe('Countable', function () {
        it('returns count of items', function () {
            expect(count($this->collection))->toBe(3);
        });
    });

    describe('ArrayAccess', function () {
        it('allows offset access', function () {
            expect($this->collection[0]['name'])->toBe('John')
                ->and($this->collection[1]['name'])->toBe('Jane');
        });

        it('returns null for non-existent offset', function () {
            expect($this->collection[99])->toBeNull();
        });

        it('allows setting offset', function () {
            $this->collection[3] = ['name' => 'Alice', 'age' => 35];
            expect($this->collection[3]['name'])->toBe('Alice');
        });

        it('reads offsets from mapped items', function () {
            $mapped = $this->collection->map(fn ($item) => $item['name']);

            expect($mapped[0])->toBe('John')
                ->and($mapped[1])->toBe('Jane');
        });

        it('checks offset existence from filtered items', function () {
            $filtered = $this->collection->filter(fn ($item) => $item['age'] > 25);

            expect(isset($filtered[0]))->toBeFalse()
                ->and(isset($filtered[1]))->toBeTrue()
                ->and($filtered[0])->toBeNull()
                ->and($filtered[1]['name'])->toBe('Jane');
        });

        it('follows isset semantics for null mapped values', function () {
            $mapped = $this->collection->map(fn () => null);

            expect(isset($mapped[0]))->toBeFalse()
                ->and($mapped[0])->toBeNull();
        });
    });

    describe('IteratorAggregate', function () {
        it('iterates over items', function () {
            $names = [];

            foreach ($this->collection as $item) {
                $names[] = $item['name'];
            }
            expect($names)->toBe(['John', 'Jane', 'Bob']);
        });
    });

    describe('map', function () {
        it('returns new collection with mapped items', function () {
            $mapped = $this->collection->map(fn ($item) => $item['name']);
            expect($mapped)->toBeInstanceOf(Collection::class)
                ->and($mapped->toArray())->toBe(['John', 'Jane', 'Bob']);
        });

        it('does not execute until iteration', function () {
            $called = false;
            $mapped = $this->collection->map(function ($item) use (&$called) {
                $called = true;

                return $item['name'];
            });
            expect($called)->toBeFalse();
            $mapped->toArray();
            expect($called)->toBeTrue();
        });

        it('chains with filter', function () {
            $result = $this->collection
                ->map(fn ($item) => $item['name'])
                ->filter(fn ($name) => str_starts_with($name, 'J'))
                ->toArray();
            expect($result)->toBe(['John', 'Jane']);
        });
    });

    describe('filter', function () {
        it('returns new collection with filtered items', function () {
            $filtered = $this->collection->filter(fn ($item) => $item['age'] > 25);
            expect($filtered->count())->toBe(1)
                ->and($filtered->first()['name'])->toBe('Jane');
        });

        it('returns false items as filtered out', function () {
            $filtered = $this->collection->filter(fn ($item) => $item['age'] > 25);
            expect($filtered->toArray())->not->toContain('John');
        });
    });

    describe('first', function () {
        it('returns first item', function () {
            expect($this->collection->first()['name'])->toBe('John');
        });

        it('stops evaluating after the first matching item', function () {
            $calls = 0;

            $first = $this->collection
                ->filter(function ($item) use (&$calls) {
                    $calls++;

                    return $item['age'] > 20;
                })
                ->map(function ($item) use (&$calls) {
                    $calls++;

                    return $item['name'];
                })
                ->first();

            expect($first)->toBe('John')
                ->and($calls)->toBe(2);
        });

        it('returns null for empty collection', function () {
            $empty = new Collection([]);
            expect($empty->first())->toBeNull();
        });
    });

    describe('last', function () {
        it('returns last item', function () {
            expect($this->collection->last()['name'])->toBe('Bob');
        });

        it('returns null for empty collection', function () {
            $empty = new Collection([]);
            expect($empty->last())->toBeNull();
        });
    });

    describe('toArray', function () {
        it('returns plain array', function () {
            $array = $this->collection->toArray();
            expect(is_array($array))->toBeTrue()
                ->and(count($array))->toBe(3);
        });
    });

    describe('toJson', function () {
        it('returns JSON string', function () {
            $json = $this->collection->toJson();
            expect(is_string($json))->toBeTrue();
            $decoded = json_decode($json, true);
            expect(count($decoded))->toBe(3);
        });
    });
});
