<?php

namespace WatheqAlshowaiter\ModelRequiredFields\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use WatheqAlshowaiter\ModelRequiredFields\RequiredFields;

class Mother extends Model
{
    use RequiredFields;
}
