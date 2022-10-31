<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Unit;

use Illuminate\Http\Request;
use LiveIntent\LaravelResourceSearch\Builder;
use LiveIntent\LaravelResourceSearch\Tests\TestCase;
use LiveIntent\LaravelResourceSearch\RelationsResolver;
use LiveIntent\LaravelResourceSearch\Directives\AllowedSort;
use LiveIntent\LaravelResourceSearch\Tests\TestJsonResource;
use LiveIntent\LaravelResourceSearch\Directives\AllowedScope;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\User;
use LiveIntent\LaravelResourceSearch\Exceptions\InvalidResourceScopeException;

class SearchableResourceTest extends TestCase
{
    /** @test */
    public function scopes_can_be_applied_to_the_query()
    {
        $postA = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14']);
        $postB = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14', 'meta' => 'verse']);
        $postC = Post::factory()->create(['publish_at' => '2020-02-01 09:35:14']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedScopes()
            {
                return [
                    AllowedScope::name('specialMetaAliasName', 'withMeta'),
                    AllowedScope::name('publishedAt'),
                ];
            }
        };

        $this->request([
            'scopes' => [
                ['name' => 'specialMetaAliasName'],
                ['name' => 'publishedAt', 'parameters' => ['2019-01-01 09:35:14']],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(1, $results);
        $this->assertEquals($postB->id, $results->first()->id);
    }

    /** @test */
    public function allowed_scopes_must_be_valid_instances()
    {
        $this->expectException(InvalidResourceScopeException::class);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedScopes()
            {
                /** @phpstan-ignore-next-line */
                return [
                    'publishedAt',
                ];
            }
        };

        $queryBuilder = new Builder($resource, new RelationsResolver([], []));

        $queryBuilder->applyScopesToQuery(Post::query(), []);
    }

    /** @test */
    public function fields_are_filterable_with_single_values()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('myTitleAlias', 'title'),
                    AllowedFilter::number('tracking_id'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'myTitleAlias', 'operator' => '=', 'value' => 'test post'],
                ['type' => 'or', 'field' => 'tracking_id', 'operator' => '=', 'value' => 5],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_multiple_values()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);
        $postD = Post::factory()->create(['title' => 'different post', 'tracking_id' => 15]);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                    AllowedFilter::number('tracking_id'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'title', 'operator' => 'in', 'value' => ['test post', 'something else']],
                ['type' => 'or', 'field' => 'tracking_id', 'operator' => 'in', 'value' => [5, 10]],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(3, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertFalse($results->contains('id', $postD->id));
    }

    /** @test */
    public function related_fields_are_filterable_with_singular_values()
    {
        $postAUser = User::factory()->create(['name' => 'test user A']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'test user B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'test user C']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('user.name'),
                    AllowedFilter::string('user.aliasedName', 'user.name'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'user.aliasedName', 'operator' => '=', 'value' => 'test user A'],
                ['type' => 'or', 'field' => 'user.name', 'operator' => '=', 'value' => 'test user B'],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function related_fields_are_filterable_with_multiple_values()
    {
        $postAUser = User::factory()->create(['name' => 'test user A']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'test user B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'test user C']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('user.name'),
                    AllowedFilter::string('user.aliasedName', 'user.name'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'user.aliasedName', 'operator' => 'in', 'value' => ['test user A', 'test user B']],
                ['type' => 'or', 'field' => 'user.name', 'operator' => 'in', 'value' => ['test user C']],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(3, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_not_in_operator()
    {
        $postA = Post::factory()->create(['title' => 'test post A']);
        $postB = Post::factory()->create(['title' => 'test post B']);
        $postC = Post::factory()->create(['title' => 'test post C']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'title', 'operator' => 'not in', 'value' => ['test post A', 'test post B']],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(1, $results);
        $this->assertFalse($results->contains('id', $postA->id));
        $this->assertFalse($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_like_and_not_like_operators()
    {
        $postA = Post::factory()->create(['title' => 'test post A']);
        $postB = Post::factory()->create(['title' => 'test post B']);
        $postC = Post::factory()->create(['title' => 'test post C']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'title', 'operator' => 'like', 'value' => 'test post%'],
                ['field' => 'title', 'operator' => 'not like', 'value' => '%B%'],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertFalse($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_ilike_and_not_ilike_operators()
    {
        $postA = Post::factory()->create(['title' => 'Test Post A']);
        $postB = Post::factory()->create(['title' => 'Test Post B']);
        $postC = Post::factory()->create(['title' => 'Test Post C']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'title', 'operator' => 'ilike', 'value' => 'test post%'],
                ['field' => 'title', 'operator' => 'not ilike', 'value' => '%B%'],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertFalse($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_the_inequality_operators()
    {
        $postA = Post::factory()->create(['publish_at' => '2019-01-01 09:35:14']);
        $postB = Post::factory()->create(['publish_at' => '2019-01-02 09:35:14']);
        $postC = Post::factory()->create(['publish_at' => '2019-01-03 09:35:14']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::timestamp('publish_at'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'publish_at', 'operator' => '>', 'value' => '2019-01-01'],
                ['field' => 'publish_at', 'operator' => '<=', 'value' => '2019-01-03 08:00:00'],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function fields_are_filterable_with_nested_filters_up_to_the_max_configured_depth()
    {
        $postA = Post::factory()->create(['title' => 'test post', 'tracking_id' => 1]);
        $postB = Post::factory()->create(['title' => 'another test post', 'tracking_id' => 5]);
        $postC = Post::factory()->create(['title' => 'different post', 'tracking_id' => 10]);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('title'),
                ];
            }
        };

        $this->request([
            'filters' => [
                ['field' => 'title', 'operator' => '=', 'value' => 'test post'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'title', 'operator' => 'like', 'value' => '%post%'],
                    ['type' => 'and', 'nested' => [
                        ['field' => 'title', 'operator' => 'like', 'value' => '%post%'],
                        ['field' => 'title', 'operator' => 'not like', 'value' => '%different%'],
                    ]],
                ]],
            ],
        ]);

        $results = $resource::search();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertFalse($results->contains('id', $postC->id));
    }

    /** @test */
    public function full_text_search_can_be_done_on_specified_fields()
    {
        $postA = Post::factory()->create(['title' => 'title example']);
        $postB = Post::factory()->create(['title' => 'example title']);
        $postC = Post::factory()->create(['title' => 'title with example in the middle']);
        $postD = Post::factory()->create(['title' => 'not matching title', 'body' => 'but matching example body']);
        $postE = Post::factory()->create(['title' => 'not matching title']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function searchableBy()
            {
                return [
                    'title', 'body',
                ];
            }
        };

        $this->request(['search' => ['value' => 'example']]);

        $results = $resource::search();

        $this->assertCount(4, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertTrue($results->contains('id', $postD->id));
        $this->assertFalse($results->contains('id', $postE->id));
    }

    /** @test */
    public function full_text_search_can_be_done_on_specified_related_fields()
    {
        $postAUser = User::factory()->create(['name' => 'name example']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'example name']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'name with example in the middle']);
        $postC = Post::factory()->for($postCUser)->create();

        $postDUser = User::factory()->create(['name' => 'not matching name', 'email' => 'but-matching-email@example.com']);
        $postD = Post::factory()->for($postDUser)->create();

        $postEUser = User::factory()->create(['name' => 'not matching name', 'email' => 'test@domain.com']);
        $postE = Post::factory()->for($postEUser)->create();

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function searchableBy()
            {
                return [
                    'user.name', 'user.email',
                ];
            }
        };

        $this->request(['search' => ['value' => 'example']]);

        $results = $resource::search();

        $this->assertCount(4, $results);
        $this->assertTrue($results->contains('id', $postA->id));
        $this->assertTrue($results->contains('id', $postB->id));
        $this->assertTrue($results->contains('id', $postC->id));
        $this->assertTrue($results->contains('id', $postD->id));
        $this->assertFalse($results->contains('id', $postE->id));
    }

    /** @test */
    public function sort_can_be_applied_on_model_fields()
    {
        $postC = Post::factory()->create(['title' => 'post C']);
        $postB = Post::factory()->create(['title' => 'post B']);
        $postA = Post::factory()->create(['title' => 'post A']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('title'),
                ];
            }
        };

        $this->request([
            'sort' => [
                ['field' => 'title'],
            ],
        ]);

        $results = $resource::search();

        $this->assertEquals($postA->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postC->id, $results[2]->id);
    }

    /** @test */
    public function sort_can_be_applied_in_reverse_on_model_fields()
    {
        $postA = Post::factory()->create(['title' => 'post A']);
        $postB = Post::factory()->create(['title' => 'post B']);
        $postC = Post::factory()->create(['title' => 'post C']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('title'),
                ];
            }
        };

        $this->request([
            'sort' => [
                ['field' => 'title', 'direction' => 'desc'],
            ],
        ]);

        $results = $resource::search();

        $this->assertEquals($postC->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postA->id, $results[2]->id);
    }

    /** @test */
    public function sort_can_be_applied_on_related_fields()
    {
        $postAUser = User::factory()->create(['name' => 'name C']);
        $postA = Post::factory()->for($postAUser)->create();

        $postBUser = User::factory()->create(['name' => 'name B']);
        $postB = Post::factory()->for($postBUser)->create();

        $postCUser = User::factory()->create(['name' => 'name A']);
        $postC = Post::factory()->for($postCUser)->create();

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('user.name'),
                ];
            }
        };

        $this->request([
            'sort' => [
                ['field' => 'user.name'],
            ],
        ]);

        $results = $resource::search();

        $this->assertEquals($postC->id, $results[0]->id);
        $this->assertEquals($postB->id, $results[1]->id);
        $this->assertEquals($postA->id, $results[2]->id);
    }

