<?php

/**
 * Posts Migrator for CPT Table Engine.
 *
 * Handles bidirectional migration of post data.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Migrations;

use CPT_Table_Engine\Database\Table_Manager;
use CPT_Table_Engine\Database\Table_Schema;
use CPT_Table_Engine\Helpers\Logger;

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
        global $wpdb;

        $custom_table = Table_Manager::get_table_name($post_type, 'main');
        $batch_size = Migration_Manager::get_batch_size();

        // Get total count.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            )
        );

        if (! $total) {
            Logger::info("No posts found to migrate for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} posts for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of posts.
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
                    $post_type,
                    $batch_size,
                    $offset
                ),
                ARRAY_A
            );

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
            $sql = "INSERT INTO `{$custom_table}` 
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

            // Execute batch insert.
            $prepared = $wpdb->prepare($sql, $values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query($prepared); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            if (false === $result) {
                Logger::error("Failed to migrate posts batch at offset {$offset}: " . $wpdb->last_error);
                return new \WP_Error('migration_failed', __('Failed to migrate posts.', 'cpt-table-engine'));
            }

            $migrated += count($posts);
            $offset += $batch_size;

            // Update progress.
            Migration_Manager::update_migration_status(
                $post_type,
                'in_progress',
                /* translators: %1$d: Number of migrated posts, %2$d: Total number of posts. */
                sprintf(__('Migrated %1$d of %2$d posts...', 'cpt-table-engine'), $migrated, $total),
                $migrated,
                $total
            );

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
        global $wpdb;

        $custom_table = Table_Manager::get_table_name($post_type, 'main');
        $batch_size = Migration_Manager::get_batch_size();

        // Get total count.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$custom_table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (! $total) {
            Logger::info("No posts found to migrate back for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} posts back to wp_posts for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of posts.
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$custom_table}` ORDER BY ID ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $batch_size,
                    $offset
                ),
                ARRAY_A
            );

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
            $sql = "INSERT INTO `{$wpdb->posts}` 
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

            // Execute batch insert.
            $prepared = $wpdb->prepare($sql, $values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query($prepared); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            if (false === $result) {
                Logger::error("Failed to migrate posts batch at offset {$offset}: " . $wpdb->last_error);
                return new \WP_Error('migration_failed', __('Failed to migrate posts back to wp_posts.', 'cpt-table-engine'));
            }

            $migrated += count($posts);
            $offset += $batch_size;

            // Update progress.
            Migration_Manager::update_migration_status(
                $post_type,
                'in_progress',
                /* translators: %1$d: Number of migrated posts, %2$d: Total number of posts. */
                sprintf(__('Migrated %1$d of %2$d posts back to wp_posts...', 'cpt-table-engine'), $migrated, $total),
                $migrated,
                $total
            );

            Logger::debug("Migrated batch: {$migrated}/{$total} posts back to wp_posts");
        }

        Logger::info("Successfully migrated {$migrated} posts back to wp_posts");

        return true;
    }
}
