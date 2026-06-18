<?php

namespace WatheqAlshowaiter\ModelFields\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Uncle extends Model
{
    protected $attributes = [
        'attribute_field' => 'default-value',
    ];

    protected $dispatchesEvents = [
        'creating' => UncleCreating::class, // fill `event_creating` field
        'saving' => UncleSaving::class, // fill `event_saving` field
    ];

    protected static function boot(): void
    {
        parent::boot();

        if (method_exists(static::class, 'whenBooted')) {
            static::whenBooted(function () {
                static::registerDefaultFieldEvents();
            });

            return;
        }

        static::registerDefaultFieldEvents();
    }

    protected static function registerDefaultFieldEvents(): void
    {
        static::observe(UncleObserver::class);

        static::creating(function ($model) {
            $model->boot_creating = 'creating';
        });

        static::saving(function ($model) {
            $model->boot_saving = 'saving';
        });
    }
}

class UncleObserver
{
    public function creating(Uncle $model): void
    {
        $model->observer_creating = 'creating';
    }

    public function saving(Uncle $model): void
    {
        $model->observer_saving = 'saving';
    }
}

class UncleCreating
{
    public Uncle $model;

    public function __construct(Uncle $model)
    {
        $this->model = $model;
    }
}

class UncleSaving
{
    public Uncle $model;

    public function __construct(Uncle $model)
    {
        $this->model = $model;
    }
}
