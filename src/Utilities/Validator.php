<?php

declare(strict_types=1);

namespace SLK\CptTableEngine\Utilities;

/**
 * Validator class.
 *
 * @package SLK\CptTableEngine
 */
final class Validator
{
    /**
     * Validate post data.
     *
     * @param array $data The post data to validate.
     * @return true|\WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_post_data(array $data)
    {
        // Post title is required.
        if (empty($data['post_title'])) {
            return new \WP_Error('missing_post_title', __('Post title is required.', 'slk-cpt-table-engine'));
        }

        // Post type is required.
        if (empty($data['post_type'])) {
            return new \WP_Error('missing_post_type', __('Post type is required.', 'slk-cpt-table-engine'));
        }

        // Validate post status.
        if (isset($data['post_status']) && ! self::is_valid_post_status($data['post_status'])) {
            return new \WP_Error('invalid_post_status', __('Invalid post status.', 'slk-cpt-table-engine'));
        }

        // Validate post author.
        if (isset($data['post_author']) && ! self::is_valid_user_id((int) $data['post_author'])) {
            return new \WP_Error('invalid_post_author', __('Invalid post author.', 'slk-cpt-table-engine'));
        }

        return true;
    }

    /**
     * Validate if a post status is valid.
     *
     * @param string $status The post status.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_post_status(string $status): bool
    {
        $valid_statuses = get_post_stati();
        return isset($valid_statuses[$status]);
    }

    /**
     * Validate if a user ID exists.
     *
     * @param int $user_id The user ID.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_user_id(int $user_id): bool
    {
        if (0 === $user_id) {
            return true; // Allow 0 for system posts.
        }

        return false !== get_userdata($user_id);
    }

    /**
     * Validate if a post type is registered.
     *
     * @param string $post_type The post type slug.
     * @return bool True if registered, false otherwise.
     */
    public static function is_valid_post_type(string $post_type): bool
    {
        return post_type_exists($post_type);
    }

    /**
     * Validate if a post type is a custom post type (not built-in).
     *
     * @param string $post_type The post type slug.
     * @return bool True if custom, false otherwise.
     */
    public static function is_custom_post_type(string $post_type): bool
    {
        $builtin_types = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];

        return self::is_valid_post_type($post_type) && ! in_array($post_type, $builtin_types, true);
    }

    /**
     * Validate meta key.
     *
     * @param string $meta_key The meta key.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_meta_key(string $meta_key): bool
    {
        // Meta key cannot be empty.
        if (empty($meta_key)) {
            return false;
        }

        // Meta key should not exceed 255 characters.
        if (strlen($meta_key) > 255) {
            return false;
        }

        return true;
    }

    /**
     * Validate datetime string.
     *
     * @param string $datetime The datetime string.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_datetime(string $datetime): bool
    {
        // Allow zero datetime.
        if ('0000-00-00 00:00:00' === $datetime) {
            return true;
        }

        // Validate datetime format.
        $timestamp = strtotime($datetime);
        return false !== $timestamp;
    }

    /**
     * Validate post ID.
     *
     * @param int    $post_id   The post ID.
     * @param string $post_type Optional. The expected post type.
     * @return bool True if valid, false otherwise.
     */
    public static function is_valid_post_id(int $post_id, string $post_type = ''): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        // If post type is specified, verify it matches.
        if (! empty($post_type)) {
            $post = get_post($post_id);
            return $post && $post->post_type === $post_type;
        }

        return true;
    }
}
