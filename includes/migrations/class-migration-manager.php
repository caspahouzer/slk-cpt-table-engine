<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Migrations;

use SLK\Cpt_Table_Engine\Database\Table_Manager;
use SLK\Cpt_Table_Engine\Controllers\Settings_Controller;
use SLK\Cpt_Table_Engine\Helpers\Logger;
use SLK\Cpt_Table_Engine\Helpers\Cache;

/**
 * Migration Manager class.
 */
final class Migration_Manager
{
    /**
     * Batch size for migrations.
     */
    private const BATCH_SIZE = 100;

    /**
     * Transient prefix for migration status.
     */
    private const TRANSIENT_PREFIX = 'cpt_table_engine_migration_';

    /**
     * Migrate posts from wp_posts to custom table.
     *
     * @param string $post_type The post type to migrate.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function migrate_to_custom_table(string $post_type)
    {
        Logger::info("Starting migration to custom table for post type: {$post_type}");

        // Verify post type is valid.
        if (! post_type_exists($post_type)) {
            /* translators: %s is the post type */
            return new \WP_Error('invalid_post_type', __('Invalid post type.', 'slk-cpt-table-engine'));
        }

        // Create tables if they don't exist.
        if (! Table_Manager::verify_tables($post_type)) {
            if (! Table_Manager::create_tables($post_type)) {
                return new \WP_Error('table_creation_failed', __('Failed to create custom tables.', 'slk-cpt-table-engine'));
            }
        }

        // Initialize migration status.
        self::init_migration_status($post_type, 'to_custom');

        // Migrate posts.
        $posts_result = Posts_Migrator::migrate_to_custom_table($post_type);
        if (is_wp_error($posts_result)) {
            self::update_migration_status($post_type, 'failed', $posts_result->get_error_message());
            return $posts_result;
        }

        // Migrate meta.
        $meta_result = Meta_Migrator::migrate_to_custom_table($post_type);
        if (is_wp_error($meta_result)) {
            self::update_migration_status($post_type, 'failed', $meta_result->get_error_message());
            return $meta_result;
        }

        // Update settings to mark CPT as enabled.
        Settings_Controller::enable_cpt($post_type);

        // Reinitialize interceptors to register hooks with new state.
        \SLK\Cpt_Table_Engine\Bootstrap::instance()->reinit();

        // Clear cache.
        Cache::flush_post_type($post_type);
        Table_Manager::clear_verification_cache($post_type);

        // Mark migration as complete.
        self::update_migration_status($post_type, 'completed');

        Logger::info("Completed migration to custom table for post type: {$post_type}");

        return true;
    }

    /**
     * Migrate posts from custom table back to wp_posts.
     *
     * @param string $post_type The post type to migrate.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function migrate_to_wp_posts(string $post_type)
    {
        Logger::info("Starting migration to wp_posts for post type: {$post_type}");

        // Verify tables exist.
        if (! Table_Manager::verify_tables($post_type)) {
            return new \WP_Error('tables_not_found', __('Custom tables do not exist.', 'slk-cpt-table-engine'));
        }

        // Initialize migration status.
        self::init_migration_status($post_type, 'to_wp_posts');

        // IMPORTANT: Disable CPT and reinitialize interceptors BEFORE migration
        // to prevent interceptors from interfering with the reverse migration process.
        Settings_Controller::disable_cpt($post_type);
        \SLK\Cpt_Table_Engine\Bootstrap::instance()->reinit();
        Cache::flush_post_type($post_type);
        Table_Manager::clear_verification_cache($post_type);

        // Migrate posts.
        $posts_result = Posts_Migrator::migrate_to_wp_posts($post_type);
        if (is_wp_error($posts_result)) {
            self::update_migration_status($post_type, 'failed', $posts_result->get_error_message());
            return $posts_result;
        }

        // Migrate meta.
        $meta_result = Meta_Migrator::migrate_to_wp_posts($post_type);
        if (is_wp_error($meta_result)) {
            self::update_migration_status($post_type, 'failed', $meta_result->get_error_message());
            return $meta_result;
        }

        // Clear any remaining caches.
        wp_cache_flush();

        // Mark migration as complete.
        self::update_migration_status($post_type, 'completed');

        // Drop custom tables after successful migration back to wp_posts.
        $drop_result = Table_Manager::drop_tables($post_type);
        if (! $drop_result) {
            Logger::warning("Migration completed but failed to drop tables for post type: {$post_type}. Tables can be dropped manually if needed.");
            // Don't fail the migration - data is already safely in wp_posts/wp_postmeta
        }

        Logger::info("Completed migration to wp_posts for post type: {$post_type}");

        return true;
    }

    /**
     * Get migration status.
     *
     * @param string $post_type The post type.
     * @return array Migration status data.
     */
    public static function get_migration_status(string $post_type): array
    {
        $transient_key = self::TRANSIENT_PREFIX . $post_type;
        $status = get_transient($transient_key);

        if (false === $status) {
            return [
                'status'    => 'idle',
                'direction' => '',
                'progress'  => 0,
                'total'     => 0,
                'message'   => '',
            ];
        }

        return $status;
    }

    /**
     * Initialize migration status.
     *
     * @param string $post_type The post type.
     * @param string $direction Migration direction ('to_custom' or 'to_wp_posts').
     * @return void
     */
    private static function init_migration_status(string $post_type, string $direction): void
    {
        $transient_key = self::TRANSIENT_PREFIX . $post_type;

        $status = [
            'status'    => 'in_progress',
            'direction' => $direction,
            'progress'  => 0,
            'total'     => 0,
            'message'   => __('Migration started...', 'slk-cpt-table-engine'),
        ];

        set_transient($transient_key, $status, HOUR_IN_SECONDS);
    }

    /**
     * Update migration status.
     *
     * @param string $post_type The post type.
     * @param string $status    Status ('in_progress', 'completed', 'failed').
     * @param string $message   Optional status message.
     * @param int    $progress  Optional progress count.
     * @param int    $total     Optional total count.
     * @return void
     */
    public static function update_migration_status(string $post_type, string $status, string $message = '', int $progress = 0, int $total = 0): void
    {
        $transient_key = self::TRANSIENT_PREFIX . $post_type;
        $current_status = get_transient($transient_key);

        if (false === $current_status) {
            $current_status = [
                'status'    => 'idle',
                'direction' => '',
                'progress'  => 0,
                'total'     => 0,
                'message'   => '',
            ];
        }

        $current_status['status'] = $status;

        if (! empty($message)) {
            $current_status['message'] = $message;
        }

        if ($progress > 0) {
            $current_status['progress'] = $progress;
        }

        if ($total > 0) {
            $current_status['total'] = $total;
        }

        set_transient($transient_key, $current_status, HOUR_IN_SECONDS);
    }

    /**
     * Clear migration status.
     *
     * @param string $post_type The post type.
     * @return void
     */
    public static function clear_migration_status(string $post_type): void
    {
        $transient_key = self::TRANSIENT_PREFIX . $post_type;
        delete_transient($transient_key);
    }

    /**
     * Get batch size for migrations.
     *
     * @return int Batch size.
     */
    public static function get_batch_size(): int
    {
        /**
         * Filter the migration batch size.
         *
         * @param int $batch_size The batch size.
         */
        return apply_filters('cpt_table_engine_migration_batch_size', self::BATCH_SIZE);
    }
}
