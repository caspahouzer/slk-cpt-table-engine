<?php

declare(strict_types=1);

namespace SLK\Cpt_Table_Engine\Helpers;

/**
 * Cache class.
 */
final class Cache
{
    /**
     * Cache group prefix.
     */
    private const GROUP_PREFIX = 'cpt_table_engine_';

    /**
     * Get a value from cache.
     *
     * @param string $key        The cache key.
     * @param string $post_type  The post type (used for cache group).
     * @param bool   &$found     Whether the key was found in cache.
     * @return mixed The cached value or false if not found.
     */
    public static function get(string $key, string $post_type, bool &$found = null)
    {
        $group = self::get_cache_group($post_type);
        return wp_cache_get($key, $group, false, $found);
    }

    /**
     * Set a value in cache.
     *
     * @param string $key        The cache key.
     * @param mixed  $value      The value to cache.
     * @param string $post_type  The post type (used for cache group).
     * @param int    $expiration Expiration time in seconds (0 = no expiration).
     * @return bool True on success, false on failure.
     */
    public static function set(string $key, $value, string $post_type, int $expiration = 0): bool
    {
        $group = self::get_cache_group($post_type);
        return wp_cache_set($key, $value, $group, $expiration);
    }

    /**
     * Delete a value from cache.
     *
     * @param string $key       The cache key.
     * @param string $post_type The post type (used for cache group).
     * @return bool True on success, false on failure.
     */
    public static function delete(string $key, string $post_type): bool
    {
        $group = self::get_cache_group($post_type);
        return wp_cache_delete($key, $group);
    }

    /**
     * Flush all cache for a specific post type.
     *
     * @param string $post_type The post type.
     * @return void
     */
    public static function flush_post_type(string $post_type): void
    {
        // WordPress doesn't support flushing specific groups,
        // so we'll use a cache key versioning approach.
        $version_key = self::get_version_key($post_type);
        $new_version = time();
        wp_cache_set($version_key, $new_version, self::GROUP_PREFIX . 'versions');
    }

    /**
     * Get the cache group for a post type.
     *
     * @param string $post_type The post type.
     * @return string The cache group name.
     */
    private static function get_cache_group(string $post_type): string
    {
        $version = self::get_cache_version($post_type);
        return self::GROUP_PREFIX . $post_type . '_v' . $version;
    }

    /**
     * Get the cache version for a post type.
     *
     * @param string $post_type The post type.
     * @return int The cache version.
     */
    private static function get_cache_version(string $post_type): int
    {
        $version_key = self::get_version_key($post_type);
        $version = wp_cache_get($version_key, self::GROUP_PREFIX . 'versions');

        if (false === $version) {
            $version = 1;
            wp_cache_set($version_key, $version, self::GROUP_PREFIX . 'versions');
        }

        return (int) $version;
    }

    /**
     * Get the version key for a post type.
     *
     * @param string $post_type The post type.
     * @return string The version key.
     */
    private static function get_version_key(string $post_type): string
    {
        return 'version_' . $post_type;
    }

    /**
     * Get a post from cache.
     *
     * @param int    $post_id   The post ID.
     * @param string $post_type The post type.
     * @return mixed The cached post or false if not found.
     */
    public static function get_post(int $post_id, string $post_type)
    {
        return self::get('post_' . $post_id, $post_type);
    }

    /**
     * Set a post in cache.
     *
     * @param int    $post_id   The post ID.
     * @param mixed  $post_data The post data.
     * @param string $post_type The post type.
     * @return bool True on success, false on failure.
     */
    public static function set_post(int $post_id, $post_data, string $post_type): bool
    {
        return self::set('post_' . $post_id, $post_data, $post_type);
    }

    /**
     * Delete a post from cache.
     *
     * @param int    $post_id   The post ID.
     * @param string $post_type The post type.
     * @return bool True on success, false on failure.
     */
    public static function delete_post(int $post_id, string $post_type): bool
    {
        return self::delete('post_' . $post_id, $post_type);
    }

    /**
     * Get post meta from cache.
     *
     * @param int    $post_id   The post ID.
     * @param string $meta_key  The meta key.
     * @param string $post_type The post type.
     * @return mixed The cached meta value or false if not found.
     */
    public static function get_meta(int $post_id, string $meta_key, string $post_type)
    {
        return self::get('meta_' . $post_id . '_' . $meta_key, $post_type);
    }

    /**
     * Set post meta in cache.
     *
     * @param int    $post_id    The post ID.
     * @param string $meta_key   The meta key.
     * @param mixed  $meta_value The meta value.
     * @param string $post_type  The post type.
     * @return bool True on success, false on failure.
     */
    public static function set_meta(int $post_id, string $meta_key, $meta_value, string $post_type): bool
    {
        return self::set('meta_' . $post_id . '_' . $meta_key, $meta_value, $post_type);
    }

    /**
     * Delete post meta from cache.
     *
     * @param int    $post_id   The post ID.
     * @param string $meta_key  The meta key.
     * @param string $post_type The post type.
     * @return bool True on success, false on failure.
     */
    public static function delete_meta(int $post_id, string $meta_key, string $post_type): bool
    {
        return self::delete('meta_' . $post_id . '_' . $meta_key, $post_type);
    }
}
