<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use WatheqAlshowaiter\ModelRequiredFields\Models\Father;
use WatheqAlshowaiter\ModelRequiredFields\Models\Mother;
use WatheqAlshowaiter\ModelRequiredFields\Models\Son;
use WatheqAlshowaiter\ModelRequiredFields\Tests\TestCase;

class RequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    // todo: remove all getRequiredFieldsForOlderVersions()

    public function test_get_required_fields_for_parent_model()
    {
        dump(
            Illuminate\Support\Facades\DB::connection()->getDriverName()
        );

        $this->assertEquals([
            'name',
            'email',
        ], Father::getRequiredFields());

        $this->assertEquals([
            'name',
            'email',
        ], Father::getRequiredFieldsForOlderVersions());
    }

    public function test_get_required_fields_in_order()
    {
        $this->assertNotEquals([
            'email',
            'name',
        ], Father::getRequiredFields());

        $this->assertNotEquals([
            'email',
            'name',
        ], Father::getRequiredFieldsForOlderVersions());
    }

    public function test_get_required_fields_for_another_parent_model()
    {
        $this->assertEquals([
            'uuid',
            'ulid',
        ], Mother::getRequiredFields());

        $this->assertEquals([
            'uuid',
            'ulid',
        ], Mother::getRequiredFieldsForOlderVersions());
    }

    public function test_get_required_fields_for_child_model()
    {
        $this->assertEquals([
            'father_id',
        ], Son::getRequiredFields());

        $this->assertEquals([
            'father_id',
        ], Son::getRequiredFieldsForOlderVersions());
    }
}
