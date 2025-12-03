<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Integration;

use SLK\Cpt_Table_Engine\Controllers\CPT_Controller;
use SLK\Cpt_Table_Engine\Controllers\Meta_Controller;
use SLK\Cpt_Table_Engine\Controllers\Settings_Controller;
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
     * Track posts being processed to prevent recursive calls.
     *
     * @var array<int, bool>
     */
    private static array $processing_posts = [];

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

        // Cleanup hook - runs after post and meta are saved.
        // Priority 9999 ensures it runs after all other hooks.
        add_action('wp_after_insert_post', [$this, 'cleanup_post_from_wp_posts'], 9999, 4);

        // Additional cleanup on shutdown to catch any remaining orphaned meta.
        add_action('shutdown', [$this, 'cleanup_orphaned_meta'], 99999);

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

        // Prevent duplicate processing of the same post during this request.
        // Keep the flag set for the entire request to prevent multiple insertions.
        if (isset(self::$processing_posts[$post_id])) {
            Logger::debug("Skipping duplicate processing of post {$post_id}");
            return;
        }

        // Mark post as processed for this request (flag is never unset).
        self::$processing_posts[$post_id] = true;

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
     * Cleanup post from wp_posts after all insert operations complete.
     * This runs on 'wp_after_insert_post' hook which fires after post and meta are saved.
     *
     * @param int      $post_id     Post ID.
     * @param \WP_Post $post        Post object.
     * @param bool     $update      Whether this is an existing post being updated.
     * @param \WP_Post $post_before Post object before the update (null for new posts).
     * @return void
     */
    public function cleanup_post_from_wp_posts(int $post_id, \WP_Post $post, bool $update, $post_before): void
    {
        // Skip if post type doesn't use custom tables.
        if (! Settings_Controller::is_enabled($post->post_type)) {
            return;
        }

        // Skip if tables don't exist.
        if (! Table_Manager::verify_tables($post->post_type)) {
            return;
        }

        // Delete post and its meta from wp_posts/wp_postmeta now that all operations are complete.
        global $wpdb;

        // Delete meta first (foreign key constraint).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $meta_deleted = $wpdb->delete($wpdb->postmeta, ['post_id' => $post_id], ['%d']);

        // Then delete post.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $post_deleted = $wpdb->delete($wpdb->posts, ['ID' => $post_id], ['%d']);

        if ($post_deleted) {
            Logger::debug("Cleaned up post {$post_id} from wp_posts and {$meta_deleted} meta entries from wp_postmeta after all operations completed");
        }
    }

    /**
     * Cleanup orphaned post meta on shutdown.
     * Removes any wp_postmeta entries whose posts no longer exist in wp_posts.
     *
     * @return void
     */
    public function cleanup_orphaned_meta(): void
    {
        global $wpdb;

        // Delete orphaned meta entries (where post_id doesn't exist in wp_posts).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $deleted = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL"
        );

        if ($deleted > 0) {
            Logger::debug("Cleaned up {$deleted} orphaned post meta entries on shutdown");
        }
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
