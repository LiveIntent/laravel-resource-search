<?php

namespace LiveIntent\LaravelResourceSearch\Tests;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use LiveIntent\LaravelResourceSearch\LaravelResourceSearchServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LiveIntent\\LaravelResourceSearch\\Tests\\Fixtures\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->registerResponseMacros();
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    protected function defineRoutes($router)
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/Fixtures/routes/api.php');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelResourceSearchServiceProvider::class,
        ];
    }

    /**
     * Bind a new request to the app container with the given payload.
     */
    protected function request(array $payload = []): Request
    {
        return tap(
            new Request($payload),
            fn ($request) => $this->app->instance('request', $request)
        );
    }

    /**
     * Register macros on the TestResponse class.
     */
    public function registerResponseMacros()
    {
        TestResponse::macro('assertValidationErrors', function (array|string $invalidFields): TestResponse {
            /** @var TestResponse $this */
            return $this->assertStatus(422)->assertJsonValidationErrors($invalidFields);
        });

        TestResponse::macro('assertResponseData', function (array $data): TestResponse {
            /** @var TestResponse $this */
            return $this->assertOk()->assertJson(['data' => $data]);
        });

        TestResponse::macro('assertResponseCount', function (int $count): TestResponse {
            /** @var TestResponse $this */
            return $this->assertOk()->assertJsonCount($count, 'data');
        });
    }
}
