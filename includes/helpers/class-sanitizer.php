<?php

/**
 * Sanitizer helper class for CPT Table Engine.
 *
 * Centralized sanitization utilities.
 *
 * @package CPT_Table_Engine
 */

declare(strict_types=1);

namespace CPT_Table_Engine\Helpers;

/**
 * Sanitizer class.
 */
final class Sanitizer
{
    /**
     * Sanitize post data.
     *
     * @param array $data The post data to sanitize.
     * @return array Sanitized post data.
     */
    public static function sanitize_post_data(array $data): array
    {
        $sanitized = [];

        if (isset($data['post_title'])) {
            $sanitized['post_title'] = sanitize_text_field($data['post_title']);
        }

        if (isset($data['post_content'])) {
            $sanitized['post_content'] = wp_kses_post($data['post_content']);
        }

        if (isset($data['post_excerpt'])) {
            $sanitized['post_excerpt'] = sanitize_textarea_field($data['post_excerpt']);
        }

        if (isset($data['post_status'])) {
            $sanitized['post_status'] = sanitize_key($data['post_status']);
        }

        if (isset($data['post_name'])) {
            $sanitized['post_name'] = sanitize_title($data['post_name']);
        }

        if (isset($data['post_type'])) {
            $sanitized['post_type'] = sanitize_key($data['post_type']);
        }

        if (isset($data['post_author'])) {
            $sanitized['post_author'] = absint($data['post_author']);
        }

        if (isset($data['post_parent'])) {
            $sanitized['post_parent'] = absint($data['post_parent']);
        }

        if (isset($data['menu_order'])) {
            $sanitized['menu_order'] = absint($data['menu_order']);
        }

        if (isset($data['comment_status'])) {
            $sanitized['comment_status'] = sanitize_key($data['comment_status']);
        }

        if (isset($data['ping_status'])) {
            $sanitized['ping_status'] = sanitize_key($data['ping_status']);
        }

        if (isset($data['comment_count'])) {
            $sanitized['comment_count'] = absint($data['comment_count']);
        }

        if (isset($data['guid'])) {
            $sanitized['guid'] = esc_url_raw($data['guid']);
        }

        // Dates - validate format.
        foreach (['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'] as $date_field) {
            if (isset($data[$date_field])) {
                $sanitized[$date_field] = self::sanitize_datetime($data[$date_field]);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a datetime value.
     *
     * @param string $datetime The datetime string.
     * @return string Sanitized datetime in MySQL format.
     */
    public static function sanitize_datetime(string $datetime): string
    {
        // Validate and format datetime.
        $timestamp = strtotime($datetime);

        if (false === $timestamp) {
            return '0000-00-00 00:00:00';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Sanitize meta key.
     *
     * @param string $meta_key The meta key.
     * @return string Sanitized meta key.
     */
    public static function sanitize_meta_key(string $meta_key): string
    {
        return sanitize_key($meta_key);
    }

    /**
     * Sanitize meta value.
     *
     * Note: Meta values can be of any type, so we preserve the type
     * but sanitize strings.
     *
     * @param mixed $meta_value The meta value.
     * @return mixed Sanitized meta value.
     */
    public static function sanitize_meta_value($meta_value)
    {
        if (is_string($meta_value)) {
            return sanitize_text_field($meta_value);
        }

        return $meta_value;
    }

    /**
     * Sanitize post type slug.
     *
     * @param string $post_type The post type slug.
     * @return string Sanitized post type slug.
     */
    public static function sanitize_post_type(string $post_type): string
    {
        return sanitize_key($post_type);
    }

    /**
     * Sanitize an array of post types.
     *
     * @param array $post_types Array of post type slugs.
     * @return array Sanitized array of post type slugs.
     */
    public static function sanitize_post_types(array $post_types): array
    {
        return array_map([self::class, 'sanitize_post_type'], $post_types);
    }
}
