<?php

namespace LiveIntent\LaravelResourceSearch;

use Spatie\LaravelPackageTools\Package;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LiveIntent\LaravelResourceSearch\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class LaravelResourceSearchServiceProvider extends PackageServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        parent::boot();

        $this->registerPaginatorMacro();
    }

    /**
     * Register the api paginator.
     */
    private function registerPaginatorMacro()
    {
        $macroName = config('resource-search.pagination.medthod_name', 'apiPaginate');
        $macro = Paginator::buildMacro();

        EloquentBuilder::macro($macroName, $macro);
        BaseBuilder::macro($macroName, $macro);
    }

    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-resource-search')
            ->hasConfigFile('resource-search');
    }
}
