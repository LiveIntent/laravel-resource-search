<?php

namespace LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Http\Controllers;

use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Http\Resources\PostResource;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return PostResource::basicSearch();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search()
    {
        return PostResource::search();
    }
}
