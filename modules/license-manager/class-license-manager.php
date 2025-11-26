<?php

/**
 * License Manager main controller.
 *
 * Handles license operations and data storage.
 *
 * @package SLK\License_Manager
 */

declare(strict_types=1);

namespace SLK\License_Manager;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * License Manager class.
 * 
 * Singleton class for managing license operations.
 */
class License_Manager
{
    /**
     * Singleton instance.
     *
     * @var License_Manager|null
     */
    private static ?License_Manager $instance = null;

    /**
     * WordPress option keys.
     */
    public const OPTION_LICENSE_KEY = 'cpt_table_engine_license_key';
    public const OPTION_ACTIVATION_TOKEN = 'cpt_table_engine_activation_token';
    public const OPTION_LICENSE_STATUS = 'cpt_table_engine_license_status';
    public const OPTION_LICENSE_COUNTS = 'cpt_table_engine_license_counts';
    public const TRANSIENT_LICENSE_VALIDATION = 'cpt_table_engine_license_validation';
    private const VALIDATION_INTERVAL = 12 * HOUR_IN_SECONDS; // 12 hours

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        // Hook into admin_init to check license validation.
        add_action('admin_init', [$this, 'maybe_validate_license']);

        // Register AJAX actions.
        add_action('wp_ajax_slk_manage_license', [$this, 'handle_ajax_request']);

        // Enqueue scripts.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Log debug messages when SLK_DEBUG is enabled.
     *
     * @param string $message Log message.
     * @param mixed  $data    Optional data to log.
     * @return void
     */
    private static function log(string $message, $data = null): void
    {
        if (!defined('SLK_DEBUG') || !SLK_DEBUG) {
            return;
        }

        $log_message = '[SLK License Manager] ' . $message;

        if ($data !== null) {
            $log_message .= ' | Data: ' . json_encode($data);
        }

        error_log($log_message);
    }

    /**
     * Get singleton instance.
     *
     * @return License_Manager
     */
    public static function instance(): License_Manager
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activate a license.
     *
     * @param string $license_key The license key to activate.
     * @return array Response array with success status and message.
     */
    public function activate_license(string $license_key): array
    {
        self::log('Activating license', ['key_length' => strlen($license_key)]);

        // Call API.
        $response = License_Helper::activate_license($license_key);

        self::log('Activation API response', $response);

        // Check if API request succeeded AND activation was successful.
        if (!$response['success']) {
            self::log('Activation failed: API request failed', $response);
            return $response;
        }

        // Verify the response contains valid data.
        if (!isset($response['data']) || empty($response['data'])) {
            self::log('Activation failed: Empty or invalid response data', $response);
            return [
                'success' => false,
                'data'    => null,
                'message' => __('Invalid API response: No data returned.', 'slk-cpt-table-engine'),
            ];
        }

        // Check if the API response indicates success (some APIs return success:true in the response body).
        if (isset($response['data']['success']) && $response['data']['success'] === false) {
            $error_msg = isset($response['data']['message'])
                ? $response['data']['message']
                : __('License activation was rejected by the API.', 'slk-cpt-table-engine');

            self::log('Activation rejected by API', $response['data']);
            return [
                'success' => false,
                'data'    => $response['data'],
                'message' => $error_msg,
            ];
        }

        // Check for errors in the nested data structure (License Manager for WooCommerce format).
        if (isset($response['data']['data']['errors']) && !empty($response['data']['data']['errors'])) {
            // Extract error message from the errors array.
            $errors = $response['data']['data']['errors'];
            $error_msg = __('License activation failed.', 'slk-cpt-table-engine');

            // Get the first error message.
            foreach ($errors as $error_key => $error_messages) {
                if (is_array($error_messages) && !empty($error_messages)) {
                    $error_msg = is_array($error_messages[0]) ? json_encode($error_messages[0]) : $error_messages[0];
                    break;
                }
            }

            self::log('Activation failed: Errors found in response', ['errors' => $errors, 'message' => $error_msg]);
            return [
                'success' => false,
                'data'    => $response['data'],
                'message' => $error_msg,
            ];
        }

        // Store license data.
        update_option(self::OPTION_LICENSE_KEY, sanitize_text_field($license_key));
        update_option(self::OPTION_LICENSE_STATUS, 'active');

        self::log('License status set to active');

        // Store activation token if provided - check multiple possible field names.
        $token = null;
        if (isset($response['data']['activationToken'])) {
            $token = $response['data']['activationToken'];
        } elseif (isset($response['data']['activation_token'])) {
            $token = $response['data']['activation_token'];
        } elseif (isset($response['data']['token'])) {
            $token = $response['data']['token'];
        }

        // If no token in activation response, try to get it from license details.
        if (!$token) {
            self::log('No token in activation response, fetching license details');
            $details = License_Helper::get_license_details($license_key);

            if ($details['success']) {
                // Check for nested data structure.
                $activations = null;
                if (isset($details['data']['data']['activationData'])) {
                    $activations = $details['data']['data']['activationData'];
                } elseif (isset($details['data']['activationData'])) {
                    $activations = $details['data']['activationData'];
                }

                if ($activations) {
                    // Get the most recent activation (last item in array or first non-deactivated).
                    foreach ($activations as $activation) {
                        if (isset($activation['token']) && empty($activation['deactivated_at'])) {
                            $token = $activation['token'];
                            self::log('Found active token in license details', ['token_length' => strlen($token)]);
                            break;
                        }
                    }
                }
            }
        }

        if ($token) {
            update_option(self::OPTION_ACTIVATION_TOKEN, sanitize_text_field($token));
            self::log('Activation token stored', ['token_length' => strlen($token)]);
        } else {
            self::log('Warning: No activation token found in API response or license details', $response['data']);
        }

        // Update license counts.
        // If we already fetched details for the token, use that.
        if (isset($details) && $details['success'] && isset($details['data'])) {
            $this->update_license_counts($details['data']);
        } else {
            // Otherwise, fetch details now to get the counts.
            $details = License_Helper::get_license_details($license_key);
            if ($details['success'] && isset($details['data'])) {
                $this->update_license_counts($details['data']);
            }
        }

        // Set up automatic validation.
        $this->schedule_validation();

        return [
            'success' => true,
            'message' => __('License activated successfully.', 'slk-cpt-table-engine'),
        ];
    }

