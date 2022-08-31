# 🔍 Laravel Resource Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/liveintent/laravel-resource-search.svg?style=flat-square)](https://packagist.org/packages/liveintent/laravel-resource-search)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-resource-search/test-php?label=tests)](https://github.com/liveintent/laravel-resource-search/actions?query=workflow%3Atest-php+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-resource-search/lint-php?label=code%20style)](https://github.com/liveintent/laravel-resource-search/actions?query=workflow%3Alint-php+branch%3Amain)

This package allows you to easily and safely provide comprehensive filtering and sorting capabilities to your Laravel endpoints by simply defining which fields should be exposed in the corresponding Eloquent API Resource object.

The package is heavily inspired by the [Spatie Query Builder](https://github.com/spatie/laravel-query-builder/tree/main) and [Laravel Orion](https://tailflow.github.io/laravel-orion-docs/).

## Basic usage

### Basic functionality is availble via query parameters in GET requests

```php
 // find employees where `name` = "Michael"
GET /employees?filter[name]=Michael

// find employees where `name` like "Michael%"
GET /employees?filter[name]=Michael* 

// find employees where `name` like "Michael%" OR `name` like "Dwight"
GET /employees?filter[name]=Michael*,Dwight 

// find employees where `name` =  "Michael" AND `email` like "%@dundermifflin.com"
GET /employees?filter[name]=Michael&filter[email]=*@dundermifflin.com

// find employees where `email` like "%@dundermifflin.com" order by `hired_at`
GET /employees?filter[email]=*@dundermifflin.com&sort=hired_at 

// find employees where `email` like "%@dundermifflin.com" order by `hired_at` desc
GET /employees?filter[email]=*@dundermifflin.com&sort=-hired_at 

// find the two most recently hired employees where any searchable field contains "stamford"
GET /employees?q=stamford&sort=-hired_at&page[size]=2
```

[Read more about basic search capabilities like: filters, pagination, search, sort...](/docs/README.md)

### Extended functionality is availble via body payload in POST requests

```jsonc
// POST /employees/search?page[size]=10
{
    "scopes" : [
        {"name" : "active"},
        {"name" : "whereBranch", "parameters" : ["scranton"]}
    ],
    "filters" : [
        {"field" : "hired_at", "operator" : ">=", "value" : "2001-01-01"},
        {"type" : "or", "field" : "name", "operator" : "in", "value" : ["Ed Truck", "Tod Packer"]}
    ],
    "search" : {
        "value" : "manager"
    },
    "sort" : [
        {"field" : "dundie_count", "direction" : "desc"},
        {"field" : "dundie.name", "direction" : "asc"}
    ]
}
```

[Read more about advanced search capabilities like: scopes, filters, pagination, search, sort...](/docs/README.md)

## Installation

You can install the package via composer:

```bash
composer require liveintent/laravel-resource-search
```

The package will automatically register its service provider.

You can optionally publish the config file with:

```
php artisan vendor:publish --provider="LiveIntent\LaravelResourceSearch\LaravelResourceSearchServiceProvider" --tag="resource-search-config"
```

### Adding searchable resources to your API

Laravel's [Eloquent API Resources](https://laravel.com/docs/master/eloquent-resources) serve as a transformation layer that sits betweeen your Eloquent models and the JSON responses that are actually returned to your application's usesr. For this reason, we think this is a great place to also define the capabilities your users should have when listing and searching those resources.

To transform any existing Eloquent resource into a Searchable Eloquent resource, you should have your resource use the `Searchable` trait provided by this package and instruct the resource that it `implements` the `SearchableResource` interface. Finally, you'll need to define a `$model` property on the resource which indicates which underlying Eloquent model should be used for the base query.

```php
<?php

use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelResourceSearch\Searchable;
use LiveIntent\LaravelResourceSearch\Contracts\SearchableResource;

class PostResource extends JsonResource implements SearchableResource
{
    use Searchable;

    /**
     * The base model of the resource.
     */
    public $model = \App\Models\Post::class;
}
```

### Adding search endpoints to your API

There are two methods users may use when searching your API. For simple, basic queries they may prefer to use a `GET` request with minimal query parameters to construct the query. Other times, users may want to construct more complex queries that require passing a larger request payload containing things like nested logical groups.

#### Adding a basic search endpoint

You can turn any new or existing endpoint into one that allows basic search by calling the `basicSearch` method on a `SearchableResource` and returning the result.

```php
use App\Http\Resources\PostResource;

Route::get('/posts', function () {
    return PostResource::basicSearch();
});
```

If you require additional control over the query, such as applying a query scope for example, create your query and pass the `Builder` into the `basicSearch` method to allow it to further filter results based on the user's input.

```php
use App\Models\Post;
use App\Http\Resources\PostResource;

Route::get('/published-posts', function () {
    return PostResource::basicSearch(Post::published());
});
```

#### Adding an advanced search endpoint

You can turn any new or existing endpoint into one that allows advanced search by calling the `search` method on a `SearchableResource` and returning the result.

```php
use App\Http\Resources\PostResource;

Route::post('/posts/search', function () {
    return PostResource::search();
});
```

Just like with basic seearh, if you require additional control over the query you may pass an existing `Builder` into the `search` method and it will further filter results based on the user's input.

```php
use App\Models\Post;
use App\Http\Resources\PostResource;

Route::post('/published-posts/search', function () {
    return PostResource::search(Post::published());
});
```

## Documentation

You can find the documentation [here](/docs/README.md).

## Changelog

Please see [CHANGELOG](/CHANGELOG.md) for information about what was changed recently.

## Contributing

Please see [CONTRIBUTING](/.github/CONTRIBUTING.md) for details.

## Why not...?

Before you use this package, there are several other options you should consider.

+ [Laravel Scout](https://laravel.com/docs/9.x/scout) - provides a simple, driver based solution for adding full-text search to your Eloquent models. If full-text search is what you're after, this is probably the way to go.
+ [Spatie Laravel Query Builder](https://github.com/spatie/laravel-query-builder/tree/main) - provides a simple yet extemely flexible package to enable pretty extensive filtering and sorting capabilities to your API based on the [JSON API specification](http://jsonapi.org/). However, this package does not yet support sending more extensive body payloads which limits the ability to allow for more extensive structured requests.
+ [Laravel Orion](https://tailflow.github.io/laravel-orion-docs/) - is a full-featured package designed to help you with designing REST APIs in Laravel. While Orion offers an amazing feature set, it also requires a much deeper and more opinionated integration with your app which can make it difficult if all you are looking to do is add filterability to one or two endpoints.
