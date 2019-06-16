<?php

namespace WF\Batch\Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use WF\Batch\Exceptions\BatchException;
use WF\Batch\SaveHandler;
use WF\Batch\Tests\Models\Car;
use WF\Batch\Tests\Models\Company;
use WF\Batch\Tests\Models\User;

trait SaveTests
{
    protected $supportsTimezones = false;

    /** @test */
    public function updaters_must_implement_the_interface()
    {
        $this->expectException(BatchException::class);
        SaveHandler::registerUpdater('mariadb', SaveHandler::class);
    }

    /** @test */
    public function throws_when_inserting_a_bad_model_instance()
    {
        $this->expectException(BatchException::class);
        Car::batchSave([new User]);
    }

    /** @test */
    public function can_insert_models_with_different_array_keys_but_same_number_of_keys()
    {
        $companyOne = [
            'id' => 1,
            'name' => 'one',
            'address' => '123 some street',
            'address_2' => 'really important delivery details',
            'city' => 'my city',
            'country_code' => 'us',
        ];
        $companyTwo = [
            'id' => 2,
            'name' => 'two',
            'address' => '456 some street',
            'city' => 'my city',
            'zipcode' => 'my zipcode',
            'country_code' => 'us',
        ];

        Company::batchSave([$companyOne, $companyTwo]);

        $this->assertEquals($companyOne, Company::query()->find(1)->only(array_keys($companyOne)));
        $this->assertEquals($companyTwo, Company::query()->find(2)->only(array_keys($companyTwo)));
    }

    /** @test */
    public function model_properties_are_correctly_updated_on_save()
    {
        $c = new Car($this->newAttributes());

        $this->assertFalse($c->exists);
        $this->assertFalse($c->wasRecentlyCreated);

        Car::batchSave([$c]);

        $this->assertTrue($c->exists);
        $this->assertTrue($c->wasRecentlyCreated);

        $d = Car::query()->find(Car::query()->create($this->newAttributes())->getKey());

        $d->big_integer = 111;

        Car::batchSave([$d]);

        $this->assertTrue($d->exists);
        $this->assertFalse($d->wasRecentlyCreated);
    }

    /** @test */
    public function models_can_be_created_from_arrays()
    {
        Car::batchSave([$this->newAttributes()]);

        $this->assertEquals(1, Car::query()->count());

        $car = Car::query()->first();
        $car->wasRecentlyCreated = true;

        $this->assertEquals($this->carAttributes(), $this->formatCar($car));

        Car::batchSave([
            $this->newAttributes(),
            $this->newAttributes(),
            $this->newAttributes(),
            $this->newAttributes(),
        ]);
        $this->assertEquals(5, Car::query()->count());

        $car = Car::query()->latest()->first();
        $car->wasRecentlyCreated = true;

        $this->assertEquals($this->carAttributes(), $this->formatCar($car));
    }

    /** @test */
    public function models_can_be_created_from_iterables()
    {
        Car::batchSave([$this->newAttributes()]);

        $this->assertEquals(1, Car::query()->count());

        $car = Car::query()->first();
        $car->wasRecentlyCreated = true;

        $this->assertEquals($this->carAttributes(), $this->formatCar($car));

        Car::batchSave(new Collection([
            $this->newAttributes(),
            $this->newAttributes(),
            $this->newAttributes(),
            $this->newAttributes(),
        ]));
        $this->assertEquals(5, Car::query()->count());

        $car = Car::query()->latest()->first();
        $car->wasRecentlyCreated = true;

        $this->assertEquals($this->carAttributes(), $this->formatCar($car));
    }

    /** @test */
    public function models_can_be_created_from_new_models()
    {
        Car::batchSave([new Car($this->newAttributes())]);
        $this->assertEquals(1, Car::query()->count());
        $car = Car::query()->first();
        $car->wasRecentlyCreated = true;
        $this->assertEquals($this->carAttributes(), $this->formatCar($car));

        Car::batchSave([
            new Car($this->newAttributes()),
            new Car($this->newAttributes()),
            new Car($this->newAttributes()),
            $this->newAttributes(),
        ]);
        $this->assertEquals(5, Car::query()->count());
        $car = Car::query()->latest()->first();
        $car->wasRecentlyCreated = true;
        $this->assertEquals($this->carAttributes(), $this->formatCar($car));
    }

    /** @test */
    public function models_can_be_updated()
    {
        $count = 0;
        while ($count < 10) {
            Car::query()->create($this->newAttributes());
            $count++;
        }
        $first = Car::query()->first();
        $first->forceFill($this->updateAttributes());
        $car = Car::query()->create($this->newAttributes());
        $car->forceFill($this->updateAttributes());
        Car::batchSave([$car, $first, $this->newAttributes()]);
        $car->wasRecentlyCreated = false;
        $first->wasRecentlyCreated = false;
        $this->assertEquals(['big_increments' => 1] + $this->carUpdatedAttributes(), $this->formatCar($first->refresh()));
        $this->assertEquals(['big_increments' => 11] + $this->carUpdatedAttributes(), $this->formatCar($car->refresh()));
        $this->assertEquals(12, Car::query()->count());
    }

