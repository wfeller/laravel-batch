<?php

namespace WF\Batch\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use WF\Batch\Batch;
use WF\Batch\DeleteHandler;
use WF\Batch\Exceptions\BatchException;
use WF\Batch\Tests\Models\Company;
use WF\Batch\Tests\Models\ModelWithCustomEvents;
use WF\Batch\Tests\Models\TestModel;
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
    public function it_fires_model_events_when_deleting()
    {
        $existingModel = new TestModel;
        $existingModel->save();
        $existingModel = $existingModel->fresh();

        $events = Arr::shuffle(['deleting', 'deleted']);

        $listeningTo = array_shift($events);
        $notListeningTo = array_shift($events);

        TestModel::{$listeningTo}(fn () => null);

        $events = Event::fake();

        Batch::of(TestModel::class, [$existingModel])->delete()->now();

        $events->assertDispatched("eloquent.{$listeningTo}: ".TestModel::class);
        $events->assertNotDispatched("eloquent.{$notListeningTo}: ".TestModel::class);
    }

    /** @test */
    public function it_fires_custom_delete_model_events()
    {
        $existingModel = new ModelWithCustomEvents();
        $existingModel->save();
        $existingModel = $existingModel->fresh();

        $customEvents = Arr::shuffle(Arr::only($existingModel->getCustomEvents(), ['deleting', 'deleted', 'forceDeleted']));

        $listeningTo = [array_shift($customEvents), array_shift($customEvents)];
        $notListeningTo = $customEvents;

        Event::listen($listeningTo, fn () => null);

        $events = Event::fake();

        Batch::of(ModelWithCustomEvents::class, [$existingModel])->delete()->force()->now();

        foreach ($listeningTo as $event) {
            $events->assertDispatched($event);
        }

        foreach ($notListeningTo as $event) {
            $events->assertNotDispatched($event);
        }
    }

    /** @test */
    public function it_doesnt_fire_events_if_no_deleting_listeners()
    {
        $existingModel = new TestModel;
        $existingModel->save();
        $existingModel = $existingModel->fresh();

        $modelEvents = ['deleting', 'deleted', 'forceDeleting'];

        $events = Event::fake();

        Batch::of(TestModel::class, [$existingModel])->delete()->force()->now();

        foreach ($modelEvents as $event) {
            $events->assertNotDispatched("eloquent.{$event}: ".TestModel::class);
        }
    }

    /** @test */
    public function it_doesnt_fires_custom_deleting_model_events_if_no_listeners()
    {
        $existingModel = new ModelWithCustomEvents();
        $existingModel->save();
        $existingModel = $existingModel->fresh();

        $events = Event::fake();

        Batch::of(ModelWithCustomEvents::class, [$existingModel])->delete()->force()->now();

        foreach ((new ModelWithCustomEvents)->getCustomEvents() as $event) {
            $events->assertNotDispatched($event);
        }
    }

    /** @test */
    public function it_deletes_models_without_calling_now_to_perform_the_deletion()
    {
        $c = $this->createDeletableCompany();

        Company::newBatch([$c->getKey()])->delete();

        $this->assertSame(0, Company::query()->whereKey($c->getKey())->count());
    }

    /** @test */
    public function it_deletes_model_instances()
    {
        $one = $this->createDeletableCompany();
        $two = $this->createDeletableCompany();
        $this->assertEquals([$one->getKey(), $two->getKey()], Company::newBatch([$one, $two->getKey()])->delete()->now());

        $this->assertTrue($one->batchDeleting);
        $this->assertFalse($two->batchDeleting);
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
