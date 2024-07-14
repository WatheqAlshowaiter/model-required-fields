<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use WatheqAlshowaiter\ModelRequiredFields\Models\AnotherParentTestModel;
use WatheqAlshowaiter\ModelRequiredFields\Models\ChildTestModel;
use WatheqAlshowaiter\ModelRequiredFields\Models\ParentTestModel;
use WatheqAlshowaiter\ModelRequiredFields\Tests\TestCase;

class RequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_required_fields_for_parent_model()
    {
        $this->assertEquals([
            'name',
            'email',
        ], ParentTestModel::getRequiredFields());

        $this->assertNotEquals([
            'email',
            'name',
        ], ParentTestModel::getRequiredFields());
    }

    public function test_get_required_fields_for_another_parent_model()
    {
        $this->assertEquals([
            'uuid',
            'ulid',
        ], AnotherParentTestModel::getRequiredFields());
    }

    public function test_get_required_fields_for_child_model()
    {
        $this->assertEquals([
            'parent_id',
        ], ChildTestModel::getRequiredFields());
    }
}
