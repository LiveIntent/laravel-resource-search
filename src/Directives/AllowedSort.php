<?php

namespace LiveIntent\LaravelResourceSearch\Directives;

use LiveIntent\LaravelResourceSearch\Concerns\Aliasable;

/** @phpstan-consistent-constructor */
class AllowedSort
{
    use Aliasable;

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null)
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
    }

    /**
     * Create a new allowed scope.
     */
    public static function field(string $name, ?string $internalName = null)
    {
        return new static($name, $internalName);
    }
}
