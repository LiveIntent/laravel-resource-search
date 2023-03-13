<?php

namespace LiveIntent\LaravelResourceSearch\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LiveIntent\LaravelResourceSearch\Contracts\SearchableResource;

class SearchInputValidator
{
    /**
     * The searchable resource.
     */
    private SearchableResource $resource;

    /**
     * The input to validate.
     *
     * @var array
     */
    private $input;

    /**
     * Create a new instance.
     */
    public function __construct(SearchableResource $resource, array $input = [])
    {
        $this->resource = $resource;
        $this->input = $input;
    }

    /**
     * Build the validation rules for searching the resource.
     */
    public function rules()
    {
        return [
            ...$this->scopeRules(),
            ...$this->filterRules(),
            ...$this->searchRules(),
            ...$this->sortRules(),
            ...$this->paginationRules(),
        ];
    }

    /**
     * Get the scope validation rules.
     */
    protected function scopeRules()
    {
        $exposedScopes = collect($this->resource->allowedScopes())->map->getName()->join(',');

        return [
            'scopes' => ['sometimes', 'array'],
            'scopes.*.name' => ['required_with:scopes', 'in:'.$exposedScopes],
            'scopes.*.parameters' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get the filter validation rules.
     */
    protected function filterRules()
    {
        $maxDepth = (int) floor($this->getArrayDepth($this->input('filters', [])) / 2);
        $configMaxNestedDepth = config('resource-search.max_nested_depth', 15);

        // Bork early if the nesting is to big so we don't screw ourselves
        throw_if(
            $maxDepth > $configMaxNestedDepth,
            ValidationException::withMessages([
                __('Max nested depth :depth is exceeded', ['depth' => $configMaxNestedDepth]),
            ])
        );

        return array_merge([
            'filters' => ['sometimes', 'array'], // TODO add array keys
        ], $this->getNestedFilterRules('filters', $maxDepth));
    }

    /**
     * Get the search validation rules.
     */
    protected function searchRules()
    {
        return [
            'search' => ['sometimes', 'array'],
            'search.value' => ['string', 'nullable'],
            'search.case_sensitive' => ['bool'],
        ];
    }

    /**
     * Get the scope validation rules.
     */
    protected function sortRules()
    {
        $exposedSorts = collect($this->resource->allowedSorts())->map->getName()->join(',');

        return [
            'sort' => ['sometimes', 'array'],
            'sort.*.field' => ['required_with:sort', 'in:'.$exposedSorts],
            'sort.*.direction' => ['sometimes', 'in:asc,desc'],
        ];
    }

    /**
     * Get the pagination validation rules.
     */
    protected function paginationRules()
    {
        $maxPageSize = config('resource-search.pagination.max_size');

        return [
            'page.size' => "integer|lte:{$maxPageSize}|gte:1",
            'page.number' => 'integer|gte:1',
            'page.cursor' => 'prohibits:page.number',
            'page.include_total_count' => 'boolean',
        ];
    }

    /**
     * Ref: https://github.com/tailflow/laravel-orion/blob/main/src/Drivers/Standard/ParamsValidator.php
     */
    protected function getNestedFilterRules(string $prefix, int $maxDepth, array $rules = [], int $currentDepth = 1): array
    {
        $filterableFields = collect($this->resource->allowedFilters())->keyBy->getName();

        $rules = array_merge($rules, [
            $prefix.'.*.type' => ['sometimes', 'in:and,or'],
            $prefix.'.*.nested' => ['sometimes', 'array'],
            $prefix.'.*.field' => [
                "required_without:{$prefix}.*.nested",
                Rule::in($filterableFields->keys()->toArray()),
            ],
            $prefix.'.*.operator' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                if (! $filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                return [
                    'sometimes',
                    Rule::in($filter->getAllowedOperators()),
                ];
            }),
            $prefix.'.*.value' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->toString();

                if (! $filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                $operator = $item["{$key}.operator"] ?? '=';
                if (in_array($operator, ['in', 'not in'])) {
                    return ['required', 'array'];
                }

                return $filter->getValidationRules();
            }),
            $prefix.'.*.value.*' => Rule::forEach(function ($_, $attribute, $item) use ($filterableFields) {
                $key = str($attribute)->beforeLast('.')->beforeLast('.')->toString();

                if (! $filter = $filterableFields->get($item["{$key}.field"] ?? '')) {
                    return [];
                }

                return $filter->getValidationRules();
            }),
        ]);

        if ($maxDepth >= $currentDepth) {
            $rules = array_merge(
                $rules,
                $this->getNestedFilterRules("{$prefix}.*.nested", $maxDepth, $rules, ++$currentDepth)
            );
        }

        return $rules;
    }

    /**
     * Get the depth of an array.
     */
    protected function getArrayDepth($array): int
    {
        $maxDepth = 0;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;

                $maxDepth = max($depth, $maxDepth);
            }
        }

        return $maxDepth;
    }

    /**
     * Retrieve an input item.
     */
    private function input($key, $default = null)
    {
        return data_get($this->input, $key, $default);
    }

    /**
     * Create a validator for the input.
     */
    public function validator()
    {
        return Validator::make($this->input, $this->rules());
    }

    /**
     * Validate the input.
     */
    public function validate()
    {
        $this->validator()->validate();
    }

    /**
     * Check if the validator fails.
     */
    public function fails()
    {
        return $this->validator()->fails();
    }
}
