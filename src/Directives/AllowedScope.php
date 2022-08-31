<?php

namespace LiveIntent\LaravelResourceSearch\Directives;

use LiveIntent\LaravelResourceSearch\Concerns\Aliasable;

/** @phpstan-consistent-constructor */
class AllowedScope
{
    use Aliasable;

    /**
     * The optional arguments to pass when calling the scope.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null, ?array $args = [])
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
        $this->args = $args ?? [];
    }

    /**
     * Get the args that should be used when calling the scope.
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Add arguments that should be used when calling the scope.
     */
    public function withArgs(array $args)
    {
        $this->args = array_merge($this->args, $args);

        return $this;
    }

    /**
     * Create a new allowed scope.
     */
    public static function name(string $name, ?string $internalName = null, ?array $args = [])
    {
        return new static($name, $internalName, $args);
    }
}
