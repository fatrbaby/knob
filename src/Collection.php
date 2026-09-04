<?php

namespace Knob;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @phpstan-consistent-constructor
 * @implements IteratorAggregate<array-key, mixed>
 * @implements ArrayAccess<array-key, mixed>
 */
class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var list<array{type: 'map'|'filter', callback: callable(mixed): mixed}> */
    private array $operations = [];

    /** @param array<array-key, mixed> $items */
    public static function from(array $items): Collection
    {
        return new static($items);
    }

    /** @param array<array-key, mixed> $items */
    public function __construct(private array $items = [])
    {
    }

    public function getIterator(): Traversable
    {
        return $this->iterateItems();
    }

    public function count(): int
    {
        if ($this->operations === []) {
            return count($this->items);
        }

        $count = 0;

        foreach ($this->iterateItems() as $_) {
            $count++;
        }

        return $count;
    }

    public function offsetExists(mixed $offset): bool
    {
        if ($this->operations === []) {
            return isset($this->items[$offset]);
        }

        $offset = $this->normalizeOffset($offset);

        foreach ($this->iterateItems() as $key => $item) {
            if ($key === $offset) {
                return $item !== null;
            }
        }

        return false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($this->operations === []) {
            return $this->items[$offset] ?? null;
        }

        $offset = $this->normalizeOffset($offset);

        foreach ($this->iterateItems() as $key => $item) {
            if ($key === $offset) {
                return $item;
            }
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function map(callable $fn): Collection
    {
        $result = new Collection($this->items);
        $result->operations = $this->operations;
        $result->operations[] = ['type' => 'map', 'callback' => $fn];

        return $result;
    }

    public function filter(callable $fn): Collection
    {
        $result = new Collection($this->items);
        $result->operations = $this->operations;
        $result->operations[] = ['type' => 'filter', 'callback' => $fn];

        return $result;
    }

    public function first(): mixed
    {
        foreach ($this as $item) {
            return $item;
        }

        return null;
    }

    public function last(): mixed
    {
        $last = null;

        foreach ($this as $item) {
            $last = $item;
        }

        return $last;
    }

    /** @return array<array-key, mixed> */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function toJson(int $jsonFlag = 0): string
    {
        return json_encode($this->toArray(), $jsonFlag | JSON_THROW_ON_ERROR);
    }

    /** @return Traversable<array-key, mixed> */
    private function iterateItems(): Traversable
    {
        foreach ($this->items as $key => $item) {
            foreach ($this->operations as $operation) {
                if ($operation['type'] === 'map') {
                    $item = $operation['callback']($item);
                } elseif (!$operation['callback']($item)) {
                    continue 2;
                }
            }

            yield $key => $item;
        }
    }

    private function normalizeOffset(mixed $offset): int|string
    {
        return array_key_first([$offset => null]);
    }
}
