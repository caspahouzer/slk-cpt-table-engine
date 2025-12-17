<?php

declare(strict_types=1);

namespace SLK\CptTableEngine\Services\Database;

/**
 * Table Schema for CPT Table Engine.
 *
 * Defines the schema for custom CPT tables.
 *
 * @package SLK\CptTableEngine
 */

/**
 * Table Schema class.
 */
final class TableSchema
{
    /**
     * Get the schema for the main CPT table.
     *
     * @param string $table_name The table name.
     * @return string SQL schema definition.
     */
    public static function get_main_table_schema(string $table_name): string
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE `{$table_name}` (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_title text NOT NULL,
			post_content longtext NOT NULL,
			post_excerpt text NOT NULL,
			post_status varchar(20) NOT NULL DEFAULT 'publish',
			post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			post_date_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			post_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			post_modified_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			post_author bigint(20) unsigned NOT NULL DEFAULT 0,
			post_name varchar(200) NOT NULL DEFAULT '',
			post_type varchar(20) NOT NULL DEFAULT 'post',
			post_parent bigint(20) unsigned NOT NULL DEFAULT 0,
			menu_order int(11) NOT NULL DEFAULT 0,
			comment_status varchar(20) NOT NULL DEFAULT 'open',
			ping_status varchar(20) NOT NULL DEFAULT 'open',
			comment_count bigint(20) NOT NULL DEFAULT 0,
			guid varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (ID),
			KEY post_name (post_name(191)),
			KEY post_status (post_status),
			KEY post_date (post_date),
			KEY post_modified (post_modified),
			KEY post_author (post_author),
			KEY post_parent (post_parent),
			KEY post_type (post_type),
			KEY menu_order (menu_order)
		) {$charset_collate};";
    }

    /**
     * Get the schema for the meta table.
     *
     * @param string $table_name The table name.
     * @return string SQL schema definition.
     */
    public static function get_meta_table_schema(string $table_name): string
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE `{$table_name}` (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY post_id (post_id),
			KEY meta_key (meta_key(191)),
			KEY post_id_meta_key (post_id, meta_key(191))
		) {$charset_collate};";
    }

    /**
     * Get all columns for the main table.
     *
     * @return array<string> Column names.
     */
    public static function get_main_table_columns(): array
    {
        return [
            'ID',
            'post_title',
            'post_content',
            'post_excerpt',
            'post_status',
            'post_date',
            'post_date_gmt',
            'post_modified',
            'post_modified_gmt',
            'post_author',
            'post_name',
            'post_type',
            'post_parent',
            'menu_order',
            'comment_status',
            'ping_status',
            'comment_count',
            'guid',
        ];
    }

    /**
     * Get all columns for the meta table.
     *
     * @return array<string> Column names.
     */
    public static function get_meta_table_columns(): array
    {
        return [
            'meta_id',
            'post_id',
            'meta_key',
            'meta_value',
        ];
    }

    /**
     * Get actual columns from an existing table.
     *
     * @param string $table_name The table name.
     * @return array<string, array<string, mixed>> Column definitions indexed by column name.
     */
    public static function get_actual_table_columns(string $table_name): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $columns = $wpdb->get_results("DESCRIBE `{$table_name}`", ARRAY_A);

        if (empty($columns)) {
            return [];
        }

        $result = [];
        foreach ($columns as $column) {
            $result[$column['Field']] = $column;
        }

        return $result;
    }

    /**
     * Get actual indexes from an existing table.
     *
     * @param string $table_name The table name.
     * @return array<string, array<string, mixed>> Index definitions indexed by key name.
     */
    public static function get_actual_table_indexes(string $table_name): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table_name}`", ARRAY_A);

        if (empty($indexes)) {
            return [];
        }

        $result = [];
        foreach ($indexes as $index) {
            $key_name = $index['Key_name'];
            if (!isset($result[$key_name])) {
                $result[$key_name] = [
                    'name'     => $key_name,
                    'unique'   => (int) $index['Non_unique'] === 0,
                    'columns'  => [],
                ];
            }
            $result[$key_name]['columns'][] = $index['Column_name'];
        }

        return $result;
    }

    /**
     * Validate table schema against expected schema.
     *
     * @param string $table_name The table name.
     * @param string $type       The table type ('main' or 'meta').
     * @return array{valid: bool, missing_columns: array<string>, extra_columns: array<string>, missing_indexes: array<string>, message: string}
     */
    public static function validate_table_schema(string $table_name, string $type = 'main'): array
    {
        $expected_columns = ('meta' === $type)
            ? self::get_meta_table_columns()
            : self::get_main_table_columns();

        $actual_columns = self::get_actual_table_columns($table_name);

        if (empty($actual_columns)) {
            return [
                'valid'            => false,
                'missing_columns'  => $expected_columns,
                'extra_columns'    => [],
                'missing_indexes'  => [],
                'message'          => 'Table does not exist or has no columns.',
            ];
        }

        $actual_column_names = array_keys($actual_columns);
        $missing_columns     = array_diff($expected_columns, $actual_column_names);
        $extra_columns       = array_diff($actual_column_names, $expected_columns);

        // Check indexes (simplified - just check if PRIMARY key exists).
        $actual_indexes  = self::get_actual_table_indexes($table_name);
        $missing_indexes = [];
        if (!isset($actual_indexes['PRIMARY'])) {
            $missing_indexes[] = 'PRIMARY';
        }

        $is_valid = empty($missing_columns) && empty($missing_indexes);

        $message = '';
        if (!$is_valid) {
            $issues = [];
            if (!empty($missing_columns)) {
                $issues[] = 'Missing columns: ' . implode(', ', $missing_columns);
            }
            if (!empty($missing_indexes)) {
                $issues[] = 'Missing indexes: ' . implode(', ', $missing_indexes);
            }
            $message = implode('; ', $issues);
        } else {
            $message = 'Schema is valid.';
        }

        return [
            'valid'            => $is_valid,
            'missing_columns'  => array_values($missing_columns),
            'extra_columns'    => array_values($extra_columns),
            'missing_indexes'  => $missing_indexes,
            'message'          => $message,
        ];
    }

    /**
     * Compare schemas and return detailed differences.
     *
     * @param string $table_name The table name.
     * @param string $type       The table type ('main' or 'meta').
     * @return array{columns: array{missing: array<string>, extra: array<string>}, indexes: array{missing: array<string>}, summary: string}
     */
    public static function compare_schemas(string $table_name, string $type = 'main'): array
    {
        $validation = self::validate_table_schema($table_name, $type);

        $summary_parts = [];
        if (!empty($validation['missing_columns'])) {
            $summary_parts[] = sprintf(
                '%d missing column(s)',
                count($validation['missing_columns'])
            );
        }
        if (!empty($validation['extra_columns'])) {
            $summary_parts[] = sprintf(
                '%d extra column(s)',
                count($validation['extra_columns'])
            );
        }
        if (!empty($validation['missing_indexes'])) {
            $summary_parts[] = sprintf(
                '%d missing index(es)',
                count($validation['missing_indexes'])
            );
        }

        $summary = empty($summary_parts)
            ? 'Schema matches expected structure'
            : implode(', ', $summary_parts);

        return [
            'columns' => [
                'missing' => $validation['missing_columns'],
                'extra'   => $validation['extra_columns'],
            ],
            'indexes' => [
                'missing' => $validation['missing_indexes'],
            ],
            'summary' => $summary,
        ];
    }
}
