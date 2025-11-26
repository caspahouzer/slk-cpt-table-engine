<?php

/**
 * Meta Controller for CPT Table Engine.
 *
 * Handles CRUD operations for post meta in custom tables.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK_Cpt_Table_Engine\Controllers;

use SLK_Cpt_Table_Engine\Database\Table_Manager;
use SLK_Cpt_Table_Engine\Helpers\Sanitizer;
use SLK_Cpt_Table_Engine\Helpers\Validator;
use SLK_Cpt_Table_Engine\Helpers\Cache;
use SLK_Cpt_Table_Engine\Helpers\Logger;

/**
 * Meta Controller class.
 */
final class Meta_Controller
{
    /**
     * Add post meta.
     *
     * @param string $post_type  The post type.
     * @param int    $post_id    The post ID.
     * @param string $meta_key   The meta key.
     * @param mixed  $meta_value The meta value.
     * @return int|false Meta ID on success, false on failure.
     */
    public static function add_meta(string $post_type, int $post_id, string $meta_key, $meta_value)
    {
        global $wpdb;

        // Validate.
        if (! Validator::is_valid_post_id($post_id) || ! Validator::is_valid_meta_key($meta_key)) {
            return false;
        }

        // Sanitize.
        $meta_key = Sanitizer::sanitize_meta_key($meta_key);

        // Serialize if needed.
        $meta_value = maybe_serialize($meta_value);

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'meta');

