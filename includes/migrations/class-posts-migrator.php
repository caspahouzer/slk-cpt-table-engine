<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Migrations;

use SLK\Cpt_Table_Engine\Database\Table_Manager;
use SLK\Cpt_Table_Engine\Database\Table_Schema;
use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * Posts Migrator class.
 */
final class Posts_Migrator
{
    /**
     * Migrate posts from wp_posts to custom table.
     *
     * @param string $post_type The post type to migrate.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function migrate_to_custom_table(string $post_type)
    {
        $custom_table = Table_Manager::get_table_name($post_type, 'main');
        if (! $custom_table) {
            /* translators: %s: post type */
            return new \WP_Error('invalid_table', __('Invalid custom table for post type.', 'slk-cpt-table-engine'));
        }
        $batch_size = Migration_Manager::get_batch_size();

        // Get total count.
        $total = self::count_wp_posts($post_type);

        if (! $total) {
            Logger::info("No posts found to migrate for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} posts for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of posts.
            $posts = self::get_wp_posts($post_type, $batch_size, $offset);

            if (empty($posts)) {
                break;
            }

            // Prepare batch insert.
            $values = [];
            $placeholders = [];

            foreach ($posts as $post) {
                $placeholders[] = '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %d, %d, %s, %s, %d, %s)';

                $values[] = $post->ID;
                $values[] = $post->post_title;
                $values[] = $post->post_content;
                $values[] = $post->post_excerpt;
                $values[] = $post->post_status;
                $values[] = $post->post_date;
                $values[] = $post->post_date_gmt;
                $values[] = $post->post_modified;
                $values[] = $post->post_modified_gmt;
                $values[] = $post->post_author;
                $values[] = $post->post_name;
                $values[] = $post->post_type;
                $values[] = $post->post_parent;
                $values[] = $post->menu_order;
                $values[] = $post->comment_status;
                $values[] = $post->ping_status;
                $values[] = $post->comment_count;
                $values[] = $post->guid;
            }

            // Build insert query.
            $sql = self::build_insert_query($custom_table, $placeholders);

            // Execute batch insert.
            if (false === self::execute_query($sql, $values)) {
                Logger::error("Failed to migrate posts batch at offset {$offset}");
                /* translators: %s: post type */
                return new \WP_Error('migration_failed', __('Failed to migrate posts.', 'slk-cpt-table-engine'));
            }

            $migrated += count($posts);
            $offset += $batch_size;

            // Update progress.
            self::update_migration_status($post_type, $migrated, $total);

            Logger::debug("Migrated batch: {$migrated}/{$total} posts");
        }

        Logger::info("Successfully migrated {$migrated} posts to custom table");

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
        $custom_table = Table_Manager::get_table_name($post_type, 'main');
        if (! $custom_table) {
            return new \WP_Error('invalid_table', __('Invalid custom table for post type.', 'slk-cpt-table-engine'));
        }
        $batch_size = Migration_Manager::get_batch_size();

        // Get total count.
        $total = self::count_posts_in_custom_table($custom_table);

        if (! $total) {
            Logger::info("No posts found to migrate back for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} posts back to wp_posts for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of posts.
            $posts = self::get_posts_from_custom_table($custom_table, $batch_size, $offset);

            if (empty($posts)) {
                break;
            }

            // Prepare batch insert.
            $values = [];
            $placeholders = [];

            foreach ($posts as $post) {
                $placeholders[] = '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %d, %d, %s, %s, %d, %s)';

                $values[] = $post['ID'];
                $values[] = $post['post_title'];
                $values[] = $post['post_content'];
                $values[] = $post['post_excerpt'];
                $values[] = $post['post_status'];
                $values[] = $post['post_date'];
                $values[] = $post['post_date_gmt'];
                $values[] = $post['post_modified'];
                $values[] = $post['post_modified_gmt'];
                $values[] = $post['post_author'];
                $values[] = $post['post_name'];
                $values[] = $post['post_type'];
                $values[] = $post['post_parent'];
                $values[] = $post['menu_order'];
                $values[] = $post['comment_status'];
                $values[] = $post['ping_status'];
                $values[] = $post['comment_count'];
                $values[] = $post['guid'];
            }

            // Build insert query.
            global $wpdb;
            $sql = self::build_insert_query($wpdb->posts, $placeholders);

            // Execute batch insert.
            if (false === self::execute_query($sql, $values)) {
                Logger::error("Failed to migrate posts batch at offset {$offset}");
                /* translators: %s: post type */
                return new \WP_Error('migration_failed', __('Failed to migrate posts back to wp_posts.', 'slk-cpt-table-engine'));
            }

            $migrated += count($posts);
            $offset += $batch_size;

            // Update progress.
            self::update_migration_status($post_type, $migrated, $total, true);

            Logger::debug("Migrated batch: {$migrated}/{$total} posts back to wp_posts");
        }

        Logger::info("Successfully migrated {$migrated} posts back to wp_posts");

        return true;
    }

    /**
     * Count posts in wp_posts.
     *
     * @param string $post_type The post type to count.
     * @return int The number of posts.
     */
    private static function count_wp_posts(string $post_type): int
    {
        $counts = wp_count_posts($post_type);
        return (int) array_sum((array) $counts);
    }

    /**
     * Get posts from wp_posts.
     *
     * @param string $post_type The post type to get.
     * @param int    $batch_size The batch size.
     * @param int    $offset The offset.
     * @return array The posts.
     */
    private static function get_wp_posts(string $post_type, int $batch_size, int $offset): array
    {
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'post_status'    => 'any',
        ];
        return get_posts($args);
    }

    /**
     * Build insert query.
     *
     * @param string $table_name The table name.
     * @param array  $placeholders The placeholders.
     * @return string The query.
     */
    private static function build_insert_query(string $table_name, array $placeholders): string
    {
        return "INSERT INTO `{$table_name}` 
            (ID, post_title, post_content, post_excerpt, post_status, post_date, post_date_gmt, 
            post_modified, post_modified_gmt, post_author, post_name, post_type, post_parent, 
            menu_order, comment_status, ping_status, comment_count, guid) 
            VALUES " . implode(', ', $placeholders) . '
            ON DUPLICATE KEY UPDATE
            post_title = VALUES(post_title),
            post_content = VALUES(post_content),
            post_excerpt = VALUES(post_excerpt),
            post_status = VALUES(post_status),
            post_date = VALUES(post_date),
            post_date_gmt = VALUES(post_date_gmt),
            post_modified = VALUES(post_modified),
            post_modified_gmt = VALUES(post_modified_gmt),
            post_author = VALUES(post_author),
            post_name = VALUES(post_name),
            post_parent = VALUES(post_parent),
            menu_order = VALUES(menu_order),
            comment_status = VALUES(comment_status),
            ping_status = VALUES(ping_status),
            comment_count = VALUES(comment_count),
            guid = VALUES(guid)';
    }

    /**
     * Execute a query.
     *
     * @param string $sql The query.
     * @param array  $values The values.
     * @return bool|int The result.
     */
    private static function execute_query(string $sql, array $values)
    {
        global $wpdb;
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql contains placeholders, values are passed safely to prepare().
        $prepared = $wpdb->prepare($sql, $values);
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration batch operations, not cacheable.
        return $wpdb->query($prepared);
    }

    /**
     * Update migration status.
     *
     * @param string $post_type The post type.
     * @param int    $migrated The number of migrated posts.
     * @param int    $total The total number of posts.
     * @param bool   $is_rollback Whether it is a rollback.
     */
    private static function update_migration_status(string $post_type, int $migrated, int $total, bool $is_rollback = false)
    {
        $message = $is_rollback ?
            /* translators: %1$d: Number of migrated posts, %2$d: Total number of posts. */
            /* translators: %1$d: migrated posts, %2$d: total posts */
            sprintf(__('Migrated %1$d of %2$d posts back to wp_posts...', 'slk-cpt-table-engine'), $migrated, $total) :
            /* translators: %1$d: Number of migrated posts, %2$d: Total number of posts. */
            /* translators: %1$d: migrated posts, %2$d: total posts */
            sprintf(__('Migrated %1$d of %2$d posts...', 'slk-cpt-table-engine'), $migrated, $total);

        Migration_Manager::update_migration_status(
            $post_type,
            'in_progress',
            $message,
            $migrated,
            $total
        );
    }

    /**
     * Count posts in a custom table.
     *
     * @param string $table_name The name of the custom table.
     * @return int The number of posts in the table.
     */
    private static function count_posts_in_custom_table(string $table_name): int
    {
        global $wpdb;
        // Table name is sanitized by Table_Manager and cannot use placeholders.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "SELECT COUNT(*) FROM `{$table_name}`";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input, table name validated.
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get posts from a custom table.
     *
     * @param string $table_name The name of the custom table.
     * @param int    $batch_size The number of posts to retrieve.
     * @param int    $offset The offset for the query.
     * @return array The posts from the custom table.
     */
    private static function get_posts_from_custom_table(string $table_name, int $batch_size, int $offset): array
    {
        global $wpdb;
        // Table name is sanitized by Table_Manager and cannot use placeholders.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name validated by Table_Manager, not user input.
        $query = $wpdb->prepare(
            "SELECT * FROM `{$table_name}` ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
        return $wpdb->get_results($query, ARRAY_A);
    }
}
