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
     * @return array|string
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

        $primaryIndex = collect(Schema::getIndexes((new self())->getTable()))
            ->filter(function ($index) {
                return $index['primary'];
            })
            ->pluck('columns')
            ->flatten()
            ->toArray();

        return collect(Schema::getColumns((new self())->getTable()))
            ->reject(function ($column) use ($primaryIndex, $withNullables, $withDefaults) {
                return
                    $column['nullable'] && !$withNullables ||
                    $column['default'] != null && !$withDefaults ||
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
     * @return array|string
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

    /**
     * @return array
     */
    private static function getRequiredFieldsForSqlite(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

        $queryResult = DB::select("PRAGMA table_info($table)");

        // convert stdClass object to array
        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        return collect($queryResult)
            ->reject(function ($column) use ($withNullables, $withDefaults, $withPrimaryKey) {
                return $column['pk'] && !$withPrimaryKey
                    || $column['dflt_value'] != null && !$withDefaults
                    || !$column['notnull'] && !$withNullables;
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return array
     */
    private static function getRequiredFieldsForMysqlAndMariaDb(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

        $queryResult = DB::select(
            "
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

        // convert stdClass object to array
        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        if(!$withNullables){
            dump($queryResult); // todo remove it later
        }

        return collect($queryResult)
            ->reject(function ($column) use ($withNullables, $withDefaults, $withPrimaryKey) {
                return $column['primary'] && !$withPrimaryKey
                    || $column['default'] != null && !$withDefaults
                    || $column['nullable'] && !$withNullables;
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return array
     */
    private static function getRequiredFieldsForPostgres(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

        $primaryIndex = DB::select("
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

        $primaryIndex = array_map(function ($column) {
            return (array) $column;
        }, $primaryIndex);

        $primaryIndex = collect($primaryIndex)
            ->filter(function ($index) {
                return $index['primary'];
            })
            ->pluck('columns')
            ->flatten()
            ->toArray();

        $queryResult = DB::select(
            '
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

        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        $result = collect($queryResult)
            ->reject(function ($column) use ($primaryIndex, $withDefaults, $withNullables) {
                return ($column['default'] && !$withDefaults) ||
                    ($column['nullable'] == 'YES' && !$withNullables) ||
                    (in_array($column['name'], $primaryIndex));
            })
            ->pluck('name')
            ->toArray();

        // Add primary key to the result if $withPrimaryKey is true
        if ($withPrimaryKey) {
            $result = array_unique(array_merge($primaryIndex, $result));
        }

        return $result;
    }

    /**
     * Not tested yet in machine with SQL SERVER
     *
     * @return array
     */
    private static function getRequiredFieldsForSqlServer(
        $withNullables = false,
        $withDefaults = false,
        $withPrimaryKey = false
    ) {
        $table = self::getTableFromThisModel();

        $primaryIndex = DB::select(
            'select col.name, type.name as type_name, '
                . 'col.max_length as length, col.precision as precision, col.scale as places, '
                . 'col.is_nullable as nullable, def.definition as [default], '
                . 'col.is_identity as autoincrement, col.collation_name as collation, '
                . 'com.definition as [expression], is_persisted as [persisted], '
                . 'cast(prop.value as nvarchar(max)) as comment '
                . 'from sys.columns as col '
                . 'join sys.types as type on col.user_type_id = type.user_type_id '
                . 'join sys.objects as obj on col.object_id = obj.object_id '
                . 'join sys.schemas as scm on obj.schema_id = scm.schema_id '
                . 'left join sys.default_constraints def on col.default_object_id = def.object_id and col.object_id = def.parent_object_id '
                . "left join sys.extended_properties as prop on obj.object_id = prop.major_id and col.column_id = prop.minor_id and prop.name = 'MS_Description' "
                . 'left join sys.computed_columns as com on col.column_id = com.column_id and col.object_id = com.object_id '
                . "where obj.type in ('U', 'V') and obj.name = ? and scm.name = schema_name() "
                . 'order by col.column_id',
            [$table],
        );

        $primaryIndex = array_map(function ($column) {
            return (array) $column;
        }, $primaryIndex);

        // todo remove later
        dump([
            'primaryIndex' => $primaryIndex,
            'table' => $table,
            'sqlserver',
        ]);

        $primaryIndex = collect($primaryIndex)
            ->filter(function ($index) {
                return $index['autoincrement']
                    || $index['type_name'] == 'uniqueidentifier';
            })
            ->pluck('columns')
            ->flatten()
            ->toArray();

        $queryResult = DB::select(
            "
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

        // convert stdClass object to array
        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        // if (!$withDefaults && !$withNullables && !$withPrimaryKey) {
        //     dump($queryResult); // todo remove it later
        // }

        $result = collect($queryResult)
            ->reject(function ($column) use ($withDefaults, $withNullables, $primaryIndex) {
                return
                    // $column['primary'] && !$withPrimaryKey || //todo remove later
                    $column['default'] != null && !$withDefaults
                    || $column['nullable'] && !$withNullables
                    || (in_array($column['name'], $primaryIndex));
            })
            ->pluck('name')
            ->toArray();

        // Add primary key to the result if $withPrimaryKey is true
        if ($withPrimaryKey) {
            $result = array_unique(array_merge($primaryIndex, $result));
        }

        return $result;
    }

    /**
     * @return array|string|string[]
     */
    private static function getTableFromThisModel()
    {
        $table = (new self())->getTable();

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
