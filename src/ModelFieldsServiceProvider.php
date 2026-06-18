<?php

namespace WatheqAlshowaiter\ModelFields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use WatheqAlshowaiter\ModelFields\Console\ModelFieldsCommand;
use WatheqAlshowaiter\ModelFields\Exceptions\UnsupportedDatabaseDriverException;
use WatheqAlshowaiter\ModelFields\Support\Helpers;

class ModelFieldsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'model-fields');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config.php' => config_path('model-fields.php'),
            ], 'config');

            $this->commands([
                ModelFieldsCommand::class,
            ]);

            if ($this->app->environment() === 'testing') {
                // This migration works only in the package test
                $this->loadMigrationsFrom(__DIR__.'/../tests/database/migrations');
            }
        }

        if (config('model-fields.enable_macro', true)) {

            Builder::macro('allFields', function () {
                if (Helpers::isLaravelVersionLessThan10()) {
                    return $this->allFieldsForOlderVersions();
                }

                $table = Helpers::getTableFromThisModel($this->getModel());

                return collect(Schema::getColumns($table))
                    ->pluck('name')
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('allFieldsForOlderVersions', function () {
                $databaseDriver = DB::connection()->getDriverName();

                switch ($databaseDriver) {
                    case 'sqlite':
                        return $this->allFieldsForSqlite();
                    case 'mysql':
                    case 'mariadb':
                        return $this->allFieldsForMysqlAndMariaDb();
                    case 'pgsql':
                        return $this->allFieldsForPostgres();
                    case 'sqlsrv':
                        return $this->allFieldsForSqlServer();
                    default:
                        throw new UnsupportedDatabaseDriverException('Unsupported database driver.');
                }
            });

            Builder::macro('allFieldsForSqlite', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('allFieldsForMysqlAndMariaDb', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang SQLite */ '
            SELECT
                COLUMN_NAME AS name
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
            ORDER BY
                ORDINAL_POSITION ASC',
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('allFieldsForPostgres', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang PostgreSQL */ '
            SELECT
                is_nullable AS nullable,
                column_name AS name,
                column_default AS default
            FROM
                information_schema.columns
            WHERE
                table_name = ?
            ORDER BY
                ordinal_position ASC',
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->pluck('name')
                    ->unique()
                    ->toArray();
            });

            Builder::macro('allFieldsForSqlServer', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang TSQL */ "
            SELECT
                COLUMN_NAME AS name,
                DATA_TYPE AS type,
                CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS nullable,
                COLUMN_DEFAULT AS [default]
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_SCHEMA = SCHEMA_NAME()
                AND TABLE_NAME = ?
            ORDER BY
                ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('requiredFields', function () {
                if (Helpers::isLaravelVersionLessThan10()) {
                    return $this->requiredFieldsForOlderVersions();
                }

                $table = Helpers::getTableFromThisModel($this->getModel());

                $modelDefaultAttributes = Helpers::getModelDefaultAttributes($this->getModel());
                $observerDefaultAttributes = Helpers::getObserverFilledFields($this->getModel());

                $primaryIndex = $this->primaryField();

                $table = Helpers::getTableFromThisModel($this->getModel());

                return collect(Schema::getColumns($table))
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->reject(function ($column) use ($primaryIndex) {
                        return
                            $column['nullable'] ||
                            $column['default'] != null ||
                            (in_array($column['name'], $primaryIndex));
                    })
                    ->reject(function ($column) use ($modelDefaultAttributes) {
                        return in_array($column['name'], $modelDefaultAttributes);
                    })
                    ->reject(function ($column) use ($observerDefaultAttributes) {
                        return in_array($column['name'], $observerDefaultAttributes);
                    })
                    ->pluck('name')
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('requiredFieldsForOlderVersions', function () {
                $databaseDriver = DB::connection()->getDriverName();

                switch ($databaseDriver) {
                    case 'sqlite':
                        return $this->requiredFieldsForSqlite();
                    case 'mysql':
                    case 'mariadb':
                        return $this->requiredFieldsForMysqlAndMariaDb();
                    case 'pgsql':
                        return $this->requiredFieldsForPostgres();
                    case 'sqlsrv':
                        return $this->requiredFieldsForSqlServer();
                    default:
                        throw new UnsupportedDatabaseDriverException('Unsupported database driver.');
                }
            });

            Builder::macro('primaryField', function () {
                if (Helpers::isLaravelVersionLessThan10()) {
                    return $this->primaryFieldForOlderVersions();
                }

                $table = Helpers::getTableFromThisModel($this->getModel());

                return collect(Schema::getIndexes($table))
                    ->filter(function ($index) {
                        return $index['primary'];
                    })
                    ->pluck('columns')
                    ->flatten()
                    ->toArray();
            });

            Builder::macro('nullableFields', function () {
                if (Helpers::isLaravelVersionLessThan10()) {
                    return $this->nullableFieldsForOlderVersions();
                }

                $table = Helpers::getTableFromThisModel($this->getModel());

                return collect(Schema::getColumns($table))
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->filter(function ($column) {
                        return $column['nullable'];
                    })
                    ->pluck('name')
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('nullableFieldsForOlderVersions', function () {
                $databaseDriver = DB::connection()->getDriverName();

                switch ($databaseDriver) {
                    case 'sqlite':
                        return $this->nullableFieldsForSqlite();
                    case 'mysql':
                    case 'mariadb':
                        return $this->nullableFieldsForMysqlAndMariaDb();
                    case 'pgsql':
                        return $this->nullableFieldsForPostgres();
                    case 'sqlsrv':
                        return $this->nullableFieldsForSqlServer();
                    default:
                        throw new UnsupportedDatabaseDriverException('Unsupported database driver.');
                }
            });

            Builder::macro('nullableFieldsForSqlite', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return ! $column['notnull'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('nullableFieldsForMysqlAndMariaDb', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang SQLite */ "
                    SELECT
                        COLUMN_NAME AS name,
                        COLUMN_TYPE AS type,
                        IF(IS_NULLABLE = 'YES', 1, 0) AS nullable,
                        COLUMN_DEFAULT AS `default`,
                        IF(COLUMN_KEY = 'PRI', 1, 0) AS `primary`
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->filter(function ($column) {
                        return $column['nullable'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('nullableFieldsForPostgres', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang PostgreSQL */ '
                    SELECT
                        is_nullable AS nullable,
                        column_name AS name
                    FROM
                        information_schema.columns
                    WHERE
                        table_name = ?
                    ORDER BY
                        ordinal_position ASC',
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return $column['nullable'] == 'YES';
                    })
                    ->pluck('name')
                    ->unique()
                    ->toArray();
            });

            Builder::macro('nullableFieldsForSqlServer', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang TSQL */ "
                    SELECT
                        COLUMN_NAME AS name,
                        DATA_TYPE AS type,
                        CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS nullable,
                        COLUMN_DEFAULT AS [default]
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = SCHEMA_NAME()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return $column['nullable'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('databaseDefaultFields', function () {
                if (Helpers::isLaravelVersionLessThan10()) {
                    return $this->databaseDefaultFieldsForOlderVersions();
                }

                // we should exclude primary keys in postgres
                // because the consider primary keys as
                // default sequences values
                $primaryField = $this->primaryField();

                $table = Helpers::getTableFromThisModel($this->getModel());

                return collect(Schema::getColumns($table))
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->filter(function ($column) use ($primaryField) {
                        return $column['default'] !== null && ! (in_array($column['name'], $primaryField));
                    })
                    ->pluck('name')
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('databaseDefaultFieldsForOlderVersions', function () {
                $databaseDriver = DB::connection()->getDriverName();

                switch ($databaseDriver) {
                    case 'sqlite':
                        return $this->databaseDefaultFieldsForSqlite();
                    case 'mysql':
                    case 'mariadb':
                        return $this->databaseDefaultFieldsForMysqlAndMariaDb();
                    case 'pgsql':
                        return $this->databaseDefaultFieldsForPostgres();
                    case 'sqlsrv':
                        return $this->databaseDefaultFieldsForSqlServer();
                    default:
                        throw new UnsupportedDatabaseDriverException('Unsupported database driver.');
                }
            });

            Builder::macro('databaseDefaultFieldsForSqlite', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());
                $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return $column['dflt_value'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('databaseDefaultFieldsForMysqlAndMariaDb', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang SQLite */ "
                    SELECT
                        COLUMN_NAME AS name,
                        COLUMN_TYPE AS type,
                        IF(IS_NULLABLE = 'YES', 1, 0) AS nullable,
                        COLUMN_DEFAULT AS `default`,
                        IF(COLUMN_KEY = 'PRI', 1, 0) AS `primary`
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->filter(function ($column) {
                        return $column['default'] !== null;
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('databaseDefaultFieldsForPostgres', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $primaryIndex = DB::select(/** @lang PostgreSQL */ "
                    SELECT
                        ic.relname AS name,
                        string_agg(a.attname, ',' ORDER BY indseq.ord) AS columns,
                        am.amname AS type,
                        i.indisunique AS unique,
                        i.indisprimary AS primary
                    FROM
                        pg_index i
                        JOIN pg_class tc ON tc.oid = i.indrelid
                        JOIN pg_namespace tn ON tn.oid = tc.relnamespace
                        JOIN pg_class ic ON ic.oid = i.indexrelid
                        JOIN pg_am am ON am.oid = ic.relam
                        JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS indseq(num, ord) ON true
                        LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid
                        AND a.attnum = indseq.num
                    WHERE
                        tc.relname = ?
                        AND tn.nspname = CURRENT_SCHEMA
                    GROUP BY
                        ic.relname,
                        am.amname,
                        i.indisunique,
                        i.indisprimary;
                ", [$table]);

                $primaryIndex = collect($primaryIndex)
                    ->map(function ($index) {
                        return (array) $index;
                    })
                    ->filter(function ($index) {
                        return $index['primary'];
                    })
                    ->pluck('columns')
                    ->flatten()
                    ->toArray();

                $queryResult = DB::select(
                    /** @lang PostgreSQL */ '
                    SELECT
                        is_nullable AS nullable,
                        column_name AS name,
                        column_default AS default
                    FROM
                        information_schema.columns
                    WHERE
                        table_name = ?
                    ORDER BY
                        ordinal_position ASC',
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) use ($primaryIndex) {
                        return $column['default'] !== null && ! (in_array($column['name'], $primaryIndex));
                    })
                    ->pluck('name')
                    ->unique()
                    ->toArray();
            });

            Builder::macro('databaseDefaultFieldsForSqlServer', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang TSQL */ "
                    SELECT
                        COLUMN_NAME AS name,
                        DATA_TYPE AS type,
                        CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS nullable,
                        COLUMN_DEFAULT AS [default]
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = SCHEMA_NAME()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return $column['default'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('applicationDefaultFields', function () {
                $model = $this->getModel();
                $attributes = collect(Helpers::getModelAttributes($model))->filter()->keys()->toArray(); // ignore null values
                $observerDefaultAttributes = Helpers::getObserverFilledFields($model);

                $allFields = $this->allFields();

                return collect($attributes)
                    ->merge($observerDefaultAttributes)
                    ->filter(function ($field) use ($allFields) {
                        return in_array($field, $allFields);
                    })
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('defaultFields', function () {
                $databaseDefaults = $this->databaseDefaultFields();
                $applicationDefaults = $this->applicationDefaultFields();

                return collect($applicationDefaults)
                    ->merge($databaseDefaults)
                    ->unique()
                    ->values()
                    ->toArray();
            });

            Builder::macro('primaryFieldForOlderVersions', function () {
                $databaseDriver = DB::connection()->getDriverName();

                switch ($databaseDriver) {
                    case 'sqlite':
                        return $this->primaryFieldForSqlite();
                    case 'mysql':
                    case 'mariadb':
                        return $this->primaryFieldForMysqlAndMariaDb();
                    case 'pgsql':
                        return $this->primaryFieldForPostgres();
                    case 'sqlsrv':
                        return $this->primaryFieldForSqlServer();
                    default:
                        throw new UnsupportedDatabaseDriverException('Unsupported database driver.');
                }
            });

            Builder::macro('primaryFieldForSqlite', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->filter(function ($column) {
                        return $column['pk'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('primaryFieldForMysqlAndMariaDb', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $queryResult = DB::select(
                    /** @lang MySQL */ "
                    SELECT
                        COLUMN_NAME AS name,
                        COLUMN_TYPE AS type,
                        IF(IS_NULLABLE = 'YES', 1, 0) AS nullable,
                        COLUMN_DEFAULT AS `default`,
                        IF(COLUMN_KEY = 'PRI', 1, 0) AS `primary`
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->filter(function ($column) {
                        return $column['primary'];
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('primaryFieldForPostgres', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $primaryIndex = DB::select(/** @lang PostgreSQL */ "
                    SELECT
                        ic.relname AS name,
                        string_agg(a.attname, ',' ORDER BY indseq.ord) AS columns,
                        am.amname AS type,
                        i.indisunique AS unique,
                        i.indisprimary AS primary
                    FROM
                        pg_index i
                        JOIN pg_class tc ON tc.oid = i.indrelid
                        JOIN pg_namespace tn ON tn.oid = tc.relnamespace
                        JOIN pg_class ic ON ic.oid = i.indexrelid
                        JOIN pg_am am ON am.oid = ic.relam
                        JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS indseq(num, ord) ON true
                        LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid
                        AND a.attnum = indseq.num
                    WHERE
                        tc.relname = ?
                        AND tn.nspname = CURRENT_SCHEMA
                    GROUP BY
                        ic.relname,
                        am.amname,
                        i.indisunique,
                        i.indisprimary;
                ", [$table]);

                return collect($primaryIndex)
                    ->map(function ($index) {
                        return (array) $index;
                    })
                    ->filter(function ($index) {
                        return $index['primary'];
                    })
                    ->pluck('columns')
                    ->flatten()
                    ->toArray();
            });

            Builder::macro('primaryFieldForSqlServer', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());

                $primaryIndex = DB::select(/** @lang TSQL */ '
                    SELECT
                        COL_NAME(ic.object_id, ic.column_id) AS [column]
                    FROM
                        sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic
                            ON i.object_id = ic.object_id
                            AND i.index_id = ic.index_id
                        INNER JOIN sys.objects AS o
                            ON i.object_id = o.object_id
                    WHERE
                        i.is_primary_key = 1
                        AND o.name = ?
                        AND SCHEMA_NAME(o.schema_id) = schema_name()', [$table]);

                return collect($primaryIndex)
                    ->pluck('column')
                    ->toArray();
            });

            Builder::macro('requiredFieldsForSqlite', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());
                $modelDefaultAttributes = Helpers::getModelDefaultAttributes($this->getModel());
                $observerDefaultAttributes = Helpers::getObserverFilledFields($this->getModel());

                $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->reject(function ($column) {
                        return $column['pk']
                            || $column['dflt_value'] != null
                            || ! $column['notnull'];
                    })
                    ->reject(function ($column) use ($modelDefaultAttributes) {
                        return in_array($column['name'], $modelDefaultAttributes);
                    })
                    ->reject(function ($column) use ($observerDefaultAttributes) {
                        return in_array($column['name'], $observerDefaultAttributes);
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('requiredFieldsForMysqlAndMariaDb', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());
                $modelDefaultAttributes = Helpers::getModelDefaultAttributes($this->getModel());
                $observerDefaultAttributes = Helpers::getObserverFilledFields($this->getModel());

                $queryResult = DB::select(
                    /** @lang SQLite */ "
                    SELECT
                        COLUMN_NAME AS name,
                        COLUMN_TYPE AS type,
                        IF(IS_NULLABLE = 'YES', 1, 0) AS nullable,
                        COLUMN_DEFAULT AS `default`,
                        IF(COLUMN_KEY = 'PRI', 1, 0) AS `primary`
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->map(function ($column) { // specific to mariadb
                        if ($column['default'] == 'NULL') {
                            $column['default'] = null;
                        }

                        return $column;
                    })
                    ->reject(function ($column) {
                        return $column['primary']
                            || $column['default'] != null
                            || $column['nullable'];
                    })
                    ->reject(function ($column) use ($modelDefaultAttributes) {
                        return in_array($column['name'], $modelDefaultAttributes);
                    })
                    ->reject(function ($column) use ($observerDefaultAttributes) {
                        return in_array($column['name'], $observerDefaultAttributes);
                    })
                    ->pluck('name')
                    ->toArray();
            });

            Builder::macro('requiredFieldsForPostgres', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());
                $modelDefaultAttributes = Helpers::getModelDefaultAttributes($this->getModel());
                $observerDefaultAttributes = Helpers::getObserverFilledFields($this->getModel());

                $primaryIndex = DB::select(/** @lang PostgreSQL */ "
                    SELECT
                        ic.relname AS name,
                        string_agg(a.attname, ',' ORDER BY indseq.ord) AS columns,
                        am.amname AS type,
                        i.indisunique AS unique,
                        i.indisprimary AS primary
                    FROM
                        pg_index i
                        JOIN pg_class tc ON tc.oid = i.indrelid
                        JOIN pg_namespace tn ON tn.oid = tc.relnamespace
                        JOIN pg_class ic ON ic.oid = i.indexrelid
                        JOIN pg_am am ON am.oid = ic.relam
                        JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS indseq(num, ord) ON true
                        LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid
                        AND a.attnum = indseq.num
                    WHERE
                        tc.relname = ?
                        AND tn.nspname = CURRENT_SCHEMA
                    GROUP BY
                        ic.relname,
                        am.amname,
                        i.indisunique,
                        i.indisprimary;
                ", [$table]);

                $primaryIndex = collect($primaryIndex)
                    ->map(function ($index) {
                        return (array) $index;
                    })
                    ->filter(function ($index) {
                        return $index['primary'];
                    })
                    ->pluck('columns')
                    ->flatten()
                    ->toArray();

                $queryResult = DB::select(
                    /** @lang PostgreSQL */ '
                    SELECT
                        is_nullable AS nullable,
                        column_name AS name,
                        column_default AS default
                    FROM
                        information_schema.columns
                    WHERE
                        table_name = ?
                    ORDER BY
                        ordinal_position ASC',
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->reject(function ($column) use ($primaryIndex) {
                        return $column['default'] ||
                            ($column['nullable'] == 'YES') ||
                            (in_array($column['name'], $primaryIndex));
                    })
                    ->reject(function ($column) use ($modelDefaultAttributes) {
                        return in_array($column['name'], $modelDefaultAttributes);
                    })
                    ->reject(function ($column) use ($observerDefaultAttributes) {
                        return in_array($column['name'], $observerDefaultAttributes);
                    })
                    ->pluck('name')
                    ->unique()
                    ->toArray();
            });

            Builder::macro('requiredFieldsForSqlServer', function () {
                $table = Helpers::getTableFromThisModel($this->getModel());
                $modelDefaultAttributes = Helpers::getModelDefaultAttributes($this->getModel());
                $observerDefaultAttributes = Helpers::getObserverFilledFields($this->getModel());

                $primaryIndex = DB::select(/** @lang TSQL */ '
                    SELECT
                        COL_NAME(ic.object_id, ic.column_id) AS [column]
                    FROM
                        sys.indexes AS i
                        INNER JOIN sys.index_columns AS ic
                            ON i.object_id = ic.object_id
                            AND i.index_id = ic.index_id
                        INNER JOIN sys.objects AS o
                            ON i.object_id = o.object_id
                    WHERE
                        i.is_primary_key = 1
                        AND o.name = ?
                        AND SCHEMA_NAME(o.schema_id) = schema_name()', [$table]);

                $primaryIndex = collect($primaryIndex)
                    ->pluck('column')
                    ->toArray();

                $queryResult = DB::select(
                    /** @lang TSQL */ "
                    SELECT
                        COLUMN_NAME AS name,
                        DATA_TYPE AS type,
                        CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS nullable,
                        COLUMN_DEFAULT AS [default]
                    FROM
                        INFORMATION_SCHEMA.COLUMNS
                    WHERE
                        TABLE_SCHEMA = SCHEMA_NAME()
                        AND TABLE_NAME = ?
                    ORDER BY
                        ORDINAL_POSITION ASC",
                    [$table]
                );

                return collect($queryResult)
                    ->map(function ($column) {
                        return (array) $column;
                    })
                    ->reject(function ($column) use ($primaryIndex) {
                        return
                            $column['default'] != null
                            || $column['nullable']
                            || (in_array($column['name'], $primaryIndex));
                    })
                    ->reject(function ($column) use ($modelDefaultAttributes) {
                        return in_array($column['name'], $modelDefaultAttributes);
                    })
                    ->reject(function ($column) use ($observerDefaultAttributes) {
                        return in_array($column['name'], $observerDefaultAttributes);
                    })
                    ->pluck('name')
                    ->toArray();
            });

        }

    }
}
