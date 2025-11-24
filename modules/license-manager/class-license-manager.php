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
final class License_Manager
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
    private const OPTION_LICENSE_KEY = 'cpt_table_engine_license_key';
    private const OPTION_ACTIVATION_TOKEN = 'cpt_table_engine_activation_token';
    private const OPTION_LICENSE_STATUS = 'cpt_table_engine_license_status';
    private const TRANSIENT_LICENSE_VALIDATION = 'cpt_table_engine_license_validation';
    private const VALIDATION_INTERVAL = 12 * HOUR_IN_SECONDS; // 12 hours

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        // Hook into admin_init to check license validation.
        add_action('admin_init', [$this, 'maybe_validate_license']);
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
            $log_message .= ' | Data: ' . print_r($data, true);
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
                'message' => __('Invalid API response: No data returned.', 'cpt-table-engine'),
            ];
        }

        // Check if the API response indicates success (some APIs return success:true in the response body).
        if (isset($response['data']['success']) && $response['data']['success'] === false) {
            $error_msg = isset($response['data']['message'])
                ? $response['data']['message']
                : __('License activation was rejected by the API.', 'cpt-table-engine');

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
            $error_msg = __('License activation failed.', 'cpt-table-engine');

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

            if ($details['success'] && isset($details['data']['activationData'])) {
                $activations = $details['data']['activationData'];

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

        if ($token) {
            update_option(self::OPTION_ACTIVATION_TOKEN, sanitize_text_field($token));
            self::log('Activation token stored', ['token_length' => strlen($token)]);
        } else {
            self::log('Warning: No activation token found in API response or license details', $response['data']);
        }

        // Set transient for automatic validation (12 hours).
        set_transient(self::TRANSIENT_LICENSE_VALIDATION, time(), self::VALIDATION_INTERVAL);
        self::log('Validation transient set for 12 hours');

        return $response;
    }

    /**
     * Deactivate a license.
     *
     * @param string $activation_token The activation token.
     * @return array Response array with success status and message.
     */
    public function deactivate_license(string $activation_token): array
    {
        self::log('Deactivating license', ['token_length' => strlen($activation_token)]);

        // Call API.
        $response = License_Helper::deactivate_license($activation_token);

        self::log('Deactivation API response', $response);

        if ($response['success']) {
            // Update status but keep license key.
            update_option(self::OPTION_LICENSE_STATUS, 'inactive');
            delete_option(self::OPTION_ACTIVATION_TOKEN);

            // Delete validation transient.
            delete_transient(self::TRANSIENT_LICENSE_VALIDATION);

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
            $is_valid = isset($response['data']['valid']) && $response['data']['valid'];
            update_option(self::OPTION_LICENSE_STATUS, $is_valid ? 'active' : 'invalid');

            self::log('License validation result', ['is_valid' => $is_valid, 'status_set' => $is_valid ? 'active' : 'invalid']);

            // Set transient for automatic validation (12 hours).
            set_transient(self::TRANSIENT_LICENSE_VALIDATION, time(), self::VALIDATION_INTERVAL);
        } else {
            // If silent mode (background check) and API failed, keep current status.
            if ($silent) {
                // Reset transient to try again in 12 hours.
                set_transient(self::TRANSIENT_LICENSE_VALIDATION, time(), self::VALIDATION_INTERVAL);
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
