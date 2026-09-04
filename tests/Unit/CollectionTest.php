<?php

use Knob\Collection;

function collectionFixture(): Collection
{
    return new Collection([
        ['name' => 'John', 'age' => 25],
        ['name' => 'Jane', 'age' => 30],
        ['name' => 'Bob', 'age' => 20],
    ]);
}

describe('Collection', function (): void {
    describe('Countable', function (): void {
        it('returns count of items', function (): void {
            expect(count(collectionFixture()))->toBe(3);
        });
    });

    describe('ArrayAccess', function (): void {
        it('allows offset access', function (): void {
            $collection = collectionFixture();

            expect($collection[0]['name'])->toBe('John')
                ->and($collection[1]['name'])->toBe('Jane');
        });

        it('returns null for non-existent offset', function (): void {
            expect(collectionFixture()[99])->toBeNull();
        });

        it('allows setting offset', function (): void {
            $collection = collectionFixture();
            $collection[3] = ['name' => 'Alice', 'age' => 35];

            expect($collection[3]['name'])->toBe('Alice');
        });

        it('reads offsets from mapped items', function (): void {
            $mapped = collectionFixture()->map(fn ($item) => $item['name']);

            expect($mapped[0])->toBe('John')
                ->and($mapped[1])->toBe('Jane');
        });

        it('checks offset existence from filtered items', function (): void {
            $filtered = collectionFixture()->filter(fn ($item) => $item['age'] > 25);

            expect(isset($filtered[0]))->toBeFalse()
                ->and(isset($filtered[1]))->toBeTrue()
                ->and($filtered[0])->toBeNull()
                ->and($filtered[1]['name'])->toBe('Jane');
        });

        it('follows isset semantics for null mapped values', function (): void {
            $mapped = collectionFixture()->map(fn () => null);

            expect(isset($mapped[0]))->toBeFalse()
                ->and($mapped[0])->toBeNull();
        });
    });

    describe('IteratorAggregate', function (): void {
        it('iterates over items', function (): void {
            $names = [];

            foreach (collectionFixture() as $item) {
                $names[] = $item['name'];
            }
            expect($names)->toBe(['John', 'Jane', 'Bob']);
        });
    });

    describe('map', function (): void {
        it('returns new collection with mapped items', function (): void {
            $mapped = collectionFixture()->map(fn ($item) => $item['name']);
            expect($mapped)->toBeInstanceOf(Collection::class)
                ->and($mapped->toArray())->toBe(['John', 'Jane', 'Bob']);
        });

        it('does not execute until iteration', function (): void {
            $called = false;
            $mapped = collectionFixture()->map(function ($item) use (&$called) {
                $called = true;

                return $item['name'];
            });
            expect($called)->toBeFalse();
            $mapped->toArray();
            expect($called)->toBeTrue();
        });

        it('chains with filter', function (): void {
            $result = collectionFixture()
                ->map(fn ($item) => $item['name'])
                ->filter(fn ($name) => str_starts_with($name, 'J'))
                ->toArray();
            expect($result)->toBe(['John', 'Jane']);
        });
    });

    describe('filter', function (): void {
        it('returns new collection with filtered items', function (): void {
            $filtered = collectionFixture()->filter(fn ($item) => $item['age'] > 25);
            expect($filtered->count())->toBe(1)
                ->and($filtered->first()['name'])->toBe('Jane');
        });

        it('returns false items as filtered out', function (): void {
            $filtered = collectionFixture()->filter(fn ($item) => $item['age'] > 25);
            expect($filtered->toArray())->not->toContain('John');
        });
    });

    describe('first', function (): void {
        it('returns first item', function (): void {
            expect(collectionFixture()->first()['name'])->toBe('John');
        });

        it('stops evaluating after the first matching item', function (): void {
            $calls = 0;

            $first = collectionFixture()
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

        it('returns null for empty collection', function (): void {
            $empty = new Collection([]);
            expect($empty->first())->toBeNull();
        });
    });

    describe('last', function (): void {
        it('returns last item', function (): void {
            expect(collectionFixture()->last()['name'])->toBe('Bob');
        });

        it('returns null for empty collection', function (): void {
            $empty = new Collection([]);
            expect($empty->last())->toBeNull();
        });
    });

    describe('toArray', function (): void {
        it('returns plain array', function (): void {
            $array = collectionFixture()->toArray();
            expect($array)->toHaveCount(3);
        });
    });

    describe('toJson', function (): void {
        it('returns JSON string', function (): void {
            $json = collectionFixture()->toJson();
            expect(json_decode($json, true))->toHaveCount(3);
        });

        it('throws a JSON exception when an item cannot be encoded', function (): void {
            $collection = new Collection(["\xB1\x31"]);

            expect(fn () => $collection->toJson())->toThrow(JsonException::class);
        });
    });
});
