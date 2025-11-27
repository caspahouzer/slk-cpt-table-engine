<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Database;

use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * Table Manager class.
 */
final class Table_Manager
{
    /**
     * Static cache for verified tables (request-level).
     *
     * @var array<string, bool>
     */
    private static array $verified_tables_cache = [];

    /**
     * Create tables for a specific post type.
     *
     * @param string $post_type The post type slug.
     * @return bool True on success, false on failure.
     */
    public static function create_tables(string $post_type): bool
    {
        $main_table = self::get_table_name($post_type, 'main');
        $meta_table = self::get_table_name($post_type, 'meta');

        try {
            // Create main table.
            if (! self::create_table($main_table, Table_Schema::get_main_table_schema($main_table))) {
                Logger::error("Failed to create main table for post type: {$post_type}");
                return false;
            }

            // Create meta table.
            if (! self::create_table($meta_table, Table_Schema::get_meta_table_schema($meta_table))) {
                Logger::error("Failed to create meta table for post type: {$post_type}");
                // Rollback: drop main table.
                self::drop_table($main_table);
                return false;
            }

            Logger::info("Successfully created tables for post type: {$post_type}");
            return true;
        } catch (\Exception $e) {
            Logger::error("Exception while creating tables for {$post_type}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drop tables for a specific post type.
     *
     * @param string $post_type The post type slug.
     * @return bool True on success, false on failure.
     */
    public static function drop_tables(string $post_type): bool
    {
        $main_table = self::get_table_name($post_type, 'main');
        $meta_table = self::get_table_name($post_type, 'meta');

        try {
            $success = true;

            // Drop meta table first (foreign key considerations).
            if (self::table_exists($meta_table)) {
                $success = self::drop_table($meta_table) && $success;
            }

            // Drop main table.
            if (self::table_exists($main_table)) {
                $success = self::drop_table($main_table) && $success;
            }

            if ($success) {
                Logger::info("Successfully dropped tables for post type: {$post_type}");
            } else {
                Logger::error("Failed to drop some tables for post type: {$post_type}");
            }

            return $success;
        } catch (\Exception $e) {
            Logger::error("Exception while dropping tables for {$post_type}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a single table using dbDelta.
     *
     * @param string $table_name The table name.
     * @param string $schema     The SQL schema.
     * @return bool True on success, false on failure.
     */
    private static function create_table(string $table_name, string $schema): bool
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $result = dbDelta($schema);

        return self::table_exists($table_name);
    }

    /**
     * Drop a single table.
     *
     * @param string $table_name The table name.
     * @return bool True on success, false on failure.
     */
    private static function drop_table(string $table_name): bool
    {
        global $wpdb;

        // Sanitize table name to prevent SQL injection.
        // This is a basic check; ensure table names are generated securely.
        if (preg_match('/[^a-zA-Z0-9_]/', str_replace($wpdb->prefix, '', $table_name))) {
            Logger::error("Invalid table name provided for deletion: {$table_name}");
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $result = $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");

        return false !== $result;
    }

    /**
     * Check if a table exists.
     *
     * @param string $table_name The table name.
     * @return bool True if exists, false otherwise.
     */
    public static function table_exists(string $table_name): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        return $result === $table_name;
    }

    /**
     * Get the table name for a post type.
     *
     * @param string $post_type The post type slug.
     * @param string $type      The table type ('main' or 'meta').
     * @return string The full table name.
     */
    public static function get_table_name(string $post_type, string $type = 'main'): string
    {
        global $wpdb;

        $sanitized_post_type = sanitize_key($post_type);

        if ('meta' === $type) {
            return $wpdb->prefix . 'cpt_' . $sanitized_post_type . '_meta';
        }

        return $wpdb->prefix . 'cpt_' . $sanitized_post_type;
    }

    /**
     * Get all custom CPT tables in the database.
     *
     * @return array<string> Array of table names.
     */
    public static function get_all_cpt_tables(): array
    {
        global $wpdb;

        $pattern = $wpdb->esc_like($wpdb->prefix . 'cpt_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tables  = $wpdb->get_col(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $pattern
            )
        );

        return $tables ?: [];
    }

    /**
     * Verify table integrity.
     *
     * @param string $post_type The post type slug.
     * @return bool True if both tables exist and are valid, false otherwise.
     */
    public static function verify_tables(string $post_type): bool
    {
        // Check cache first.
        if (isset(self::$verified_tables_cache[$post_type])) {
            return self::$verified_tables_cache[$post_type];
        }

        $main_table = self::get_table_name($post_type, 'main');
        $meta_table = self::get_table_name($post_type, 'meta');

        $result = self::table_exists($main_table) && self::table_exists($meta_table);

        // Cache the result.
        self::$verified_tables_cache[$post_type] = $result;

        return $result;
    }

    /**
     * Get row count for a table.
     *
     * @param string $table_name The table name.
     * @return int Row count, or 0 if table doesn't exist.
     */
    public static function get_table_row_count(string $table_name): int
    {
        global $wpdb;

        if (!self::table_exists($table_name)) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");

        return (int) $count;
    }

    /**
     * Create a backup of a table.
     *
     * @param string $table_name The table name to backup.
     * @return string|false Backup table name on success, false on failure.
     */
    public static function backup_table(string $table_name)
    {
        global $wpdb;

        if (!self::table_exists($table_name)) {
            Logger::error("Cannot backup non-existent table: {$table_name}");
            return false;
        }

        $timestamp   = gmdate('YmdHis');
        $backup_name = "{$table_name}_backup_{$timestamp}";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $result = $wpdb->query("CREATE TABLE `{$backup_name}` LIKE `{$table_name}`");

        if (false === $result) {
            Logger::error("Failed to create backup table structure: {$backup_name}");
            return false;
        }

        // Copy data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query("INSERT INTO `{$backup_name}` SELECT * FROM `{$table_name}`");

        if (false === $result) {
            Logger::error("Failed to copy data to backup table: {$backup_name}");
            // Clean up incomplete backup.
            self::drop_table($backup_name);
            return false;
        }

        $row_count = self::get_table_row_count($backup_name);
        Logger::info("Created backup table {$backup_name} with {$row_count} rows");

        return $backup_name;
    }

    /**
     * Detect existing CPT tables and gather information.
     *
     * @return array<string, array<string, mixed>> Table information indexed by table name.
     */
    public static function detect_existing_tables(): array
    {
        $tables = self::get_all_cpt_tables();
        $results = [];

        foreach ($tables as $table_name) {
            $row_count = self::get_table_row_count($table_name);

            // Determine if it's a main or meta table.
            $type = (strpos($table_name, '_meta') !== false) ? 'meta' : 'main';

            $results[$table_name] = [
                'name'      => $table_name,
                'type'      => $type,
                'row_count' => $row_count,
                'has_data'  => $row_count > 0,
            ];

            Logger::info("Detected existing table: {$table_name} ({$row_count} rows, type: {$type})");
        }

        return $results;
    }
}
