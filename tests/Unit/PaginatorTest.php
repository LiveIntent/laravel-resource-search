<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Unit;

use Illuminate\Support\Facades\Config;
use LiveIntent\LaravelResourceSearch\Tests\TestCase;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;

class PaginatorTest extends TestCase
{
    /** @test */
    public function requests_can_be_paginated_to_specific_page_sizes()
    {
        Post::factory()->count(5)->create();

        $this->request(['page' => ['size' => 2]]);

        $this->assertEquals(2, Post::query()->apiPaginate()->count());
        $this->assertCount(2, Post::query()->apiPaginate()->toArray()['data']);
    }

    /** @test */
    public function page_size_must_be_greater_than_zero()
    {
        Post::factory()->count(3)->create();
        Config::set('resource-search.pagination.default_size', 2);

        $this->request(['page' => ['size' => 0]]);
        $this->assertEquals(2, Post::query()->apiPaginate()->count());

        $this->request(['page' => ['size' => -1]]);
        $this->assertEquals(2, Post::query()->apiPaginate()->count());

        $this->request(['page' => ['size' => 2]]);
        $this->assertEquals(2, Post::query()->apiPaginate()->count());

        $this->request(['page' => ['size' => 3]]);
        $this->assertEquals(3, Post::query()->apiPaginate()->count());
    }

    /** @test */
    public function page_size_must_be_less_than_or_equal_to_the_configured_max()
    {
        Post::factory()->count(3)->create();
        Config::set('resource-search.pagination.max_size', 2);

        $this->request();
        $this->assertEquals(2, Post::query()->apiPaginate()->count());

        $this->request(['page' => ['size' => 2]]);
        $this->assertEquals(2, Post::query()->apiPaginate()->count());

        $this->request(['page' => ['size' => 3]]);
        $this->assertEquals(2, Post::query()->apiPaginate()->count());
    }

    /** @test */
    public function the_base_url_can_be_set_to_use_a_relative_url()
    {
        Post::factory()->count(2)->create();
        Config::set('resource-search.pagination.default_size', 1);

        Config::set('resource-search.pagination.use_relative_urls', true);
        $this->assertStringStartsWith('?page%5Bcursor%5D=', Post::query()->apiPaginate()->toArray()['next_page_url']);

        Config::set('resource-search.pagination.use_relative_urls', false);
        $this->assertStringStartsWith('http://localhost?page%5Bcursor%5D=', Post::query()->apiPaginate()->toArray()['next_page_url']);
    }
}
