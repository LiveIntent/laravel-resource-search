<?php

namespace LiveIntent\LaravelResourceSearch;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

class BasicSearchAdapter
{
    /**
     * Convert input from a 'basic' search request to an 'advanced' search request.
     */
    public function toAdvancedInput(array $input = [])
    {
        if (array_key_exists('sort', $input) && is_string($input['sort'])) {
            $input['sort'] = $this->adaptSort($input['sort']);
        }

        if (array_key_exists('q', $input) && is_string($input['q'])) {
            $input['search'] = $this->adaptSearch($input['q']);
        }

        if (array_key_exists('filter', $input) && is_array($input['filter'])) {
            $input['filters'] = $this->adaptFilters($input['filter']);
        }

        return $input;
    }

    /**
     * Adapt the sort portion of the query.
     */
    private function adaptSort(string $sorts)
    {
        return array_map(
            fn ($sort) => [
                'field' => preg_replace('/^\-/', '', $sort),
                'direction' => Str::startsWith($sort, '-') ? 'desc' : 'asc',
            ],
            explode(',', $sorts)
        );
    }

    /**
     * Adapt the search portion of the query.
     */
    private function adaptSearch(string $search)
    {
        return ['value' => $search];
    }

    /**
     * Adapt the filters portion of the query.
     */
    private function adaptFilters(array $filters = [])
    {
        return collect($filters)->keys()->map(function ($key) use ($filters) {
            $values = collect(explode(',', $filters[$key]))
                ->mapInto(Stringable::class)
                ->map
                ->replace('*', '%');

            // For convenience, we will allow `filter[field]=*` to be a non-null check
            if ($values->count() === 1 && $this->getFilterValue($values[0]) === '%') {
                return [
                    'field' => $key,
                    'value' => null,
                    'operator' => '!=',
                ];
            }

            // With at least one wildcard we'll use like. ex: `filter[field]=foo*,bar`
            if ($values->count() > 1 && $values->some->contains('%')) {
                return [
                    'type' => 'and', 'nested' => $values->map(
                        fn ($value) => [
                            'field' => $key,
                            'operator' => 'like',
                            'value' => $this->getFilterValue($value),
                            'type' => 'or',
                        ]
                    )->toArray(),
                ];
            }

            // Even without a nested group we can use an array of values as a quick or
            return [
                'field' => $key,
                'value' => $values->count() === 1 ? $this->getFilterValue($values[0]) : $this->getFilterValue($values),
                'operator' => $values->count() === 1 ? ($values[0]->contains('%') ? 'like' : '=') : 'in',
            ];
        })->toArray();
    }

    /**
     * Convert the value from the query string into a usable filter value.
     */
    private function getFilterValue($value)
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($v) => $this->getFilterValue($v))->toArray();
        }

        $value = str($value)->toString();

        return $value === '' ? null : $value;
    }
}