    /** @test */
    public function resources_can_individually_permit_or_prohibit_including_total_counts()
    {
        Post::factory(5)->create();

        $resource = new class(new Post()) extends TestJsonResource
        {
            public $model = Post::class;

            protected $includeExactTotalCountByDefault = true;

            protected $allowExactTotals = true;
        };
        $this->assertTrue(property_exists($resource::search()->toResponse($this->request())->getData()->meta, 'total'));
        $this->assertEquals(5, $resource::search()->toResponse($this->request())->getData()->meta->total);

        $resource = new class(new Post()) extends TestJsonResource
        {
            public $model = Post::class;

            protected $includeExactTotalCountByDefault = true;

            protected $allowExactTotals = false;
        };
        $request = tap($this->request(), fn ($request) => $request->query->set('include_total_count', true));
        $this->assertFalse(property_exists($resource::search()->toResponse($this->request())->getData()->meta, 'total'));
    }

    /** @test */
    public function resources_can_individually_configure_default_total_count_inclusion_behavior()
    {
        Post::factory(5)->create();

        $resource = new class(new Post()) extends TestJsonResource
        {
            public $model = Post::class;

            protected $includeExactTotalCountByDefault = true;

            protected $allowExactTotals = true;
        };
        $this->assertTrue(property_exists($resource::search()->toResponse($this->request())->getData()->meta, 'total'));
        $this->assertEquals(5, $resource::search()->toResponse($this->request())->getData()->meta->total);

        $resource = new class(new Post()) extends TestJsonResource
        {
            public $model = Post::class;

            protected $includeExactTotalCountByDefault = false;

            protected $allowExactTotals = true;
        };
        $this->assertFalse(property_exists($resource::search()->toResponse($this->request())->getData()->meta, 'total'));
    }

    /** @test */
    public function searchable_resources_can_still_be_used_as_normal_resources()
    {
        $posts = Post::factory(2)->create();

        $resource = new class(Post::find($posts[0]->id)) extends TestJsonResource
        {
            public $model = Post::class;

            public function toArray($requset)
            {
                return [
                    // @phpstan-ignore-next-line
                    'id' => $this->id,
                    'random' => 'thing',
                ];
            }
        };

        $this->assertEquals($posts[0]->id, $resource->toArray(new Request())['id']);
        $this->assertEquals('thing', $resource->toArray(new Request())['random']);

        $collection = $resource::collection(Post::all());
        $this->assertInstanceOf(AnonymousResourceCollection::class, $collection);
        $this->assertEquals(2, $collection->count());
    }

    /** @test */
    public function searchable_resources_can_start_with_an_existing_query()
    {
        Post::factory()->create();
        Post::factory()->create(['publish_at' => '2019-01-01 09:35:14']);
        Post::factory()->create(['publish_at' => '2020-02-01 09:35:14']);

        $resource = new class(null) extends TestJsonResource
        {
            public $model = Post::class;
        };

        $results = $resource::search(Post::published());

        $this->assertEquals(2, $results->count());
    }
}
