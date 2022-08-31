<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Feature;

use Illuminate\Support\Facades\Config;
use LiveIntent\LaravelResourceSearch\Tests\TestCase;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;

class ListRequestTest extends TestCase
{
    /** @test */
    public function list_requests_can_be_made()
    {
        $this->getJson('/api/posts')->assertResponseCount(0);

        Post::factory()->count(10)->create();
        $this->getJson('/api/posts')->assertResponseCount(10);
    }

    /** @test */
    public function list_requests_can_use_simple_filters_in_query_params()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(2)]);

        $this->getJson('/api/posts?filter[title]=*')->assertResponseCount(6);
        $this->getJson('/api/posts?filter[title]=*very*')->assertResponseCount(2);
        $this->getJson('/api/posts?filter[title]=less special title')->assertResponseCount(2);
        $this->getJson('/api/posts?filter[title]=*very*,*less*')->assertResponseCount(4);
        $this->getJson('/api/posts?filter[publish_at]=')->assertResponseCount(2);
        $this->getJson('/api/posts?filter[publish_at]=*')->assertResponseCount(4);
        $this->getJson('/api/posts?filter[publish_at]=*&filter[title]=*very*')->assertResponseCount(2);

        $this->getJson('/api/posts?filter[foobar]=')->assertJsonValidationErrors(['filters.0.field']);
        $this->getJson('/api/posts?filter[publish_at]=123')->assertJsonValidationErrors(['filters.0.value']);
    }

    /** @test */
    public function list_requests_can_use_sorts()
    {
        $p1 = Post::factory()->create(['title' => 'more special title', 'publish_at' => now()->addSeconds(1)]);
        $p2 = Post::factory()->create(['title' => 'less special title', 'publish_at' => now()->addSeconds(1)]);
        $p3 = Post::factory()->create(['title' => 'more special title', 'publish_at' => now()->addSeconds(2)]);
        $p4 = Post::factory()->create(['title' => 'less special title', 'publish_at' => now()->addSeconds(2)]);
        $p5 = Post::factory()->create(['title' => 'more special title', 'publish_at' => now()->addSeconds(3)]);
        $p6 = Post::factory()->create(['title' => 'less special title', 'publish_at' => now()->addSeconds(3)]);

        $response = $this->getJson('/api/posts?sort=publish_at')->assertResponseCount(6);
        $this->assertEquals($p1->id, $response->json('data')[0]['id']);
        $this->assertEquals($p2->id, $response->json('data')[1]['id']);

        $response = $this->getJson('/api/posts?sort=-publish_at')->assertResponseCount(6);
        $this->assertEquals($p5->id, $response->json('data')[0]['id']);
        $this->assertEquals($p6->id, $response->json('data')[1]['id']);

        $response = $this->getJson('/api/posts?sort=-publish_at,title')->assertResponseCount(6);
        $this->assertEquals($p6->id, $response->json('data')[0]['id']);
        $this->assertEquals($p5->id, $response->json('data')[1]['id']);
    }

    /** @test */
    public function list_requests_can_use_full_text_search()
    {
        Post::factory(2)->create(['title' => 'special title']);
        Post::factory(2)->create(['title' => 'less special title', 'publish_at' => now()->subSeconds(2)]);
        Post::factory(2)->create(['title' => 'very special title', 'publish_at' => now()->subSeconds(10)]);

        $response = $this->getJson('/api/posts?q=very')->assertResponseCount(2);

        $this->assertEquals('very special title', $response->json('data')[0]['title']);
    }

    /** @test */
    public function list_requests_are_paginated_using_the_configured_default()
    {
        Config::set('resource-search.pagination.default_size', $size = 5);

        Post::factory()->count($size * 2)->create();

        $this->getJson('/api/posts')->assertResponseCount($size);
    }

    /** @test */
    public function list_requests_can_be_paginated_to_specific_page_sizes()
    {
        Post::factory()->count(5)->create();

        $this->getJson('/api/posts?page[size]=1')->assertResponseCount(1);
        $this->getJson('/api/posts?page[size]=2')->assertResponseCount(2);
        $this->getJson('/api/posts?page[size]=10')->assertResponseCount(5);

        Config::set('resource-search.pagination.max_size', 10);
        $this->getJson('/api/posts?page[size]=11')->assertValidationErrors('page.size');
    }

    /** @test */
    public function list_requests_can_use_cursor_based_pagination()
    {
        $posts = Post::factory()->count(5)->create();

        $response = $this->getJson('/api/posts?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $response = $this->getJson("/api/posts?page[size]=2&page[cursor]=$cursor")
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $response = $this->getJson("/api/posts?page[size]=2&page[cursor]=$cursor")
            ->assertResponseCount(1)
            ->assertJsonPath('data.0.id', $posts[4]->id);

        $cursor = $response->json('meta')['next_cursor'] ?? '';
        $this->assertEmpty($cursor);
    }

    /** @test */
    public function list_requests_can_use_offset_based_pagination()
    {
        $posts = Post::factory()->count(5)->create();

        $this->getJson('/api/posts?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id);

        $this->getJson('/api/posts?page[size]=2&page[number]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id);

        $this->getJson('/api/posts?page[size]=2&page[number]=3')
            ->assertResponseCount(1)
            ->assertJsonPath('data.0.id', $posts[4]->id);

        $this->getJson('/api/posts?page[size]=2&page[number]=4')
            ->assertResponseCount(0);
    }

    /** @test */
    public function list_requests_can_include_exact_total_result_counts()
    {
        $posts = Post::factory()->count(5)->create();

        $this->getJson('/api/posts?page[size]=2&include_total_count=true')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id)
            ->assertJsonPath('meta.total', 5);

        $this->getJson('/api/posts?page[size]=2&page[number]=2&include_total_count=true')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[2]->id)
            ->assertJsonPath('data.1.id', $posts[3]->id)
            ->assertJsonPath('meta.total', 5);

        $this->getJson('/api/posts?page[size]=2')
            ->assertResponseCount(2)
            ->assertJsonPath('data.0.id', $posts[0]->id)
            ->assertJsonPath('data.1.id', $posts[1]->id)
            ->assertJsonPath('meta.total', null);
    }
}
