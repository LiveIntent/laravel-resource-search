<?php

namespace LiveIntent\LaravelResourceSearch;

use ReflectionClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LiveIntent\LaravelResourceSearch\Directives\AllowedSort;
use LiveIntent\LaravelResourceSearch\Directives\AllowedScope;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;
use LiveIntent\LaravelResourceSearch\Validation\SearchInputValidator;
use LiveIntent\LaravelResourceSearch\Exceptions\InvalidResourceModelException;

/**
 * @property bool $allowExactTotals
 * @property bool $includeExactTotalCountByDefault
 * @property class-string<\Illuminate\Database\Eloquent\Model> $model
 */
trait Searchable
{
    /**
     * The fields to use when performing full text search.
     *
     * @return string[]
     */
    public function searchableBy()
    {
        return [];
    }

    /**
     * The allowed query scopes for the resource.
     *
     * @return array<AllowedScope>
     */
    public function allowedScopes()
    {
        return [];
    }

    /**
     * The allowed sortable fields for the resource.
     *
     * @return array<AllowedSort>
     */
    public function allowedSorts()
    {
        return [];
    }

    /**
     * The allowed filters for the resource.
     *
     * @return array<AllowedFilter>
     */
    public function allowedFilters()
    {
        return [];
    }

    /**
     * The allowed relationships to load for the resource.
     *
     * @return array
     */
    public function allowedIncludes()
    {
        return [];
    }

    /**
     * The relationships that should always be loaded for the resource.
     */
    public function alwaysInclude()
    {
        return [];
    }

    /**
     * Dictates if exact total counts should be allowed in the api response.
     */
    public function allowsExactTotals()
    {
        return $this->allowExactTotals ?? false;
    }

    /**
     * Dictates if exact total counts should be included by default in the api response.
     * NOTE: Only works if $allowExactTotals is also set to true.
     */
    public function includesExactTotalCountByDefault()
    {
        return $this->includeExactTotalCountByDefault ?? false;
    }

    /**
     * Get the model class that the resource transforms.
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Create a new filtered collection for the resource from a basic search.
     */
    public static function basicSearch(EloquentBuilder $query = null)
    {
        request()->replace(
            app(BasicSearchAdapter::class)->toAdvancedInput(request()->input())
        );

        return static::search($query);
    }

    /**
     * Create a new filtered collection for the resource.
     */
    public static function search(EloquentBuilder $query = null)
    {
        $input = request()->input();
        // @phpstan-ignore-next-line
        $resource = new static(null);
        $modelClass = $resource->model;

        if (! rescue(fn () => (new ReflectionClass($modelClass))->isSubclassOf(Model::class))) {
            throw new InvalidResourceModelException(static::class, $modelClass);
        }

        app(SearchInputValidator::class, [
            'resource' => $resource,
            'input' => $input,
        ])->validate();

        $builder = app(Builder::class, [
            'resource' => $resource,
            'input' => $input,
        ]);

        return static::collection(
            $builder
                ->buildQuery($query ?: $modelClass::query(), $input)
                ->apiPaginate([
                    'allow_exact_totals' => $resource->allowsExactTotals(),
                    'include_exact_total_count_by_default' => $resource->includesExactTotalCountByDefault(),
                ])
        );
    }
}
