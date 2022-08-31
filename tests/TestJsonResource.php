<?php

namespace LiveIntent\LaravelResourceSearch\Tests;

use Illuminate\Http\Resources\Json\JsonResource;
use LiveIntent\LaravelResourceSearch\Searchable;
use LiveIntent\LaravelResourceSearch\Contracts\SearchableResource;

class TestJsonResource extends JsonResource implements SearchableResource
{
    use Searchable;
}
