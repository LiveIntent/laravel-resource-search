<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelResourceSearch\Searchable;
use LiveIntent\LaravelResourceSearch\Directives\AllowedSort;
use LiveIntent\LaravelResourceSearch\Directives\AllowedScope;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;
use LiveIntent\LaravelResourceSearch\Contracts\SearchableResource;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;

class PostResource extends JsonResource implements SearchableResource
{
    use Searchable;

    /**
     * The base model of the resource.
     */
    public $model = Post::class;

    /**
     * Dictates if exact total counts are includable in the api response.
     */
    protected $allowExactTotals = true;

    /**
     * The fields to use when performing full text search.
     */
    public function searchableBy()
    {
        return ['title'];
    }

    /**
     * The allowed query scopes for the resource.
     */
    public function allowedScopes()
    {
        return [
            AllowedScope::name('published'),
        ];
    }

    /**
     * The allowed filters for the resource.
     */
    public function allowedFilters()
    {
        return [
            AllowedFilter::string('title'),
            AllowedFilter::timestamp('publish_at'),
        ];
    }

    /**
     * The allowed sorts for the resource.
     */
    public function allowedSorts()
    {
        return [
            AllowedSort::field('title'),
            AllowedSort::field('publish_at'),
        ];
    }
}
