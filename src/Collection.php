<?php

namespace Knob;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    private array $items = [];
    private array $operations = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getIterator(): Traversable
    {
        $items = $this->items;
        foreach ($this->operations as $operation) {
            if ($operation['type'] === 'map') {
                $items = array_map($operation['callback'], $items);
            } elseif ($operation['type'] === 'filter') {
                $items = array_filter($items, $operation['callback']);
            }
        }
        return new ArrayIterator($items);
    }

    public function count(): int
    {
        return count(iterator_to_array($this->getIterator()));
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
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

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function toJson(int $jsonFlag = 0): string
    {
        return json_encode($this->toArray(), $jsonFlag);
    }
}