    /**
     * Get license counts.
     *
     * @return array|null Array with 'activated' and 'limit' keys, or null if not set.
     */
    public function get_license_counts(): ?array
    {
        return get_option(self::OPTION_LICENSE_COUNTS, null);
    }

    /**
     * Update license counts from API data.
     *
     * @param array $data API response data.
     * @return void
     */
    private function update_license_counts(array $data): void
    {
        // Handle nested data structure (data.data).
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (isset($data['timesActivated']) && isset($data['timesActivatedMax'])) {
            $counts = [
                'activated' => (int) $data['timesActivated'],
                'limit'     => (int) $data['timesActivatedMax'],
            ];
            update_option(self::OPTION_LICENSE_COUNTS, $counts);
            self::log('License counts updated', $counts);
        }
    }

    /**
     * Deactivate a license.
     *
     * @param string $license_key      The license key.
     * @param string $activation_token The activation token.
     * @return array Response array with success status and message.
     */
    public function deactivate_license(string $license_key, string $activation_token): array
    {
        self::log('Deactivating license', [
            'license_key_length' => strlen($license_key),
            'token_length'       => strlen($activation_token)
        ]);

        // Call API.
        $response = License_Helper::deactivate_license($license_key, $activation_token);

        self::log('Deactivation API response', $response);

        if ($response['success']) {
            // Check for API errors in nested data (LMFWC format).
            // API might return success:true but contain errors in data.
            if (isset($response['data']['data']['errors']) && !empty($response['data']['data']['errors'])) {
                $errors = $response['data']['data']['errors'];
                $error_msg = __('Deactivation failed.', 'slk-cpt-table-engine');

                foreach ($errors as $error_key => $error_messages) {
                    if (is_array($error_messages) && !empty($error_messages)) {
                        $error_msg = is_array($error_messages[0]) ? json_encode($error_messages[0]) : $error_messages[0];
                        break;
                    }
                }

                self::log('Deactivation failed: Errors found in response', ['errors' => $errors, 'message' => $error_msg]);
                return [
                    'success' => false,
                    'data'    => $response['data'],
                    'message' => $error_msg,
                ];
            }

            // Clear license data.
            License_Helper::delete_license_data();

            self::log('License deactivated, token deleted, transient cleared');
        } else {
            self::log('Deactivation failed', $response);
        }

        return $response;
    }

