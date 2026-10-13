<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\FilterStateInterface;

/**
 * Immutable filter state.
 */
class FilterState implements FilterStateInterface
{
    /** @var array<string, string[]> */
    private array $values = [];

    /**
     * @param array<string, string[]> $values
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $key => $list) {
            $list = array_values(array_unique(array_map('strval', $list)));
            if ($list !== []) {
                $this->values[(string) $key] = $list;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): array
    {
        return $this->values[$key] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return array_sum(array_map('count', $this->values));
    }

    /**
     * @inheritDoc
     */
    public function with(string $key, array $values): FilterStateInterface
    {
        $all = $this->values;
        $all[$key] = $values;

        return new self($all);
    }

    /**
     * @inheritDoc
     */
    public function without(string $key): FilterStateInterface
    {
        $all = $this->values;
        unset($all[$key]);

        return new self($all);
    }
}
