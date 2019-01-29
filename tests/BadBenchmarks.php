<?php

namespace WF\Batch\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WF\Batch\Tests\Models\User;

class BadBenchmarks extends TestCase
{
    // SQLITE
//    protected function databaseDriver() : array
//    {
//        return [
//            'driver'   => 'sqlite',
//            'database' => ':memory:',
//            'prefix'   => '',
//        ];
//    }

    // MYSQL
//    protected function databaseDriver() : array
//    {
//        return [
//            'driver' => 'mysql',
//            'host' => env('DB_HOST', '127.0.0.1'),
//            'port' => env('DB_PORT', '3306'),
//            'database' => env('DB_DATABASE', 'forge'),
//            'username' => env('DB_USERNAME', 'root'),
//            'password' => env('DB_PASSWORD', 'password'),
//            'unix_socket' => env('DB_SOCKET', ''),
//            'charset' => 'utf8mb4',
//            'collation' => 'utf8mb4_unicode_ci',
//            'prefix' => '',
//            'prefix_indexes' => true,
//            'strict' => true,
//            'engine' => null,
//        ];
//    }

    // POSTGRES
    protected function databaseDriver() : array
    {
        return [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    /** @test */
    public function laravel_insert()
    {
        dump('laravel batch insert');
        $models = $this->giveArrayAttributes();
        $start = microtime(true);
        foreach (array_chunk($models, 500) as $chunk) {
            User::query()->insert($chunk);
        }
        $time_elapsed_secs = microtime(true) - $start;
        dump($time_elapsed_secs . ' seconds');
        dump(User::query()->first()->event ?? 'no event');

        $this->assertTrue(true);
    }

    /** @test */
    public function laravel_foreach_insert()
    {
        dump('laravel foreach insert');
        $models = $this->giveArrayAttributes();
        $start = microtime(true);
        foreach ($models as $model) {
            User::query()->create($model);
        }
        $time_elapsed_secs = microtime(true) - $start;
        dump($time_elapsed_secs . ' seconds');
        dump(User::query()->first()->event ?? 'no event');

        $this->assertTrue(true);
    }

    /** @test */
    public function batch_insert()
    {
        dump('new batch insert');
        $models = $this->giveArrayAttributes();
        $start = microtime(true);
        User::batchSave($models);
        $time_elapsed_secs = microtime(true) - $start;
        dump($time_elapsed_secs . ' seconds');
        dump(User::query()->first()->event ?? 'no event');

        $this->assertTrue(true);
    }

    private function giveArrayAttributes(int $size = 2500) : array
    {
        $models = [];
        while ($size >= 1) {
            $models[] = [
                'name' => 'Robert Green',
                'telephone' => '777 888 999',
                'email' => 'robert.green@example.com',
                'password' => bcrypt('some value'),
            ];
            $size--;
        }
        return $models;
    }

    public function runMigrations()
    {
        Schema::dropIfExists((new User)->getTable());
        Schema::create((new User)->getTable(), function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');
            $table->string('telephone');
            $table->string('email');
            $table->string('event')->nullable();
            $table->string('password');

            $table->timestamps();
            $table->rememberToken();

            $table->softDeletes();
            $table->timestamp('email_verified_at')->nullable();
        });
    }
}
