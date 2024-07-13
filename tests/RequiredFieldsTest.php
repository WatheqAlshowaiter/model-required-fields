<?php

use WatheqAlshowaiter\ModelRequiredFields\Models\AnotherParentTestModel;
use WatheqAlshowaiter\ModelRequiredFields\Models\ChildTestModel;
use WatheqAlshowaiter\ModelRequiredFields\Models\ParentTestModel;

it('get required fields for parent model', function () {
    expect(ParentTestModel::getRequiredFields())->toBe([
        'name',
        'email',
    ]);
});

it('get required fields for another parent model', function () {
    expect(AnotherParentTestModel::getRequiredFields())->toBe([
        'uuid',
        'ulid',
    ]);
});

it('get required fields for child model', function () {
    expect(ChildTestModel::getRequiredFields())->toBe([
        'parent_id',
    ]);

    expect(true)->toBe(false);
});