    /**
     * Validate a license.
     *
     * @param string $license_key The license key to validate.
     * @param bool $silent If true, preserve active status on API failure (for background checks).
     * @return array Response array with success status and message.
     */
    public function validate_license(string $license_key, bool $silent = false): array
    {
        self::log('Validating license', ['key_length' => strlen($license_key), 'silent_mode' => $silent]);

        // Call API.
        $response = License_Helper::validate_license($license_key);

        self::log('Validation API response', $response);

        if ($response['success']) {
            // Update status based on validation result.
            // API returns 'success' => true if valid.
            $is_valid = isset($response['data']['success']) && $response['data']['success'];
            update_option(self::OPTION_LICENSE_STATUS, $is_valid ? 'active' : 'invalid');

            self::log('License validation result', ['is_valid' => $is_valid, 'status_set' => $is_valid ? 'active' : 'invalid']);

            self::log('License validation result', ['is_valid' => $is_valid, 'status_set' => $is_valid ? 'active' : 'invalid']);

            // Update license counts.
            // If validation response doesn't have counts, fetch details.
            // Check both direct and nested locations.
            $has_counts = isset($response['data']['timesActivated']) || isset($response['data']['data']['timesActivated']);

            if (!$has_counts) {
                $details = License_Helper::get_license_details($license_key);
                if ($details['success'] && isset($details['data'])) {
                    $this->update_license_counts($details['data']);
                }
            } else {
                $this->update_license_counts($response['data']);
            }

            // Set transient for automatic validation (12 hours).
            $this->schedule_validation();
        } else {
            // If silent mode (background check) and API failed, keep current status.
            if ($silent) {
                // Reset transient to try again in 12 hours.
                $this->schedule_validation();
                self::log('Silent validation failed, keeping current status and resetting transient');
            } else {
                self::log('Validation failed (non-silent mode)', $response);
            }
            // Otherwise, let the admin page handle the error display.
        }

        return $response;
    }

    /**
     * Get stored license key.
     *
     * @return string License key or empty string.
     */
    public function get_license_key(): string
    {
        return (string) get_option(self::OPTION_LICENSE_KEY, '');
    }

    /**
     * Get stored activation token.
     *
     * @return string Activation token or empty string.
     */
    public function get_activation_token(): string
    {
        return (string) get_option(self::OPTION_ACTIVATION_TOKEN, '');
    }

    /**
     * Get current license status.
     *
     * @return string License status (active, inactive, invalid, or empty).
     */
    public function get_license_status(): string
    {
        return (string) get_option(self::OPTION_LICENSE_STATUS, '');
    }

    /**
     * Schedules the next license validation check.
     *
     * @return void
     */
    private function schedule_validation(): void
    {
        set_transient(self::TRANSIENT_LICENSE_VALIDATION, time(), self::VALIDATION_INTERVAL);
        self::log('Validation transient set for ' . self::VALIDATION_INTERVAL . ' seconds');
    }

    /**
     * Automatically validate license if transient has expired.
     * 
     * This method is hooked to admin_init and runs every 12 hours.
     * If the API fails to respond, the license remains active.
     *
     * @return void
     */
    public function maybe_validate_license(): void
    {
        // Check if transient exists.
        $last_validation = get_transient(self::TRANSIENT_LICENSE_VALIDATION);

        self::log('Auto-validation check', ['transient_exists' => ($last_validation !== false), 'last_validation' => $last_validation]);

        // If transient doesn't exist, validate the license.
        if (false === $last_validation) {
            $license_key = $this->get_license_key();
            $license_status = $this->get_license_status();

            self::log('Transient expired, checking license', ['has_key' => !empty($license_key), 'status' => $license_status]);

            // Only auto-validate if there's a license key and status is active.
            if (!empty($license_key) && $license_status === 'active') {
                self::log('Starting automatic background validation');
                // Validate in silent mode - preserves active status if API fails.
                $this->validate_license($license_key, true);
            } else {
                self::log('Skipping auto-validation', ['reason' => empty($license_key) ? 'no_key' : 'not_active']);
            }
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        // Only load on our settings page.
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'slk-cpt-table-engine') === false) {
            return;
        }

