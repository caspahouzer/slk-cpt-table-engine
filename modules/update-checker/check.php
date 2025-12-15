<?php

/**
 * Update Checker integration.
 *
 * set slk_update_checker_text_domain and slk_update_checker_version filters before including this file.
 * 
 * require_once PLUGIN_DIR . 'modules/update-checker/check.php';
 */
add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $text_domain = apply_filters('slk_update_checker_text_domain', '');
    $version = apply_filters('slk_update_checker_version', '');

    if (empty($text_domain) || empty($version)) {
        error_log('[CPT Table Engine] [DEBUG] Text domain or version not provided for update checker.');
        return $transient;
    }

    $url = 'https://slk-communications.de/plugins/' . $text_domain . '/version.json?v=' . time();
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);
    if (is_wp_error($response)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response));

    if (version_compare($version, $data->version, '<')) {
        $transient->response[$text_domain . '/' . $text_domain . '.php'] = (object) [
            'slug'        => $text_domain,
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'tested'      => $data->tested,
            'requires'    => $data->requires,
        ];
    }

    return $transient;
});
