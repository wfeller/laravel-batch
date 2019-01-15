<?php

namespace WF\Batch\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use WF\Batch\Tests\Models\Car;

abstract class TestCase extends BaseTestCase
{
    protected static $timezone = null;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Carbon::setTestNow(static::newDate());
    }

    public function setUp()
    {
        Carbon::setTestNow(static::newDate());
        parent::setUp();
        $this->runMigrations();
        $this->withFactories(__DIR__ . '/factories');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', $this->databaseDriver());
    }

    public function runMigrations()
    {
        Schema::dropIfExists((new Car)->getTable());
        Schema::create((new Car)->getTable(), function (Blueprint $table) {
            $table->bigIncrements('big_increments');
            $table->bigInteger('big_integer');
            $table->binary('binary');
            $table->boolean('boolean');
            $table->char('char', 255);
            $table->date('date');
            $table->dateTime('datetime');
            $table->dateTimeTz('datetime_tz');
            $table->decimal('decimal', 8, 2);
            $table->double('double', 8, 2);
            $table->enum('enum', ['easy', 'hard']);
            $table->float('float', 8, 2);
//            $table->geometry('geometry');
//            $table->geometryCollection('geometry_collection');
            $table->integer('integer');
            $table->ipAddress('ip_address');
            $table->json('json');
            $table->jsonb('jsonb');
//            $table->lineString('line_string');
            $table->longText('long_text');
            $table->macAddress('mac_address');
            $table->mediumInteger('medium_integer');
            $table->mediumText('medium_text');
//            $table->multiLineString('multi_line_string');
//            $table->multiPoint('multi_point');
//            $table->multiPolygon('multi_polygon');
//            $table->point('point');
//            $table->polygon('polygon');
            $table->smallInteger('small_integer');
            $table->string('string', 255);
            $table->text('text');
            $table->time('time');
            $table->timeTz('time_tz');
            $table->timestamp('timestamp')->nullable()->default(null);
            $table->timestampTz('timestamp_tz')->nullable()->default(null);
            $table->timestamps();
            $table->tinyInteger('tiny_integer');
            $table->unsignedBigInteger('unsigned_big_integer');
            $table->unsignedDecimal('unsigned_decimal');
            $table->unsignedInteger('unsigned_integer');
            $table->unsignedMediumInteger('unsigned_medium_integer');
            $table->unsignedSmallInteger('unsigned_small_integer');
            $table->unsignedTinyInteger('unsigned_tiny_integer');
            $table->uuid('uuid');
            $table->year('year');
        });
    }

    protected static function newDate() : Carbon
    {
        if (empty(static::$timezone)) {
            static::$timezone = system('date +%Z');
        }
        return Carbon::parse('2019-01-01 01:00:00', static::$timezone);
    }

    abstract protected function databaseDriver() : array;
}