        wp_enqueue_script(
            'slk-license-manager',
            CPT_TABLE_ENGINE_URL . 'modules/license-manager/assets/js/license-manager.js',
            ['jquery'],
            CPT_TABLE_ENGINE_VERSION,
            true
        );

        wp_localize_script('slk-license-manager', 'slk_license_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('slk_license_nonce'),
            'status'   => $this->get_license_status(),
            'strings'  => [
                'enter_key'         => __('Please enter a license key.', 'slk-cpt-table-engine'),
                'confirm_deactivate' => __('Are you sure you want to deactivate this license?', 'slk-cpt-table-engine'),
                'network_error'     => __('Network error. Please try again.', 'slk-cpt-table-engine'),
                'active_desc'       => __('Your license is active. Click "Deactivate" to change or remove the license.', 'slk-cpt-table-engine'),
                'inactive_desc'     => __('Enter the license key you received after purchase.', 'slk-cpt-table-engine'),
            ],
        ]);
    }

    /**
     * Handle AJAX request for license management.
     *
     * @return void
     */
    public function handle_ajax_request(): void
    {
        // Verify nonce.
        if (!check_ajax_referer('slk_license_nonce', 'security', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'slk-cpt-table-engine')]);
        }

        // Check capabilities.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'slk-cpt-table-engine')]);
        }

        $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : '';

        if ($method === 'activate') {
            $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
            if (empty($license_key)) {
                wp_send_json_error(['message' => __('License key is required.', 'slk-cpt-table-engine')]);
            }

            $response = $this->activate_license($license_key);

            if ($response['success']) {
                // Return masked key for display
                $key_length = strlen($license_key);
                $masked_key = ($key_length > 8)
                    ? substr($license_key, 0, 4) . str_repeat('*', $key_length - 8) . substr($license_key, -4)
                    : str_repeat('*', $key_length);

                // Get counts
                $counts = $this->get_license_counts();
                $usage = $counts ? sprintf('%d / %d', $counts['activated'], $counts['limit']) : '';

                wp_send_json_success([
                    'message'    => __('License activated successfully!', 'slk-cpt-table-engine'),
                    'masked_key' => $masked_key,
                    'usage'      => $usage
                ]);
            } else {
                wp_send_json_error(['message' => $response['message']]);
            }
        } elseif ($method === 'deactivate') {
            $activation_token = $this->get_activation_token();

            // Fallback to license key if token missing (same logic as before)
            if (empty($activation_token)) {
                $activation_token = $this->get_license_key();
            }

            if (!$activation_token) {
                wp_send_json_error(['message' => __('No activation token found.', 'slk-cpt-table-engine')]);
            }

            $license_key = $this->get_license_key();
            if (!$license_key) {
                wp_send_json_error(['message' => __('No license key found.', 'slk-cpt-table-engine')]);
            }

            $response = $this->deactivate_license($license_key, $activation_token);

            if ($response['success']) {
                wp_send_json_success([
                    'message'     => __('License deactivated successfully!', 'slk-cpt-table-engine'),
                    'license_key' => $this->get_license_key() // Return full key so user can edit it
                ]);
            } else {
                wp_send_json_error(['message' => $response['message']]);
            }
        } elseif ($method === 'check_status') {
            $license_key = $this->get_license_key();

            if (empty($license_key)) {
                wp_send_json_error(['message' => __('No license key found.', 'slk-cpt-table-engine')]);
            }

            // Force validation (silent=true so we don't deactivate on network error, but we DO update on API result).
            $response = $this->validate_license($license_key, true);

            // Get fresh status and counts.
            $status = $this->get_license_status();
            $counts = $this->get_license_counts();
            $usage = $counts ? sprintf('%d / %d', $counts['activated'], $counts['limit']) : '';

            wp_send_json_success([
                'status' => $status,
                'usage'  => $usage,
                'message' => ($status === 'active')
                    ? __('License is active.', 'slk-cpt-table-engine')
                    : __('License is inactive.', 'slk-cpt-table-engine')
            ]);
        } else {
            wp_send_json_error(['message' => __('Invalid method.', 'slk-cpt-table-engine')]);
        }
    }

    /**
     * Render the license form.
     * 
     * This method is called from the settings page.
     *
     * @return void
     */
    public function render_license_form(): void
    {
        $admin_page = new License_Admin_Page();
        $admin_page->render();
    }

    /**
     * Prevent cloning.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
