<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Feature;

use Illuminate\Support\Facades\Config;
use LiveIntent\LaravelResourceSearch\Tests\TestCase;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;

class SearchRequestTest extends TestCase
{
    /** @test */
    public function search_requests_can_be_made()
    {
        $this->post('/api/posts/search')->assertResponseCount(0);

        Post::factory()->count(10)->create();
        $this->post('/api/posts/search')->assertResponseCount(10);
    }

    /** @test */
    public function search_requests_can_use_scopes()
    {
        Post::factory(2)->create(['publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create();

        $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published'],
            ],
        ])->assertResponseCount(2);
    }

    /** @test */
    public function search_requests_can_use_filters()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(2)]);

        $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published'],
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
                ['field' => 'title', 'operator' => 'like', 'value' => '%very%'],
            ],
        ])->assertResponseCount(2);
    }

    /** @test */
    public function search_requests_can_use_sorts()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(10)]);

        $response = $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published'],
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
            ],
            'sort' => [
                ['field' => 'publish_at'],
            ],
        ])->assertResponseCount(4);

        $this->assertEquals('very special title', $response->json('data')[0]['title']);
    }

    /** @test */
    public function search_requests_can_use_full_text_search()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(10)]);

        $response = $this->postJson('/api/posts/search', [
            'scopes' => [
                ['name' => 'published'],
            ],
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => '%special%'],
            ],
            'search' => [
                'value' => 'very',
            ],
            'sort' => [
                ['field' => 'publish_at'],
            ],
        ])->assertResponseCount(2);

        $this->assertEquals('very special title', $response->json('data')[0]['title']);
    }

    /** @test */
    public function search_requests_are_paginated_using_the_configured_default()
    {
        Config::set('resource-search.pagination.default_size', $size = 5);

        Post::factory()->count($size * 2)->create();

        $this->post('/api/posts/search')->assertResponseCount($size);
    }

    /** @test */
    public function search_requests_can_be_paginated_to_specific_page_sizes()
    {
        Post::factory()->count(5)->create();

        $this->postJson('/api/posts/search', ['page' => ['size' => 1]])->assertResponseCount(1);
        $this->postJson('/api/posts/search', ['page' => ['size' => 2]])->assertResponseCount(2);
        $this->postJson('/api/posts/search', ['page' => ['size' => 10]])->assertResponseCount(5);
        $this->postJson('/api/posts/search?page[size]=1')->assertResponseCount(1);
        $this->postJson('/api/posts/search?page[size]=2')->assertResponseCount(2);
        $this->postJson('/api/posts/search?page[size]=10')->assertResponseCount(5);

        Config::set('resource-search.pagination.max_size', 10);
        $this->postJson('/api/posts/search', ['page' => ['size' => 11]])->assertValidationErrors('page.size');
        $this->postJson('/api/posts/search?page[size]=11')->assertValidationErrors('page.size');
    }

    /** @test */
    public function search_requests_can_use_cursor_based_pagination()
    {
        $posts = Post::factory()->count(5)->create();

        $response = $this->postJson('/api/posts/search?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $response = $this->postJson("/api/posts/search?page[size]=2&page[cursor]=$cursor")
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $response = $this->postJson("/api/posts/search?page[size]=2&page[cursor]=$cursor")
            ->assertResponseCount(1)
            ->assertJsonPath('data.0.id', $posts[4]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $this->assertEmpty($cursor);
    }

    /** @test */
    public function search_requests_can_use_offset_based_pagination()
    {
        $posts = Post::factory()->count(5)->create();

        $this->postJson('/api/posts/search?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);

        $this->postJson('/api/posts/search?page[size]=2&page[number]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id);

        $this->postJson('/api/posts/search?page[size]=2&page[number]=3')
            ->assertResponseCount(1)
            ->assertJsonPath('data.0.id', $posts[4]->id);

        $this->postJson('/api/posts/search?page[size]=2&page[number]=4')
            ->assertResponseCount(0);
    }

    /** @test */
    public function search_requests_can_include_exact_total_result_counts()
    {
        $posts = Post::factory()->count(5)->create();

        $this->postJson('/api/posts/search?page[size]=2&include_total_count=true')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id)
            ->assertJsonPath('meta.total', 5);

        $this->postJson('/api/posts/search?page[size]=2&page[number]=2&include_total_count=true')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id)
            ->assertJsonPath('meta.total', 5);

        $this->postJson('/api/posts/search?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id)
            ->assertJsonPath('meta.total', null);
    }
}
