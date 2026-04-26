<?php

namespace WF\Batch\Tests\Unit;

use Illuminate\Support\Str;
use WF\Batch\Batch;
use WF\Batch\Tests\Models\UuidCompany;

trait UuidTests
{
    public function test_uuid_models_generate_ids_on_insert()
    {
        $models = [
            new UuidCompany(['name' => 'one', 'address' => 'a', 'city' => 'b', 'country_code' => 'c']),
            new UuidCompany(['name' => 'two', 'address' => 'd', 'city' => 'e', 'country_code' => 'f']),
        ];

        Batch::of(UuidCompany::class, $models)->save()->now();

        foreach ($models as $model) {
            $this->assertTrue($model->exists);
            $this->assertTrue(Str::isUuid($model->getKey()));
        }

        $this->assertEquals(2, UuidCompany::query()->count());
    }

    public function test_uuid_models_can_be_updated()
    {
        $model = new UuidCompany(['name' => 'original', 'address' => 'a', 'city' => 'b', 'country_code' => 'c']);

        Batch::of(UuidCompany::class, [$model])->save()->now();

        $model->name = 'updated';

        Batch::of(UuidCompany::class, [$model])->save()->now();

        $this->assertEquals('updated', $model->fresh()->name);
        $this->assertEquals(1, UuidCompany::query()->count());
    }

    public function test_uuid_models_can_be_deleted()
    {
        $model = new UuidCompany(['name' => 'to_delete', 'address' => 'a', 'city' => 'b', 'country_code' => 'c']);

        Batch::of(UuidCompany::class, [$model])->save()->now();

        Batch::of(UuidCompany::class, [$model])->delete()->now();

        $this->assertEquals(0, UuidCompany::query()->count());
    }

    public function test_uuid_models_with_chunking()
    {
        $models = [];
        for ($i = 0; $i < 5; $i++) {
            $models[] = new UuidCompany(['name' => "company_{$i}", 'address' => 'a', 'city' => 'b', 'country_code' => 'c']);
        }

        Batch::of(UuidCompany::class, $models)->batchSize(2)->save()->now();

        $this->assertEquals(5, UuidCompany::query()->count());
    }
}
