<?php

namespace Arris\Template;

use ArrayIterator;
use Traversable;

class Config implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    protected $options = [];

    public function __construct($options = [])
    {
        $this->options = $this->getArrayItems($options);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->options);
    }

    /**
     * @param int|string $offset
     * @return bool
     * #[\ReturnTypeWillChange]
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param int|string $offset
     * @return mixed
     * #[\ReturnTypeWillChange]
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->options[] = $value;
            return;
        }
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->delete($offset);
    }

    public function count($key = null): int
    {
        return count($this->get($key));
    }

    /**
     * @return array
     * #[\ReturnTypeWillChange]
     */
    public function jsonSerialize()
    {
        return $this->options;
    }

    /**
     * Return all the stored items
     *
     * @return array
     */
    public function all(): array
    {
        return $this->options;
    }

    /**
     * Return the given items as an array
     *
     * @param $items
     * @return array|mixed
     */
    protected function getArrayItems($items)
    {
        if (is_array($items)) {
            return $items;
        }

        if ($items instanceof self) {
            return $items->all();
        }

        return (array) $items;
    }

    /**
     * Check if a given key or keys exists
     *
     * @param int|string $offset
     * @return bool
     */
    public function has($offset): bool
    {
        return array_key_exists($offset, $this->options);
    }

    protected function exists($array, $key): bool
    {
        return array_key_exists($key, $array);
    }

    /**
     * Return the value of a given key
     *
     * @param $key
     * @param $default
     * @return array|mixed
     */
    public function get($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->all();
        }

        if ($this->exists($this->options, $key)) {
            return $this->options[$key];
        }

        return $this->options;
    }

    /**
     * Set a given key / value pair or pairs
     *
     * @param $keys
     * @param $value
     * @return $this
     */
    public function set($keys, $value = null)
    {
        if (is_array($keys)) {
            foreach ($keys as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        $this->options = $value;
        return $this;
    }

    /**
     * Delete the given key or keys
     *
     * @param $keys
     * @return $this
     */
    public function delete($keys)
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if ($this->exists($this->options, $key)) {
                unset($this->options[$key]);
            }
        }

        return $this;
    }
}