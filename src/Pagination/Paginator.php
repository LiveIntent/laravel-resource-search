<?php

namespace LiveIntent\LaravelResourceSearch\Pagination;

use Illuminate\Support\Arr;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

class Paginator extends CursorPaginator
{
    /**
     * The exact total number of items.
     */
    protected $exactTotal;

    /**
     * The estimated total number of items.
     */
    protected $estimatedTotal;

    /**
     * Tell the paginator to include an exact total in the response.
     */
    public function withExactTotal(int $exactTotal)
    {
        $this->exactTotal = $exactTotal;
    }

    /**
     * Tell the paginator to include an estimated total in the response.
     */
    public function withEstimatedTotal(int $estimatedTotal)
    {
        $this->estimatedTotal = $estimatedTotal;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge(
            parent::toArray(),
            array_filter([
                'total' => $this->exactTotal,
                'estimated_total' => $this->estimatedTotal,
            ])
        );
    }

    /**
     * Build the pagination macro that can be called on the QueryBuilder to paginate results.
     *
     * Most of this code comes directly from https://github.com/spatie/laravel-json-api-paginate.
     */
    public static function buildMacro()
    {
        // We want to temporarily swap the CursorPaginator for our own Paginator, just
        // for the duration of this marco call. This allows us to take advantage of
        // the framework's cursor pagination setup and supplement our own logic.
        $unbind = bind_temporarily(CursorPaginator::class, static::class);

        return tap(function (array $options = []) {
            /** @phpstan-ignore-next-line */
            $builder = $this;

            // First let's grab all the developer configured options for this resource
            $maxResults = data_get($options, 'max_size', config('resource-search.pagination.max_size', 10000));
            $defaultSize = data_get($options, 'default_size', config('resource-search.pagination.default_size'));
            $paginationMethod = data_get($options, 'method', config('resource-search.pagination.method', 'combined'));
            $allowsExactTotals = data_get($options, 'allow_exact_totals', false);

            // Next, we will get the configured options for the application as a whole
            $numberParameter = config('resource-search.pagination.number_parameter', 'number');
            $cursorParameter = config('resource-search.pagination.cursor_parameter', 'cursor');
            $sizeParameter = config('resource-search.pagination.size_parameter', 'size');
            $paginationParameter = config('resource-search.pagination.pagination_parameter', 'page');

            // Now, we'll start to parse the runtime options passed in by the consumer
            $size = (int) request()->input($paginationParameter.'.'.$sizeParameter, $defaultSize);
            $cursor = (string) request()->input($paginationParameter.'.'.$cursorParameter);
            $page = (string) request()->input($paginationParameter.'.'.$numberParameter);
            $wantsExactTotals = request()->has('include_total_count')
                              ? filter_var(request()->input('include_total_count'))
                              : data_get($options, 'include_exact_total_count_by_default', false);

            // After we've collected the inputs we can apply any adjustments necessary
            if ($size <= 0) {
                $size = $defaultSize;
            }

            if ($size > $maxResults) {
                $size = $maxResults;
            }

            if ($paginationMethod === 'combined') {
                $paginationMethod = $page ? 'paginate' : 'cursorPaginate';
            }

            // The pagination method selected will inform which Builder method to call
            $paginator = $paginationMethod === 'cursorPaginate'
                ? $builder->cursorPaginate($size, ['*'], $paginationParameter.'['.$cursorParameter.']', $cursor)
                : $builder->{$paginationMethod}($size, ['*'], $paginationParameter.'.'.$numberParameter);

            // Before we finish up, we can add additional information to the paginator
            if ($wantsExactTotals && $allowsExactTotals && method_exists($paginator, 'withExactTotal')) {
                $paginator->withExactTotal($builder->toBase()->getCountForPagination());
            }

            if ($paginator instanceof AbstractPaginator) {
                $paginator->setPageName($paginationParameter.'['.$numberParameter.']')
                    ->appends(Arr::except(request()->input(), $paginationParameter.'.'.$numberParameter));
            }

            if (config('resource-search.pagination.use_relative_urls')) {
                $paginator->withPath('');
            }

            return $paginator;
        }, $unbind);
    }
}
