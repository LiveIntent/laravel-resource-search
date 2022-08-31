<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use LiveIntent\LaravelResourceSearch\Tests\TestCase;
use LiveIntent\LaravelResourceSearch\Directives\AllowedSort;
use LiveIntent\LaravelResourceSearch\Tests\TestJsonResource;
use LiveIntent\LaravelResourceSearch\Directives\AllowedScope;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Models\Post;
use LiveIntent\LaravelResourceSearch\Validation\SearchInputValidator;

class SearchInputValidatorTest extends TestCase
{
    /** @test */
    public function scopes_must_be_whitelisted()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedScopes()
            {
                return [
                    AllowedScope::name('valid'),
                ];
            }
        };

        $this->assertInvalid($resource, [
            'scopes' => [['name' => 'published']],
        ]);

        $this->assertInvalid($resource, [
            'scopes' => [['name' => 'published'], ['name' => 'valid']],
        ]);

        $this->assertValid($resource, [
            'scopes' => [['name' => 'valid']],
        ]);
    }

    /** @test */
    public function filters_must_have_a_field()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color'),
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [['value' => 'color']],
        ]);

        $this->assertValid($resource, [
            'filters' => [['field' => 'color']],
        ]);
    }

    /** @test */
    public function filters_may_use_boolean_logic()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color'),
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'xor'],
            ],
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'cookies'],
            ],
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'and'],
            ],
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'color', 'value' => 'red', 'type' => 'or'],
            ],
        ]);
    }

    /** @test */
    public function filter_fields_must_be_whitelisted()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color'),
                ];
            }
        };

        $this->assertInvalid($resource, [
            'filters' => [['field' => 'colour', 'value' => 'red']],
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['field' => 'colour', 'value' => 'red'],
            ],
        ]);

        $this->assertInvalid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'color', 'value' => 'red'],
                    ['field' => 'colour', 'value' => 'red'],
                ]],
            ],
        ]);

        $this->assertValid($resource, [
            'filters' => [['field' => 'color', 'value' => 'red']],
        ]);

        $this->assertValid($resource, [
            'filters' => [
                ['field' => 'color', 'value' => 'red'],
                ['type' => 'or', 'nested' => [
                    ['field' => 'color', 'value' => 'red'],
                ]],
            ],
        ]);
    }

    /** @test */
    public function filters_may_only_be_nested_to_a_certain_configured_max_depth()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color'),
                ];
            }
        };

        Config::set('resource-search.max_nested_depth', 3);
        $this->assertValid($resource, [
            'filters' => [
                ['nested' => [
                    ['nested' => [
                        ['nested' => [
                            ['field' => 'color', 'value' => 'green'],
                        ]],
                    ]],
                ]],
            ],
        ]);

        Config::set('resource-search.max_nested_depth', 2);
        $this->expectException(ValidationException::class);
        $this->assertInvalid($resource, [
            'filters' => [
                ['nested' => [
                    ['nested' => [
                        ['nested' => [
                            ['field' => 'color', 'value' => 'green'],
                        ]],
                    ]],
                ]],
            ],
        ]);
    }

    /** @test */
    public function string_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::string('color'),
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<=', 'like', 'not like'])
            ->crossJoin(['red', 'blue', '', null])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([['red'], ['red', 'blue'], ['red', null]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<=', 'like', 'not like'])
            ->crossJoin([1, 100, [], ['red'], false])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, [], [100], [false]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'color', 'value' => $value, 'operator' => $operator]],
                ]);
            });
    }

    /** @test */
    public function number_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::number('likes'),
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin([-1, 0, 1, 2, null, '', '1', '100'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([[1], [1, 2], [3, null]])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin(['red', [], ['red'], false])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, 1, 100, [], ['one'], ['red']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['like', 'not like'])
            ->crossJoin([-1, 0, 1, 2, null])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'likes', 'value' => $value, 'operator' => $operator]],
                ]);
            });
    }

    /** @test */
    public function timestamp_fields_are_only_filterable_with_relevant_operators_and_values()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedFilters()
            {
                return [
                    AllowedFilter::timestamp('went_to_darkside_at'),
                ];
            }
        };

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin([null, '', '2022-01-01', '2022-01-01 00:00:00', '2022-01-01T00:00:00'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin([[null], ['2022-01-01'], ['2022-01-01 00:00:00'], ['2022-01-01T00:00:00']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertValid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['=', '!=', '>', '>=', '<', '<='])
            ->crossJoin(['1', '100', 'red', [], ['red'], false, 1659571200, 'now', 'yesterday', '+1 week'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkside_at', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['in', 'not in'])
            ->crossJoin(['red', 'blue', '', null, false, 1, 100, [], ['100'], ['red'], [1659571200], ['now'], ['yesterday'], ['+1 week']])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkdside_at', 'value' => $value, 'operator' => $operator]],
                ]);
            });

        collect()
            ->concat(['like', 'not like'])
            ->crossJoin([null, '2022-01-01', '2022-01-01 00:00:00', '2022-01-01T00:00:00'])
            ->eachSpread(function ($operator, $value) use ($resource) {
                $this->assertInvalid($resource, [
                    'filters' => [['field' => 'went_to_darkdside_at', 'value' => $value, 'operator' => $operator]],
                ]);
            });
    }

    /** @test */
    public function searches_must_have_a_valid_string_value()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['search' => ['value' => 'foobar']]);
        $this->assertValid($resource, ['search' => ['value' => 'f']]);
        $this->assertValid($resource, ['search' => ['value' => '100']]);
        $this->assertValid($resource, ['search' => ['value' => null]]);
        $this->assertValid($resource, ['search' => ['value' => '']]);

        $this->assertInvalid($resource, ['search' => null]);
        $this->assertInvalid($resource, ['search' => 'foobar']);
        $this->assertInvalid($resource, ['search' => ['value' => 100]]);
        $this->assertInvalid($resource, ['search' => ['value' => false]]);
        $this->assertInvalid($resource, ['search' => ['value' => true]]);
        $this->assertInvalid($resource, ['search' => ['value' => []]]);
        $this->assertInvalid($resource, ['search' => ['value' => ['foobar']]]);
    }

    /** @test */
    public function searches_may_have_a_boolean_arg_for_case_sensitive()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => true]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => false]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 0]]);
        $this->assertValid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 1]]);
        $this->assertValid($resource, ['search' => ['case_sensitive' => true]]);

        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'true']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'false']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 100]]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'yes']]);
        $this->assertInvalid($resource, ['search' => ['value' => 'foobar', 'case_sensitive' => 'no']]);
    }

    /** @test */
    public function sorts_must_be_whitelisted()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('barfoo'),
                ];
            }
        };

        $this->assertInvalid($resource, ['sort' => [['field' => 'foobar']]]);
        $this->assertInvalid($resource, ['sort' => [['field' => '']]]);
        $this->assertInvalid($resource, ['sort' => [['field' => null]]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 100]]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'foobar'], ['field' => 'barfoo']]]);

        $this->assertValid($resource, ['sort' => [['field' => 'barfoo']]]);
        $this->assertValid($resource, ['sort' => []]);
    }

    /** @test */
    public function sort_direction_must_be_valid()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;

            public function allowedSorts()
            {
                return [
                    AllowedSort::field('barfoo'),
                ];
            }
        };

        $this->assertValid($resource, ['sort' => [['field' => 'barfoo']]]);
        $this->assertValid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'asc']]]);
        $this->assertValid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'desc']]]);

        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'ASC']]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'DESC']]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => true]]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => false]]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'abra']]]);
        $this->assertInvalid($resource, ['sort' => [['field' => 'barfoo', 'direction' => 'kedabra']]]);
    }

    /** @test */
    public function page_size_must_not_be_greater_than_the_configured_amount()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        Config::set('resource-search.pagination.max_size', 10);
        $this->assertValid($resource, ['page' => ['size' => 1]]);
        $this->assertValid($resource, ['page' => ['size' => 10]]);
        $this->assertValid($resource, ['page' => ['size' => '10']]);

        $this->assertInvalid($resource, ['page' => ['size' => 11]]);
        $this->assertInvalid($resource, ['page' => ['size' => 0]]);
        $this->assertInvalid($resource, ['page' => ['size' => -1]]);
    }

    /** @test */
    public function page_number_must_be_a_valid_postive_integer()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['page' => ['number' => 1]]);
        $this->assertValid($resource, ['page' => ['number' => 10]]);
        $this->assertValid($resource, ['page' => ['number' => 10000000000]]);
        $this->assertValid($resource, ['page' => ['number' => '4']]);

        $this->assertInvalid($resource, ['page' => ['number' => 0]]);
        $this->assertInvalid($resource, ['page' => ['number' => -1]]);
        $this->assertInvalid($resource, ['page' => ['number' => 'one']]);
    }

    /** @test */
    public function page_include_total_count_must_be_a_boolean()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['page' => ['include_total_count' => 1]]);
        $this->assertValid($resource, ['page' => ['include_total_count' => 0]]);
        $this->assertValid($resource, ['page' => ['include_total_count' => true]]);
        $this->assertValid($resource, ['page' => ['include_total_count' => false]]);

        $this->assertInvalid($resource, ['page' => ['include_total_count' => 'true']]);
        $this->assertInvalid($resource, ['page' => ['include_total_count' => 'false']]);
        $this->assertInvalid($resource, ['page' => ['include_total_count' => 100]]);
    }

    /** @test */
    public function page_number_and_cursor_may_not_be_mixed()
    {
        $resource = new class(null) extends TestJsonResource
        {
            protected static $model = Post::class;
        };

        $this->assertValid($resource, ['page' => ['number' => 1]]);
        $this->assertValid($resource, ['page' => ['cursor' => 'abc']]);

        $this->assertInvalid($resource, ['page' => ['number' => 1, 'cursor' => 'abc']]);
    }

    private function assertInvalid(TestJsonResource $resource, $payload)
    {
        $this->assertTrue(
            (new SearchInputValidator($resource, $payload))->fails(),
            sprintf('Expected payload to be invalid. Used: %s', json_encode($payload))
        );
    }

    private function assertValid(TestJsonResource $resource, $payload)
    {
        $this->assertFalse(
            (new SearchInputValidator($resource, $payload))->fails(),
            sprintf('Expected payload to be valid. Used: %s', json_encode($payload))
        );
    }
}
