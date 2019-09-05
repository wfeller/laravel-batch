<?php

namespace WF\Batch\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use WF\Batch\DeleteHandler;
use WF\Batch\Exceptions\BatchException;
use WF\Batch\Tests\Models\Company;
use WF\Batch\Tests\Models\User;

trait DeleteTests
{
    /** @test */
    public function inexistant_ids_dont_get_deleted()
    {
        $this->assertCount(0, Company::newBatch([1111, 2222, 3333, 4444])->delete()->now());
    }

    /** @test */
    public function it_deletes_models_by_id()
    {
        $c = $this->createDeletableCompany();
        $this->assertEquals([$c->getKey()], Company::newBatch([$c->getKey()])->delete()->now());
    }

    /** @test */
    public function it_deletes_model_instances()
    {
        $c = $this->createDeletableCompany();
        $this->assertEquals([$c->getKey()], Company::newBatch([$c])->delete()->now());
    }

    /** @test */
    public function it_ignores_new_model_instances()
    {
        $this->assertCount(0, Company::newBatch([new Company, new Company])->delete()->now());
    }

    /** @test */
    public function it_throws_when_deleting_different_model_classes()
    {
        $this->expectException(BatchException::class);
        Company::newBatch([new User])->delete()->now();
    }

    /** @test */
    public function models_can_be_deleted_in_queue()
    {
        Queue::fake();

        $job = Company::newBatch([$this->createDeletableCompany()])->delete();
        $job->dispatch();

        $jobId = spl_object_id($job);

        Queue::assertPushed(DeleteHandler::class, function (DeleteHandler $job) use ($jobId) {
            return spl_object_id($job) === $jobId;
        });
    }

    private function createDeletableCompany() : Company
    {
        return Company::query()->create(['name' => 'a', 'address' => 'b', 'city' => 'c', 'country_code' => 'd']);
    }
}
