<?php

/**
 * CRUD Interceptor for CPT Table Engine.
 *
 * Intercepts WordPress CRUD functions and routes to custom tables.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Integration;

use SLK\Cpt_Table_Engine\Controllers\Settings_Controller;
use SLK\Cpt_Table_Engine\Controllers\CPT_Controller;
use SLK\Cpt_Table_Engine\Controllers\Meta_Controller;
use SLK\Cpt_Table_Engine\Database\Table_Manager;
use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * CRUD Interceptor class.
 */
final class CRUD_Interceptor
{
    /**
     * Static cache for post type lookups (request-level).
     *
     * @var array<int, string>
     */
    private static array $post_type_cache = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Get post type for a post ID with caching.
     *
     * @param int $post_id Post ID.
     * @return string|false Post type or false if not found.
     */
    private static function get_post_type_cached(int $post_id)
    {
        // Check cache first.
        if (isset(self::$post_type_cache[$post_id])) {
            return self::$post_type_cache[$post_id];
        }

        // Get post and cache the post type.
        $post = get_post($post_id);
        if (! $post) {
            return false;
        }

        self::$post_type_cache[$post_id] = $post->post_type;

        return $post->post_type;
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Post CRUD hooks.
        add_action('wp_insert_post', [$this, 'handle_insert_post'], 10, 3);
        add_filter('wp_insert_post_data', [$this, 'handle_insert_post_data'], 10, 2);

        // Meta CRUD hooks.
        add_filter('add_post_metadata', [$this, 'handle_add_post_meta'], 10, 5);
        add_filter('update_post_metadata', [$this, 'handle_update_post_meta'], 10, 5);
        add_filter('delete_post_metadata', [$this, 'handle_delete_post_meta'], 10, 5);
        add_filter('get_post_metadata', [$this, 'handle_get_post_meta'], 10, 4);
    }

    /**
     * Handle post insertion/update.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     * @return void
     */
    public function handle_insert_post(int $post_id, \WP_Post $post, bool $update): void
    {
        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post->post_type)) {
            return;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post->post_type)) {
            return;
        }

        // Convert WP_Post to array.
        $post_data = get_object_vars($post);

        if ($update) {
            // Update in custom table.
            CPT_Controller::update($post->post_type, $post_id, $post_data);
        } else {
            // Insert into custom table.
            CPT_Controller::insert($post->post_type, $post_data);
        }

        Logger::debug("Intercepted post " . ($update ? 'update' : 'insert') . " for ID: {$post_id}");
    }

    /**
     * Filter post data before insertion.
     *
     * @param array $data    Post data.
     * @param array $postarr Raw post data.
     * @return array Modified post data.
     */
    public function handle_insert_post_data(array $data, array $postarr): array
    {
        // Check if this post type uses custom tables.
        $post_type = $data['post_type'] ?? '';

        if (! Settings_Controller::is_enabled($post_type)) {
            return $data;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $data;
        }

        // We'll handle the insertion in handle_insert_post,
        // but we still need to let WordPress create the post in wp_posts
        // to maintain compatibility with other plugins.
        // We could optionally skip wp_posts insertion here if needed.

        return $data;
    }

    /**
     * Handle add_post_meta.
     *
     * @param null|bool $check      Whether to allow adding metadata.
     * @param int       $object_id  Post ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value.
     * @param bool      $unique     Whether the key should be unique.
     * @return null|bool Null to continue, true/false to short-circuit.
     */
    public function handle_add_post_meta($check, int $object_id, string $meta_key, $meta_value, bool $unique)
    {
        // Get post type.
        $post_type = self::get_post_type_cached($object_id);
        if (! $post_type) {
            return $check;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $check;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $check;
        }

        // Check if unique and already exists.
        if ($unique) {
            $existing = Meta_Controller::get_meta($post_type, $object_id, $meta_key, true);
            if ('' !== $existing) {
                return false;
            }
        }

        // Add to custom table.
        $result = Meta_Controller::add_meta($post_type, $object_id, $meta_key, $meta_value);

        Logger::debug("Intercepted add_post_meta for post ID: {$object_id}, key: {$meta_key}");

        // Return the meta ID or false to short-circuit WordPress's add_metadata.
        return false !== $result ? $result : false;
    }

    /**
     * Handle update_post_meta.
     *
     * @param null|bool $check      Whether to allow updating metadata.
     * @param int       $object_id  Post ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value.
     * @param mixed     $prev_value Previous value.
     * @return null|bool Null to continue, true/false to short-circuit.
     */
    public function handle_update_post_meta($check, int $object_id, string $meta_key, $meta_value, $prev_value)
    {
        // Get post type.
        $post_type = self::get_post_type_cached($object_id);
        if (! $post_type) {
            return $check;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $check;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $check;
        }

        // Update in custom table.
        $result = Meta_Controller::update_meta($post_type, $object_id, $meta_key, $meta_value, $prev_value);

        Logger::debug("Intercepted update_post_meta for post ID: {$object_id}, key: {$meta_key}");

        // Return true to short-circuit WordPress's update_metadata.
        return $result;
    }

    /**
     * Handle delete_post_meta.
     *
     * @param null|bool $check      Whether to allow deleting metadata.
     * @param int       $object_id  Post ID.
     * @param string    $meta_key   Meta key.
     * @param mixed     $meta_value Meta value.
     * @param bool      $delete_all Whether to delete all matching meta.
     * @return null|bool Null to continue, true/false to short-circuit.
     */
    public function handle_delete_post_meta($check, int $object_id, string $meta_key, $meta_value, bool $delete_all)
    {
        // Get post type.
        $post_type = $this->get_post_type_cached($object_id);
        if (! $post_type) {
            return $check;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $check;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $check;
        }

        // Delete from custom table.
        $result = Meta_Controller::delete_meta($post_type, $object_id, $meta_key, $meta_value);

        // Skip logging for frequently checked internal meta keys.
        $skip_logging = in_array($meta_key, ['_edit_lock', '_edit_last'], true);

        if (! $skip_logging) {
            Logger::debug("Intercepted delete_post_meta for post ID: {$object_id}, key: {$meta_key}");
        }

        // Return true to short-circuit WordPress's delete_metadata.
        return $result;
    }

    /**
     * Handle get_post_meta.
     *
     * @param null|array|string $value     The value to return.
     * @param int               $object_id Post ID.
     * @param string            $meta_key  Meta key.
     * @param bool              $single    Whether to return a single value.
     * @return mixed The meta value(s) or null to continue.
     */
    public function handle_get_post_meta($check, int $object_id, string $meta_key, bool $single)
    {
        // Get the post type using cached method.
        $post_type = $this->get_post_type_cached($object_id);

        if (! $post_type) {
            return $check;
        }

        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post_type)) {
            return $check;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return $check;
        }

        // Get from custom table.
        $result = Meta_Controller::get_meta($post_type, $object_id, $meta_key, $single);

        // Skip logging for frequently checked internal meta keys.
        $skip_logging = in_array($meta_key, ['_edit_lock', '_edit_last'], true);

        if (! $skip_logging) {
            Logger::debug("Intercepted get_post_meta for post ID: {$object_id}, key: {$meta_key}");
        }

        return $result;
    }
}
