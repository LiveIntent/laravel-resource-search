<?php

namespace LiveIntent\LaravelResourceSearch\Directives;

use Illuminate\Support\Traits\Macroable;
use LiveIntent\LaravelResourceSearch\Concerns\Aliasable;

/** @phpstan-consistent-constructor */
class AllowedFilter
{
    use Aliasable;
    use Macroable;

    /**
     * The permitted operators for this filter.
     *
     * @var string[]
     */
    protected $allowedOperators = [];

    /**
     * The validation rules for this filter's value.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Create a new instance.
     */
    public function __construct(string $name, ?string $internalName = null, ?array $allowedOperators = [], ?array $rules = [])
    {
        $this->name = $name;
        $this->internalName = $internalName ?? $name;
        $this->allowedOperators = $allowedOperators ?? [];
        $this->rules = $rules ?? [];
    }

    /**
     * Get the internal facing name.
     */
    public function getAllowedOperators(): array
    {
        return $this->allowedOperators;
    }

    /**
     * Get rules to use for validating the value of the filter.
     */
    public function getValidationRules()
    {
        return $this->rules;
    }

    /**
     * Create a new allowed filter for a string field.
     */
    public static function string(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<=', 'like', 'not like', 'ilike', 'not ilike'],
            ['string', 'nullable']
        );
    }

    /**
     * Create a new allowed filter for a number field.
     */
    public static function number(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<='],
            ['integer', 'numeric', 'nullable']
        );
    }

    /**
     * Create a new allowed filter for a timestamp field.
     */
    public static function timestamp(string $name, ?string $internalName = null)
    {
        return new static(
            $name,
            $internalName,
            ['=', '!=', 'in', 'not in', '>', '>=', '<', '<='],
            ['date', 'nullable']
        );
    }
}
