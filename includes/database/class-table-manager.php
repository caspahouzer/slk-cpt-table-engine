<?php

/**
 * Table Manager class for CPT Table Engine.
 *
 * Handles table creation, deletion, and existence checks.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK_Cpt_Table_Engine\Database;

use SLK_Cpt_Table_Engine\Helpers\Logger;

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

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
}
