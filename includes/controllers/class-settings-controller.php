<?php

/**
 * Settings Controller for CPT Table Engine.
 *
 * Manages plugin settings and CPT configuration.
 *
 * @package SLK_Cpt_Table_Engine
 */

declare(strict_types=1);

namespace SLK_Cpt_Table_Engine\Controllers;

use SLK_Cpt_Table_Engine\Helpers\Sanitizer;
use SLK_Cpt_Table_Engine\Helpers\Validator;
use SLK_Cpt_Table_Engine\Database\Table_Manager;

/**
 * Settings Controller class.
 */
final class Settings_Controller
{
    /**
     * Option name for enabled CPTs.
     */
    private const OPTION_ENABLED_CPTS = 'cpt_table_engine_enabled_cpts';

    /**
     * Cache group for WordPress object cache.
     */
    private const CACHE_GROUP = 'cpt_table_engine';

    /**
     * Cache key for enabled CPTs.
     */
    private const CACHE_KEY = 'enabled_cpts';

    /**
     * Static cache for enabled CPTs (request-level).
     *
     * @var array<string>|null
     */
    private static ?array $enabled_cpts_cache = null;

    /**
     * Get list of CPTs using custom tables.
     *
     * @return array<string> Array of post type slugs.
     */
    public static function get_enabled_cpts(): array
    {
        // Check static cache first (request-level).
        if (null !== self::$enabled_cpts_cache) {
            return self::$enabled_cpts_cache;
        }

        // Check WordPress object cache (persistent).
        $cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
        if (false !== $cached && is_array($cached)) {
            self::$enabled_cpts_cache = $cached;
            return $cached;
        }

        // Fetch from database.
        $enabled = get_option(self::OPTION_ENABLED_CPTS, []);

        if (! is_array($enabled)) {
            $enabled = [];
        }

        $enabled = array_values($enabled);

        // Store in both caches.
        self::$enabled_cpts_cache = $enabled;
        wp_cache_set(self::CACHE_KEY, $enabled, self::CACHE_GROUP, HOUR_IN_SECONDS);

        return $enabled;
    }

    /**
     * Clear the enabled CPTs cache.
     *
     * @return void
     */
    private static function clear_cache(): void
    {
        self::$enabled_cpts_cache = null;
        wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
    }

    /**
     * Enable custom table for a CPT.
     *
     * @param string $post_type The post type slug.
     * @return bool True on success, false on failure.
     */
    public static function enable_cpt(string $post_type): bool
    {
        $post_type = Sanitizer::sanitize_post_type($post_type);

        if (! Validator::is_custom_post_type($post_type)) {
            return false;
        }

        $enabled = self::get_enabled_cpts();

        if (in_array($post_type, $enabled, true)) {
            return true; // Already enabled.
        }

        $enabled[] = $post_type;

        $result = update_option(self::OPTION_ENABLED_CPTS, $enabled, false);

        if ($result) {
            self::clear_cache();
        }

        return $result;
    }

    /**
     * Disable custom table for a CPT.
     *
     * @param string $post_type The post type slug.
     * @return bool True on success, false on failure.
     */
    public static function disable_cpt(string $post_type): bool
    {
        $post_type = Sanitizer::sanitize_post_type($post_type);

        $enabled = self::get_enabled_cpts();

        $key = array_search($post_type, $enabled, true);

        if (false === $key) {
            return true; // Already disabled.
        }

        unset($enabled[$key]);
        $enabled = array_values($enabled);

        $result = update_option(self::OPTION_ENABLED_CPTS, $enabled, false);

        if ($result) {
            self::clear_cache();
        }

        return $result;
    }

    /**
     * Check if a CPT uses custom table.
     *
     * @param string $post_type The post type slug.
     * @return bool True if enabled, false otherwise.
     */
    public static function is_enabled(string $post_type): bool
    {
        $enabled = self::get_enabled_cpts();
        return in_array($post_type, $enabled, true);
    }

    /**
     * Get all registered custom post types.
     *
     * @return array<string, object> Array of post type objects keyed by slug.
     */
    public static function get_all_custom_post_types(): array
    {
        $post_types = get_post_types(
            [
                'public'   => true,
                '_builtin' => false,
            ],
            'objects'
        );

        if (! is_array($post_types)) {
            return [];
        }

        return $post_types;
    }

    /**
     * Get settings for display in admin.
     *
     * @return array Array of CPT settings with status.
     */
    public static function get_settings_for_display(): array
    {
        $custom_post_types = self::get_all_custom_post_types();
        $enabled_cpts = self::get_enabled_cpts();

        $settings = [];

        foreach ($custom_post_types as $slug => $post_type_object) {
            $is_enabled = in_array($slug, $enabled_cpts, true);
            $count = 0;

            if ($is_enabled) {
                // Get count from custom table.
                if (Table_Manager::verify_tables($slug)) {
                    global $wpdb;
                    $table = Table_Manager::get_table_name($slug, 'main');
                    if ($table) {
                        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
                    }
                }
            } else {
                // Get count from wp_posts.
                $counts = wp_count_posts($slug);
                $count = (int) $counts->publish;
            }

            $settings[] = [
                'slug'    => $slug,
                'label'   => $post_type_object->labels->name ?? $slug,
                'enabled' => $is_enabled,
                'count'   => $count,
            ];
        }

        return $settings;
    }
}
