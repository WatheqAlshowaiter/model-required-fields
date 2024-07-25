<?php

namespace WatheqAlshowaiter\ModelRequiredFields;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait RequiredFields
{
    /**
     * Get only required fields for the model that
     * need to be added while creating a new record in the database.
     * So, we ignore auto_increment, primary keys, nullable and default fields.
     *
     * @return array
     */
    public static function getRequiredFields(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        if ((float) App::version() < 10) {
            return self::getRequiredFieldsForOlderVersions(
                $withNullables,
                $withDefaults,
                $withPrimaryKey
            );
        }

        $primaryIndex = collect(Schema::getIndexes((new self)->getTable()))
            ->filter(function ($index) {
                return $index['primary'];
            })
            ->pluck('columns')
            ->flatten()
            ->toArray();

        return collect(Schema::getColumns((new self)->getTable()))
            ->map(function ($column) { // specific to mariadb
                if ($column['default'] == 'NULL') {
                    $column['default'] = null;
                }

                return $column;
            })
            ->reject(function ($column) use ($primaryIndex, $withNullables, $withDefaults) {
                return
                    $column['nullable'] && ! $withNullables ||
                    $column['default'] != null && ! $withDefaults ||
                    (in_array($column['name'], $primaryIndex));
            })
            ->pluck('name')
            ->when($withPrimaryKey, function ($collection) use ($primaryIndex) {
                return $collection->prepend(...$primaryIndex);
            })
            ->unique()
            ->toArray();
    }

    /**
     * @return array
     */
    public static function getRequiredFieldsForOlderVersions(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $databaseDriver = DB::connection()->getDriverName();

        switch ($databaseDriver) {
            case 'sqlite':
                return self::getRequiredFieldsForSqlite(
                    $withNullables,
                    $withDefaults,
                    $withPrimaryKey
                );
            case 'mysql':
            case 'mariadb':
                return self::getRequiredFieldsForMysqlAndMariaDb(
                    $withNullables,
                    $withDefaults,
                    $withPrimaryKey
                );
            case 'pgsql':
                return self::getRequiredFieldsForPostgres(
                    $withNullables,
                    $withDefaults,
                    $withPrimaryKey
                );
            case 'sqlsrv':
                return self::getRequiredFieldsForSqlServer(
                    $withNullables,
                    $withDefaults,
                    $withPrimaryKey
                );
            default:
                return 'NOT SUPPORTED DATABASE DRIVER';
        }
    }

    private static function getRequiredFieldsForSqlite(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

        $queryResult = DB::select(/** @lang SQLite */ "PRAGMA table_info($table)");

        return collect($queryResult)
            ->map(function ($column) {
                return (array) $column;
            })
            ->reject(function ($column) use ($withNullables, $withDefaults, $withPrimaryKey) {
                return $column['pk'] && ! $withPrimaryKey
                    || $column['dflt_value'] != null && ! $withDefaults
                    || ! $column['notnull'] && ! $withNullables;
            })
            ->pluck('name')
            ->toArray();
    }

    private static function getRequiredFieldsForMysqlAndMariaDb(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

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
            ->reject(function ($column) use ($withNullables, $withDefaults, $withPrimaryKey) {
                return $column['primary'] && ! $withPrimaryKey
                    || $column['default'] != null && ! $withDefaults
                    || $column['nullable'] && ! $withNullables;
            })
            ->pluck('name')
            ->toArray();
    }

    private static function getRequiredFieldsForPostgres(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

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
            ->reject(function ($column) use ($primaryIndex, $withDefaults, $withNullables) {
                return ($column['default'] && ! $withDefaults) ||
                    ($column['nullable'] == 'YES' && ! $withNullables) ||
                    (in_array($column['name'], $primaryIndex));
            })
            ->pluck('name')
            ->when($withPrimaryKey, function ($collection) use ($primaryIndex) {
                return $collection->prepend(...$primaryIndex);
            })
            ->unique()
            ->toArray();
    }

    private static function getRequiredFieldsForSqlServer(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

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
            ->reject(function ($column) use ($withDefaults, $withNullables, $primaryIndex, $withPrimaryKey) {
                return
                    $column['default'] != null && ! $withDefaults
                    || $column['nullable'] && ! $withNullables
                    || (in_array($column['name'], $primaryIndex) && ! $withPrimaryKey);
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return string
     */
    private static function getTableFromThisModel()
    {
        $table = (new self)->getTable();

        return str_replace('.', '__', $table);
    }

    public static function getRequiredFieldsWithNullables()
    {
        return self::getRequiredFields($withNullables = true, $withDefaults = false, $withPrimaryKey = false);
    }

    public static function getRequiredFieldsWithDefaults()
    {
        return self::getRequiredFields($withNullables = false, $withDefaults = true, $withPrimaryKey = false);
    }

    public static function getRequiredFieldsWithPrimaryKey()
    {
        return self::getRequiredFields($withNullables = false, $withDefaults = false, $withPrimaryKey = true);
    }

    public static function getRequiredFieldsWithDefaultsAndPrimaryKey()
    {
        return self::getRequiredFields($withNullables = false, $withDefaults = true, $withPrimaryKey = true);
    }

    public static function getRequiredFieldsWithNullablesAndDefaults()
    {
        return self::getRequiredFields($withNullables = true, $withDefaults = true, $withPrimaryKey = false);
    }

    public static function getRequiredFieldsWithNullablesAndPrimaryKey()
    {
        return self::getRequiredFields($withNullables = true, $withDefaults = false, $withPrimaryKey = true);
    }

    public static function getAllFields()
    {
        return self::getRequiredFields(
            $withNullables = true,
            $withDefaults = true,
            $withPrimaryKey = true
        );
    }
}
