<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Migrations;

use SLK\Cpt_Table_Engine\Helpers\Logger;

/**
 * Relationship Updater class.
 */
final class Relationship_Updater
{
    /**
     * Update post relationships after re-IDing.
     *
     * @param array $id_map
     * @return void
     */
    public static function update(array $id_map): void
    {
        if (empty($id_map)) {
            return;
        }

        Logger::info('Updating post relationships for ' . count($id_map) . ' re-IDed posts...');

        self::update_post_content($id_map);
        self::update_post_meta($id_map);
        self::update_post_parent($id_map);
    }

    /**
     * Update post content.
     *
     * @param array $id_map
     */
    private static function update_post_content(array $id_map): void
    {
        global $wpdb;

        foreach ($id_map as $old_id => $new_id) {
            // Simple search and replace. This is not perfect but covers many cases.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
                    'p=' . $old_id,
                    'p=' . $new_id
                )
            );
        }
    }

    /**
     * Update post meta.
     *
     * @param array $id_map
     */
    private static function update_post_meta(array $id_map): void
    {
        global $wpdb;

        $old_ids = array_keys($id_map);
        $placeholders = implode(',', array_fill(0, count($old_ids), '%d'));

        // Find meta values that contain the old IDs
        $meta_to_update = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value IN ($placeholders)",
                ...$old_ids
            )
        );

        foreach ($meta_to_update as $meta) {
            $old_id = (int) $meta->meta_value;
            if (isset($id_map[$old_id])) {
                $new_id = $id_map[$old_id];
                $wpdb->update(
                    $wpdb->postmeta,
                    ['meta_value' => (string) $new_id],
                    ['meta_id' => $meta->meta_id]
                );
            }
        }
    }

    /**
     * Update post parent.
     *
     * @param array $id_map
     */
    private static function update_post_parent(array $id_map): void
    {
        global $wpdb;

        foreach ($id_map as $old_id => $new_id) {
            $wpdb->update(
                $wpdb->posts,
                ['post_parent' => $new_id],
                ['post_parent' => $old_id]
            );
        }
    }
}
