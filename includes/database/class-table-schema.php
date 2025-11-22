<?php

/**
 * Table Schema class for CPT Table Engine.
 *
 * Defines database schema for CPT main and meta tables.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Database;

/**
 * Table Schema class.
 */
final class Table_Schema
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
			UNIQUE KEY post_name (post_name),
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
}
