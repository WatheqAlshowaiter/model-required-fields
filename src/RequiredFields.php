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
     * @return array|string
     */
    public static function getRequiredFields()
    {
        if ((float) App::version() < 10) {
            return self::getRequiredFieldsForOlderVersions();
        }

        $primaryIndex = collect(Schema::getIndexes((new self())->getTable()))
            ->filter(function ($index) {
                return $index['primary'];
            })
            ->pluck('columns')
            ->flatten()
            ->toArray();

        return collect(Schema::getColumns((new self())->getTable()))
            ->reject(function ($column) use ($primaryIndex) {
                return $column['auto_increment']
                    || $column['nullable']
                    || $column['default'] != null
                    || in_array($column['name'], $primaryIndex);
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @todo convert this method to private after testing
     * @return array|string
     */
    public static function getRequiredFieldsForOlderVersions()
    {
        $databaseDriver = DB::connection()->getDriverName();

        switch ($databaseDriver) {
            case 'sqlite':
                return self::getRequiredFieldsForSqlite();
            case 'mysql':
            case 'mariadb':
                return self::getRequiredFieldsForMysqlAndMariaDb();
            case 'pgsql':
                return self::getRequiredFieldsForPostgres();
            case 'sqlsrv':
                return self::getRequiredFieldsForSqlServer();
            default:
                return 'NOT SUPPORTED DATABASE DRIVER';
        }
    }

    /**
     * @return array
     */
    private static function getRequiredFieldsForSqlite()
    {
        $table = self::getTableFromThisModel();

        $queryResult = DB::select("PRAGMA table_info($table)");

        // convert stdClass object to array
        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        return collect($queryResult)
            ->reject(function ($column) {
                return  $column['pk']
                    || $column['dflt_value'] != null
                    || !$column['notnull'];
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return array
     */
    private static function getRequiredFieldsForMysqlAndMariaDb()
    {
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

        return collect($queryResult)
            ->reject(function ($column) {
                return $column['primary']
                    || $column['default'] != null
                    || $column['nullable'];
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return array
     */
    private static function getRequiredFieldsForPostgres()
    {

        $table = self::getTableFromThisModel();

        $primaryIndex = DB::select("
                    SELECT
                ic.relname AS name,
                string_agg(
                    a.attname,
                    ','
                    ORDER BY
                        indseq.ord
                ) AS columns,
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
                tc.relname = 'users'
                AND tn.nspname = CURRENT_SCHEMA
            GROUP BY
                ic.relname,
                am.amname,
                i.indisunique,
                i.indisprimary;
        ");

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
            is_nullable as nullable,
            column_name as name,
            column_default as default
        FROM
            information_schema.columns
        WHERE
            TABLE_NAME = ?
        ORDER BY
        ORDINAL_POSITION ASC',
            [$table]
        );

        // convert stdClass object children to array
        $queryResult = array_map(function ($column) {
            return (array) $column;
        }, $queryResult);

        return collect($queryResult)
            ->reject(function ($column) use ($primaryIndex) {
                return $column['default']
                    || $column['nullable'] == 'YES'
                    || in_array($column['name'], $primaryIndex);
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * Not tested yet in machine with SQLSERVER
     * @return array
     */
    private static function getRequiredFieldsForSqlServer()
    {
        $table = self::getTableFromThisModel();

        $queryResult = DB::select(
            "
            SELECT
                COLUMN_NAME AS name,
                DATA_TYPE AS type,
                CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS nullable,
                COLUMN_DEFAULT AS [default],
                CASE WHEN COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') = 1
                     OR COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsPrimaryKey') = 1 THEN 1 ELSE 0 END AS [primary]
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

        return collect($queryResult)
            ->reject(function ($column) {
                return $column['primary']
                    || $column['default'] != null
                    || $column['nullable'];
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * @return array|string|string[]
     */
    private static function getTableFromThisModel()
    {
        $table = (new self())->getTable();

        return str_replace('.', '__', $table);
    }
}
