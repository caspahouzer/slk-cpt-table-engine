<?php

/**
 * License Management Admin View Template.
 *
 * This template is loaded by License_Admin_Page::render().
 *
 * @package SLK\License_Checker
 * @var string $license_key       Current license key.
 * @var string $license_status    Current license status.
 * @var string $notice            Admin notice message.
 * @var string $notice_type       Admin notice type.
 */


// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="cpt-license-manager">
    <!-- Message Container for AJAX -->
    <div id="slk-license-message" style="display: none; margin-bottom: 15px;"></div>

    <?php if (!empty($this->notice)) : ?>
        <div class="notice notice-<?php echo esc_attr($this->notice_type); ?> is-dismissible">
            <p><?php echo esc_html($this->notice); ?></p>
        </div>
    <?php endif; ?>

    <div class="cpt-license-info" style="margin-bottom: 20px;">
        <p class="description">
            <?php
            /* translators: %s: Plugin name. */
            printf(esc_html__('Manage your %s license. Activate your license to receive updates and support.', 'slk-cpt-table-engine'), '<strong>' . esc_html(SLK_PLUGIN_NAME) . '</strong>');
            ?>
        </p>
    </div>

    <form method="post" action="" class="cpt-license-form" onsubmit="return false;">
        <?php wp_nonce_field(\SLK\License_Checker\License_Admin_Page::NONCE_ACTION, \SLK\License_Checker\License_Admin_Page::NONCE_FIELD); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- License Key -->
                <tr>
                    <th scope="row">
                        <label for="license_key"><?php esc_html_e('License Key', 'slk-cpt-table-engine'); ?></label>
                    </th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <?php
                            // Mask the license key if active
                            $display_key = $license_key;
                            $is_active = ($license_status === 'active');

                            if ($is_active && !empty($license_key)) {
                                // Show first 4 and last 4 characters, mask the rest
                                $key_length = strlen($license_key);
                                if ($key_length > 8) {
                                    $display_key = substr($license_key, 0, 4) . str_repeat('*', $key_length - 8) . substr($license_key, -4);
                                } else {
                                    $display_key = str_repeat('*', $key_length);
                                }
                            }
                            ?>
                            <input
                                type="text"
                                id="license_key"
                                name="license_key"
                                class="regular-text code"
                                value="<?php echo esc_attr($display_key); ?>"
                                placeholder="<?php esc_attr_e('Enter your license key', 'slk-cpt-table-engine'); ?>"
                                style="flex: 1; <?php echo $is_active ? 'background-color: #f0f0f1;' : ''; ?>"
                                <?php echo $is_active ? 'readonly disabled' : ''; ?> />

                            <!-- Deactivate Button -->
                            <button
                                type="button"
                                id="slk-deactivate-btn"
                                class="button button-primary"
                                style="<?php echo $is_active ? '' : 'display: none;'; ?>">
                                <span class="dashicons dashicons-lock" style="vertical-align: middle; margin-top: 3px;"></span>
                                <?php esc_html_e('Deactivate', 'slk-cpt-table-engine'); ?>
                            </button>

                            <!-- Activate Button -->
                            <button
                                type="button"
                                id="slk-activate-btn"
                                class="button button-primary"
                                style="<?php echo $is_active ? 'display: none;' : ''; ?>">
                                <span class="dashicons dashicons-unlock" style="vertical-align: middle; margin-top: 3px;"></span>
                                <?php esc_html_e('Activate', 'slk-cpt-table-engine'); ?>
                            </button>

                            <span class="spinner slk-spinner" style="float: none; margin-top: 5px;"></span>
                        </div>
                        <p class="description">
                            <?php
                            if ($is_active) {
                                esc_html_e('Your license is active. Click "Deactivate" to change or remove the license.', 'slk-cpt-table-engine');
                            } else {
                                esc_html_e('Enter the license key you received after purchase.', 'slk-cpt-table-engine');
                            }
                            ?>
                        </p>
                    </td>
                </tr>

                <!-- License Status -->
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Status', 'slk-cpt-table-engine'); ?>
                    </th>
                    <td>
                        <?php
                        $status_icon = 'dashicons-minus';
                        $status_color = '#999';
                        $status_text = __('Not Activated', 'slk-cpt-table-engine');
                        $status_class = 'slk-status-inactive';

                        if ($license_status === 'active') {
                            $status_icon = 'dashicons-yes';
                            $status_color = 'green';
                            $status_text = 'active';
                            $status_class = 'slk-status-active';
                        } elseif ($license_status === 'inactive') {
                            $status_icon = 'dashicons-minus';
                            $status_color = '#999';
                            $status_text = 'inactive';
                            $status_class = 'slk-status-inactive';
                        } elseif ($license_status === 'invalid') {
                            $status_icon = 'dashicons-dismiss';
                            $status_color = '#dc3232';
                            $status_text = 'invalid';
                            $status_class = 'slk-status-invalid';
                        }
                        ?>
                        <span class="dashicons <?php echo esc_attr($status_icon); ?> slk-license-status-icon" style="color: <?php echo esc_attr($status_color); ?>;"></span>
                        <strong class="slk-license-status-text <?php echo esc_attr($status_class); ?>" style="text-transform: capitalize;"><?php echo esc_html($status_text); ?></strong>
                    </td>
                </tr>

                <!-- Activations -->
                <tr class="slk-activations-row" style="<?php echo ($license_status === 'active') ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <?php esc_html_e('Activations', 'slk-cpt-table-engine'); ?>
                    </th>
                    <td>
                        <?php
                        $usage_text = '';
                        if ($license_counts && isset($license_counts['activated'], $license_counts['limit'])) {
                            $usage_text = sprintf('%d / %d', $license_counts['activated'], $license_counts['limit']);
                        }
                        ?>
                        <span class="slk-license-usage"><?php echo esc_html($usage_text); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <hr />

    <div class="cpt-license-help" style="margin-top: 30px;">
        <h3><?php esc_html_e('Need Help?', 'slk-cpt-table-engine'); ?></h3>
        <ul>
            <li>
                <strong><?php esc_html_e('Where can I find my license key?', 'slk-cpt-table-engine'); ?></strong><br />
                <?php
                printf(
                    /* translators: %s: website link */
                    esc_html__('Your license key was sent to you via email after purchase. You can also find it in your account on our %s.', 'slk-cpt-table-engine'),
                    '<a href="https://slk-communications.de/account/" target="_blank" rel="noopener noreferrer">' . esc_html__('website', 'slk-cpt-table-engine') . '</a>'
                );
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('How many sites can I activate?', 'slk-cpt-table-engine'); ?></strong><br />
                <?php esc_html_e('This depends on your license type. Check your purchase confirmation email or contact support for details.', 'slk-cpt-table-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('What happens if I deactivate my license?', 'slk-cpt-table-engine'); ?></strong><br />
                <?php esc_html_e('Deactivating frees up an activation slot so you can use it on another site. The plugin will continue to work but you won\'t receive updates.', 'slk-cpt-table-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Support', 'slk-cpt-table-engine'); ?></strong><br />
                <?php
                printf(
                    /* translators: %s: support URL */
                    esc_html__('For support, please visit %s', 'slk-cpt-table-engine'),
                    '<a href="https://slk-communications.de/" target="_blank" rel="noopener noreferrer">https://slk-communications.de/</a>'
                );
                ?>
            </li>
        </ul>
    </div>
</div>

<style>
    .cpt-license-manager {
        max-width: 800px;
    }

    .cpt-license-form .button .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .cpt-license-help ul {
        list-style: disc;
        margin-left: 20px;
    }

    .cpt-license-help li {
        margin-bottom: 15px;
    }
</style>
