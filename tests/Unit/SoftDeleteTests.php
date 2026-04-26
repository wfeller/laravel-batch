<?php

namespace WF\Batch\Tests\Unit;

use WF\Batch\Batch;
use WF\Batch\Tests\Models\SoftDeletableCompany;

trait SoftDeleteTests
{
    public function test_soft_delete_sets_deleted_at()
    {
        $company = SoftDeletableCompany::query()->create([
            'name' => 'test', 'address' => 'a', 'city' => 'b', 'country_code' => 'c',
        ]);

        Batch::of(SoftDeletableCompany::class, [$company])->delete()->now();

        $this->assertNotNull($company->fresh()->deleted_at);
        $this->assertEquals(0, SoftDeletableCompany::query()->count());
        $this->assertEquals(1, SoftDeletableCompany::withTrashed()->count());
    }

    public function test_force_delete_removes_model()
    {
        $company = SoftDeletableCompany::query()->create([
            'name' => 'test', 'address' => 'a', 'city' => 'b', 'country_code' => 'c',
        ]);

        Batch::of(SoftDeletableCompany::class, [$company])->delete()->force()->now();

        $this->assertEquals(0, SoftDeletableCompany::withTrashed()->count());
    }

    public function test_soft_delete_multiple_models()
    {
        $companies = [];
        for ($i = 0; $i < 3; $i++) {
            $companies[] = SoftDeletableCompany::query()->create([
                'name' => "test_{$i}", 'address' => 'a', 'city' => 'b', 'country_code' => 'c',
            ]);
        }

        Batch::of(SoftDeletableCompany::class, $companies)->delete()->now();

        $this->assertEquals(0, SoftDeletableCompany::query()->count());
        $this->assertEquals(3, SoftDeletableCompany::withTrashed()->count());
    }

    public function test_force_delete_with_chunking()
    {
        $companies = [];
        for ($i = 0; $i < 5; $i++) {
            $companies[] = SoftDeletableCompany::query()->create([
                'name' => "test_{$i}", 'address' => 'a', 'city' => 'b', 'country_code' => 'c',
            ]);
        }

        Batch::of(SoftDeletableCompany::class, $companies)->batchSize(2)->delete()->force()->now();

        $this->assertEquals(0, SoftDeletableCompany::withTrashed()->count());
    }
}
