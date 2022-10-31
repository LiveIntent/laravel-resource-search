<?php

namespace LiveIntent\LaravelResourceSearch\Tests;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use LiveIntent\LaravelResourceSearch\LaravelResourceSearchServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Set up the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LiveIntent\\LaravelResourceSearch\\Tests\\Fixtures\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->registerResponseMacros();
    }

    /**
     * Refresh an in-memory database.
     */
    protected function refreshInMemoryDatabase()
    {
        $this->artisan('migrate', [
            '--path' => __DIR__.'/Fixtures/database/migrations',
            '--realpath' => true,
        ]);
    }

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate', [
                '--path' => __DIR__.'/Fixtures/database/migrations',
                '--realpath' => true,
            ]);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Define the test application routes.
     */
    protected function defineRoutes($router)
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/Fixtures/routes/api.php');
    }

    /**
     * Set up the test application environment.
     */
    protected function defineEnvironment($app)
    {
        $driver = env('DB_CONNECTION') === 'testing' ? 'sqlite' : env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', array_filter([
            'driver' => $driver,
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => env('DB_DATABASE', ':memory:'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'prefix' => '',
        ]));
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
