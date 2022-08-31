<?php

namespace LiveIntent\LaravelResourceSearch\Contracts;

use LiveIntent\LaravelResourceSearch\Directives\AllowedSort;
use LiveIntent\LaravelResourceSearch\Directives\AllowedScope;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;

interface SearchableResource
{
    /**
     * The fields to use when performing full text search.
     *
     * @return string[]
     */
    public function searchableBy();

    /**
     * The allowed query scopes for the resource.
     *
     * @return array<AllowedScope>
     */
    public function allowedScopes();

    /**
     * The allowed sortable fields for the resource.
     *
     * @return array<AllowedSort>
     */
    public function allowedSorts();

    /**
     * The allowed filters for the resource.
     *
     * @return array<AllowedFilter>
     */
    public function allowedFilters();

    /**
     * The allowed relationships to load for the resource.
     *
     * @return array
     */
    public function allowedIncludes();

    /**
     * The relationships that should always be loaded for the resource.
     *
     * @return string[]
     */
    public function alwaysInclude();

    /**
     * Dictates if exact total counts should be allowed in the api response.
     *
     * @return bool
     */
    public function allowsExactTotals();

    /**
     * Dictates if exact total counts should be included by default in the api response.
     * NOTE: Only works if $allowExactTotals is also set to true.
     *
     * @return bool
     */
    public function includesExactTotalCountByDefault();

    /**
     * Get the model class that the resource transforms.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function getModel();
}
