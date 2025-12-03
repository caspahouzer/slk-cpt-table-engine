<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Migrations;

use SLK\Cpt_Table_Engine\Database\Table_Manager;
use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * Meta Migrator class.
 */
final class Meta_Migrator
{
    /**
     * Migrate post meta from wp_postmeta to custom meta table.
     *
     * @param string $post_type The post type to migrate.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function migrate_to_custom_table(string $post_type)
    {
        global $wpdb;

        $custom_table = Table_Manager::get_table_name($post_type, 'meta');
        $main_table = Table_Manager::get_table_name($post_type, 'main');

        if (! $custom_table || ! $main_table) {
            return new \WP_Error('invalid_table', __('Invalid custom table for post type.', 'slk-cpt-table-engine'));
        }

        $batch_size = Migration_Manager::get_batch_size();

        // Get total count of meta entries for this post type.
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN %i p ON pm.post_id = p.ID",
                $main_table
            )
        );

        if (! $total) {
            Logger::info("No meta entries found to migrate for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} meta entries for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of meta entries.
            $meta_entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pm.* FROM {$wpdb->postmeta} pm
                    INNER JOIN %i p ON pm.post_id = p.ID
                    ORDER BY pm.meta_id ASC
                    LIMIT %d OFFSET %d",
                    $main_table,
                    $batch_size,
                    $offset
                ),
                ARRAY_A
            );

            if (empty($meta_entries)) {
                break;
            }

            // Prepare batch insert.
            $values = [];
            $placeholders = [];

            foreach ($meta_entries as $meta) {
                $placeholders[] = '(%d, %d, %s, %s)';

                $values[] = $meta['meta_id'];
                $values[] = $meta['post_id'];
                $values[] = $meta['meta_key'];
                $values[] = $meta['meta_value'];
            }

            // Build insert query.
            $sql = "INSERT INTO `{$custom_table}` 
				(meta_id, post_id, meta_key, meta_value) 
				VALUES " . implode(', ', $placeholders) . '
				ON DUPLICATE KEY UPDATE
				meta_key = VALUES(meta_key),
				meta_value = VALUES(meta_value)';

            // Execute batch insert.
            $prepared = $wpdb->prepare($sql, $values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query($prepared); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            if (false === $result) {
                Logger::error("Failed to migrate meta batch at offset {$offset}: " . $wpdb->last_error);
                /* translators: %s: post type */
                return new \WP_Error('migration_failed', __('Failed to migrate post meta.', 'slk-cpt-table-engine'));
            }

            // Delete meta from wp_postmeta after successful migration to custom table.
            $meta_ids = array_column($meta_entries, 'meta_id');
            if (!empty($meta_ids)) {
                $meta_placeholders = implode(',', array_fill(0, count($meta_ids), '%d'));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $meta_placeholders is safe, constructed above
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk migration operation
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($meta_placeholders)",
                        ...$meta_ids
                    )
                );
                Logger::debug("Deleted " . count($meta_ids) . " meta entries from wp_postmeta");
            }

            $migrated += count($meta_entries);
            $offset += $batch_size;

            // Update progress.
            Migration_Manager::update_migration_status(
                $post_type,
                'in_progress',
                /* translators: %1$d: Number of migrated entries, %2$d: Total number of entries. */
                /* translators: %1$d: migrated meta entries, %2$d: total meta entries */
                sprintf(__('Migrated %1$d of %2$d meta entries...', 'slk-cpt-table-engine'), $migrated, $total),
                $migrated,
                $total
            );

            Logger::debug("Migrated meta batch: {$migrated}/{$total} entries");
        }

        Logger::info("Successfully migrated {$migrated} meta entries to custom table");

        return true;
    }

    /**
     * Migrate post meta from custom meta table back to wp_postmeta.
     *
     * @param string $post_type The post type to migrate.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function migrate_to_wp_posts(string $post_type)
    {
        global $wpdb;

        $custom_table = Table_Manager::get_table_name($post_type, 'meta');
        if (! $custom_table) {
            return new \WP_Error('invalid_table', __('Invalid custom table for post type.', 'slk-cpt-table-engine'));
        }
        $batch_size = Migration_Manager::get_batch_size();

        // Get total count.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$custom_table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (! $total) {
            Logger::info("No meta entries found to migrate back for post type: {$post_type}");
            return true;
        }

        Logger::info("Migrating {$total} meta entries back to wp_postmeta for post type: {$post_type}");

        $offset = 0;
        $migrated = 0;

        while ($offset < $total) {
            // Get batch of meta entries.
            $meta_entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$custom_table}` ORDER BY meta_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $batch_size,
                    $offset
                ),
                ARRAY_A
            );

            if (empty($meta_entries)) {
                break;
            }

            // Prepare batch insert.
            $values = [];
            $placeholders = [];

            foreach ($meta_entries as $meta) {
                $placeholders[] = '(%d, %d, %s, %s)';

                $values[] = $meta['meta_id'];
                $values[] = $meta['post_id'];
                $values[] = $meta['meta_key'];
                $values[] = $meta['meta_value'];
            }

            // Build insert query.
            $sql = "INSERT INTO `{$wpdb->postmeta}` 
				(meta_id, post_id, meta_key, meta_value) 
				VALUES " . implode(', ', $placeholders) . '
				ON DUPLICATE KEY UPDATE
				meta_key = VALUES(meta_key),
				meta_value = VALUES(meta_value)';

            // Execute batch insert.
            $prepared = $wpdb->prepare($sql, $values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query($prepared); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            if (false === $result) {
                Logger::error("Failed to migrate meta batch at offset {$offset}: " . $wpdb->last_error);
                /* translators: %s: post type */
                return new \WP_Error('migration_failed', __('Failed to migrate post meta back to wp_postmeta.', 'slk-cpt-table-engine'));
            }

            $migrated += count($meta_entries);
            $offset += $batch_size;

            // Update progress.
            Migration_Manager::update_migration_status(
                $post_type,
                'in_progress',
                /* translators: %1$d: Number of migrated entries, %2$d: Total number of entries. */
                /* translators: %1$d: migrated meta entries, %2$d: total meta entries */
                sprintf(__('Migrated %1$d of %2$d meta entries back to wp_postmeta...', 'slk-cpt-table-engine'), $migrated, $total),
                $migrated,
                $total
            );

            Logger::debug("Migrated meta batch: {$migrated}/{$total} entries back to wp_postmeta");
        }

        Logger::info("Successfully migrated {$migrated} meta entries back to wp_postmeta");

        return true;
    }
}