        // Insert into database.
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id'    => $post_id,
                'meta_key'   => $meta_key,
                'meta_value' => $meta_value,
            ],
            ['%d', '%s', '%s']
        );

        if (false === $result) {
            Logger::error("Failed to add meta for post ID {$post_id}: " . $wpdb->last_error);
            return false;
        }

        $meta_id = (int) $wpdb->insert_id;

        // Clear cache.
        Cache::delete_meta($post_id, $meta_key, $post_type);
        Cache::flush_post_type($post_type);

        Logger::debug("Added meta ID {$meta_id} for post ID {$post_id}");

        return $meta_id;
    }

    /**
     * Update post meta.
     *
     * @param string $post_type  The post type.
     * @param int    $post_id    The post ID.
     * @param string $meta_key   The meta key.
     * @param mixed  $meta_value The meta value.
     * @param mixed  $prev_value Optional. Previous value to match.
     * @return bool True on success, false on failure.
     */
    public static function update_meta(string $post_type, int $post_id, string $meta_key, $meta_value, $prev_value = ''): bool
    {
        global $wpdb;

        // Validate.
        if (! Validator::is_valid_post_id($post_id) || ! Validator::is_valid_meta_key($meta_key)) {
            return false;
        }

        // Sanitize.
        $meta_key = Sanitizer::sanitize_meta_key($meta_key);

        // Serialize if needed.
        $meta_value = maybe_serialize($meta_value);

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'meta');
        if (! $table_name) {
            return false;
        }

        // Check if meta exists.
        $sql = "SELECT meta_id FROM `{$table_name}` WHERE post_id = %d AND meta_key = %s";
        $params = [$post_id, $meta_key];

        if ('' !== $prev_value) {
            $sql .= ' AND meta_value = %s';
            $params[] = maybe_serialize($prev_value);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $existing = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        if (! $existing) {
            // Meta doesn't exist, add it.
            return false !== self::add_meta($post_type, $post_id, $meta_key, maybe_unserialize($meta_value));
        }

        // Update existing meta.
        $where = [
            'post_id'  => $post_id,
            'meta_key' => $meta_key,
        ];
        $where_format = ['%d', '%s'];

        if ('' !== $prev_value) {
            $where['meta_value'] = maybe_serialize($prev_value);
            $where_format[] = '%s';
        }

        $result = $wpdb->update(
            $table_name,
            ['meta_value' => $meta_value],
            $where,
            ['%s'],
            $where_format
        );

        if (false === $result) {
            Logger::error("Failed to update meta for post ID {$post_id}: " . $wpdb->last_error);
            return false;
        }

        // Clear cache.
        Cache::delete_meta($post_id, $meta_key, $post_type);
        Cache::flush_post_type($post_type);

        Logger::debug("Updated meta for post ID {$post_id}, key: {$meta_key}");

        return true;
    }

    /**
     * Delete post meta.
     *
     * @param string $post_type  The post type.
     * @param int    $post_id    The post ID.
     * @param string $meta_key   The meta key.
     * @param mixed  $meta_value Optional. Meta value to match.
     * @return bool True on success, false on failure.
     */
    public static function delete_meta(string $post_type, int $post_id, string $meta_key, $meta_value = ''): bool
    {
        global $wpdb;

        // Validate.
        if (! Validator::is_valid_post_id($post_id) || ! Validator::is_valid_meta_key($meta_key)) {
            return false;
        }

        // Sanitize.
        $meta_key = Sanitizer::sanitize_meta_key($meta_key);

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'meta');

        // Build where clause.
        $where = [
            'post_id'  => $post_id,
            'meta_key' => $meta_key,
        ];
        $where_format = ['%d', '%s'];

        if ('' !== $meta_value) {
            $where['meta_value'] = maybe_serialize($meta_value);
            $where_format[] = '%s';
        }

        // Delete from database.
        $result = $wpdb->delete($table_name, $where, $where_format);

        if (false === $result) {
            Logger::error("Failed to delete meta for post ID {$post_id}: " . $wpdb->last_error);
            return false;
        }

        // Clear cache.
        Cache::delete_meta($post_id, $meta_key, $post_type);
        Cache::flush_post_type($post_type);

        Logger::debug("Deleted meta for post ID {$post_id}, key: {$meta_key}");

        return true;
    }

    /**
     * Get post meta.
     *
     * @param string $post_type The post type.
     * @param int    $post_id   The post ID.
     * @param string $meta_key  Optional. The meta key. If empty, returns all meta.
     * @param bool   $single    Optional. Whether to return a single value.
     * @return mixed Meta value(s) or empty string/array if not found.
     */
    public static function get_meta(string $post_type, int $post_id, string $meta_key = '', bool $single = false)
    {
        global $wpdb;

        // Validate.
        if (! Validator::is_valid_post_id($post_id)) {
            return $single ? '' : [];
        }

        // Check cache if specific key requested.
        if (! empty($meta_key)) {
            $cached = Cache::get_meta($post_id, $meta_key, $post_type);
            if (false !== $cached) {
                return $single ? $cached : [$cached];
            }
        }

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'meta');
        if (! $table_name) {
            return $single ? '' : [];
        }

        // Build query.
        if (! empty($meta_key)) {
            $meta_key = Sanitizer::sanitize_meta_key($meta_key);

            $results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_value FROM `{$table_name}` WHERE post_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $post_id,
                    $meta_key
                )
            );

            if (empty($results)) {
                return $single ? '' : [];
            }

            // Unserialize values.
            $results = array_map('maybe_unserialize', $results);

            // Cache the first result.
            if (! empty($results)) {
                Cache::set_meta($post_id, $meta_key, $results[0], $post_type);
            }

            return $single ? $results[0] : $results;
        }

        // Get all meta for post.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM `{$table_name}` WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $post_id
            )
        );

        if (empty($results)) {
            return [];
        }

        // Build meta array.
        $meta = [];
        foreach ($results as $row) {
            $value = maybe_unserialize($row->meta_value);
            $meta[$row->meta_key][] = $value;
        }

        return $meta;
    }

    /**
     * Delete all meta for a post.
     *
     * @param string $post_type The post type.
     * @param int    $post_id   The post ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_all_meta(string $post_type, int $post_id): bool
    {
        global $wpdb;

        // Validate.
        if (! Validator::is_valid_post_id($post_id)) {
            return false;
        }

        // Get table name.
        $table_name = Table_Manager::get_table_name($post_type, 'meta');

        // Delete all meta.
        $result = $wpdb->delete(
            $table_name,
            ['post_id' => $post_id],
            ['%d']
        );

        // Clear cache.
        Cache::flush_post_type($post_type);

        Logger::debug("Deleted all meta for post ID {$post_id}");

        return false !== $result;
    }
}
