<?php

namespace WatheqAlshowaiter\ModelFields\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use ReflectionException;

/**
 * Here are the shared logic across multiple files, now are FieldsService & ModelFieldsServiceProvider
 */
class Helpers
{
    /**
     * @return bool
     */
    public static function isLaravelVersionLessThan10()
    {
        return version_compare(App::version(), '10.0', '<');
    }

    /**
     * @return string[]
     */
    public static function getModelDefaultAttributes($model)
    {
        return array_keys(static::getModelAttributes($model));
    }

    public static function getTableFromThisModel($model)
    {
        $table = static::getModelWithoutBooting($model)->getTable();

        return str_replace('.', '__', $table);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public static function getModelAttributes($model)
    {
        return static::getModelWithoutBooting($model)->getAttributes();
    }

    /**
     * Get fields that are automatically filled by model observers/events
     * during 'creating' and 'saving' events
     *
     *
     * @return string[]
     */
    public static function getObserverFilledFields($modelOrClass)
    {
        if ($modelOrClass instanceof Model) {
            $model = clone $modelOrClass;
            $modelClass = get_class($modelOrClass);
        } else {
            $model = new $modelOrClass;
            $modelClass = $modelOrClass;
        }

        // ensure clean baseline
        $model->syncOriginal();

        // fire the creating events (observer + model booted events)
        Event::dispatch("eloquent.creating: {$modelClass}", $model);
        Event::dispatch("eloquent.saving: {$modelClass}", $model);

        $dirty = $model->getDirty();
        $dirtyNoNull = array_filter($dirty); // exclude null values

        return array_keys($dirtyNoNull);
    }

    /**
     * Get a model for passive metadata inspection without starting its boot cycle.
     *
     * @return object
     * @throws ReflectionException
     */
    protected static function getModelWithoutBooting($modelOrClass)
    {
        if ($modelOrClass instanceof Model) {
            return $modelOrClass;
        }

        $reflection = new ReflectionClass($modelOrClass);

        return $reflection->newInstanceWithoutConstructor();
    }
}