    /** @test */
    public function models_can_be_created_from_arrays_in_queue()
    {
        Queue::fake();

        $job = Car::batch([$this->newAttributes()])->save();
        $job->dispatch();

        $jobId = spl_object_id($job);

        Queue::assertPushed(SaveHandler::class, function (SaveHandler $job) use ($jobId) {
            return spl_object_id($job) === $jobId;
        });
    }

    protected function formatCar(Car $car) : array
    {
        return $car->attributesToArray();
    }

    protected function carAttributes() : array
    {
        $attributes = array_merge(
            $this->newAttributes(),
            ['created_at' => static::newDate(), 'updated_at' => static::newDate(), 'big_increments' => 1]
        );
        return (new Car($attributes))->attributesToArray();
    }

    protected function carUpdatedAttributes() : array
    {
        $attributes = array_merge(
            $this->updateAttributes(),
            ['created_at' => static::newDate(), 'updated_at' => static::newDate(), 'big_increments' => 1]
        );
        return (new Car($attributes))->attributesToArray();
    }

    protected function newAttributes() : array
    {
        return [
            // 'big_increments',
            'big_integer' => 1,
            'binary' => 'a',
            'boolean' => true,
            'char' => 'a',
            'date' => '2019-01-01',
            'datetime' => '2019-01-01 01:00:00',
            'datetime_tz' => $this->supportsTimezones
                ? '2019-01-01 01:00:00+01'
                : '2019-01-01 01:00:00',
            'decimal' => 1.20,
            'double' => 1.20,
            'enum' => 'easy',
            'float' => 1.20,
//            'geometry',
//            'geometry_collection',
            'integer' => 1,
            'ip_address' => '127.0.0.1',
            'json' => ['a' => 'b'],
            'jsonb' => ['a' => 'b'],
//            'line_string',
            'long_text' => 'hello',
            'mac_address' => '00:14:22:01:23:45',
            'medium_integer' => 1,
            'medium_text' => 'hello',
//            'multi_line_string',
//            'multi_point',
//            'multi_polygon',
//            'point',
//            'polygon',
            'small_integer' => 1,
            'string' => 'hello',
            'text' => 'hello',
            'time' => '01:00:00',
            'time_tz' => $this->supportsTimezones ? '01:00:00+01' : '01:00:00',
            'timestamp' => '2019-01-01 01:00:00',
            'timestamp_tz' => $this->supportsTimezones
                ? '2019-01-01 01:00:00+01'
                : '2019-01-01 01:00:00',
            'tiny_integer' => true,
            'unsigned_big_integer' => 1,
            'unsigned_decimal' => 1.20,
            'unsigned_integer' => 1,
            'unsigned_medium_integer' => 1,
            'unsigned_small_integer' => 1,
            'unsigned_tiny_integer' => true,
            'uuid' => '8106f52c-12ae-11e9-ab14-d663bd873d93',
            'year' => 2019,
        ];
    }

    protected function updateAttributes() : array
    {
        return [
            // 'big_increments',
            'big_integer' => 2,
            'binary' => 'b',
            'boolean' => false,
            'char' => 'b',
            'date' => '2019-01-02',
            'datetime' => '2019-01-02 01:00:00',
            'datetime_tz' => $this->supportsTimezones
                ? '2019-01-02 01:00:00+01'
                : '2019-01-02 01:00:00',
            'decimal' => 1.21,
            'double' => 1.21,
            'enum' => 'hard',
            'float' => 1.21,
//            'geometry',
//            'geometry_collection',
            'integer' => 1,
            'ip_address' => '127.0.0.2',
            'json' => ['a' => 'e', 'c' => 'd'],
            'jsonb' => ['a' => 'e', 'c' => 'd'],
//            'line_string',
            'long_text' => 'hello world',
            'mac_address' => '00:14:22:01:23:46',
            'medium_integer' => 2,
            'medium_text' => 'hello world',
//            'multi_line_string',
//            'multi_point',
//            'multi_polygon',
//            'point',
//            'polygon',
            'small_integer' => 2,
            'string' => 'hello world',
            'text' => 'hello world',
            'time' => '02:00:00',
            'time_tz' => $this->supportsTimezones ? '02:00:00+01' : '02:00:00',
            'timestamp' => '2019-01-02 01:00:00',
            'timestamp_tz' => $this->supportsTimezones
                ? '2019-01-02 01:00:00+01'
                : '2019-01-02 01:00:00',
            'tiny_integer' => false,
            'unsigned_big_integer' => 2,
            'unsigned_decimal' => 1.21,
            'unsigned_integer' => 2,
            'unsigned_medium_integer' => 2,
            'unsigned_small_integer' => 2,
            'unsigned_tiny_integer' => false,
            'uuid' => '83fd77ce-18b7-11e9-ab14-d663bd873d93',
            'year' => 2020,
        ];
    }
}
