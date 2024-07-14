<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use WatheqAlshowaiter\ModelRequiredFields\Models\Father;
use WatheqAlshowaiter\ModelRequiredFields\Models\Mother;
use WatheqAlshowaiter\ModelRequiredFields\Models\Son;
use WatheqAlshowaiter\ModelRequiredFields\Tests\TestCase;

class RequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_required_fields_for_parent_model()
    {
        $this->assertEquals([
            'name',
            'email',
        ], Father::getRequiredFields());

        $this->assertNotEquals([
            'email',
            'name',
        ], Father::getRequiredFields());
    }

    public function test_get_required_fields_for_another_parent_model()
    {
        $this->assertEquals([
            'uuid',
            'ulid',
        ], Mother::getRequiredFields());
    }

    public function test_get_required_fields_for_child_model()
    {
        $this->assertEquals([
            'parent_id',
        ], Son::getRequiredFields());
    }
}
