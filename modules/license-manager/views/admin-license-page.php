<?php

/**
 * License Management Admin View Template.
 *
 * This template is loaded by License_Admin_Page::render().
 *
 * @package SLK\License_Manager
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
    <?php if (!empty($this->notice)) : ?>
        <div class="notice notice-<?php echo esc_attr($this->notice_type); ?> is-dismissible">
            <p><?php echo esc_html($this->notice); ?></p>
        </div>
    <?php endif; ?>

    <div class="cpt-license-info" style="margin-bottom: 20px;">
        <p class="description">
            <?php printf(esc_html__('Manage your %s license. Activate your license to receive updates and support.', 'cpt-table-engine'), '<strong>' . SLK_PLUGIN_NAME . '</strong>'); ?>
        </p>
    </div>

    <form method="post" action="" class="cpt-license-form">
        <?php wp_nonce_field(\SLK\License_Manager\License_Admin_Page::NONCE_ACTION, \SLK\License_Manager\License_Admin_Page::NONCE_FIELD); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- License Key -->
                <tr>
                    <th scope="row">
                        <label for="license_key"><?php esc_html_e('License Key', 'cpt-table-engine'); ?></label>
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
                                placeholder="<?php esc_attr_e('Enter your license key', 'cpt-table-engine'); ?>"
                                style="flex: 1; <?php echo $is_active ? 'background-color: #f0f0f1;' : ''; ?>"
                                <?php echo $is_active ? 'readonly disabled' : ''; ?> />

                            <?php if ($is_active) : ?>
                                <!-- Deactivate Button (shown when license is active) -->
                                <button
                                    type="submit"
                                    name="license_action"
                                    value="deactivate"
                                    class="button button-primary">
                                    <span class="dashicons dashicons-lock" style="vertical-align: middle; margin-top: 3px;"></span>
                                    <?php esc_html_e('Deactivate', 'cpt-table-engine'); ?>
                                </button>
                            <?php else : ?>
                                <!-- Activate Button (shown when license is not active) -->
                                <button
                                    type="submit"
                                    name="license_action"
                                    value="activate"
                                    class="button button-primary">
                                    <span class="dashicons dashicons-unlock" style="vertical-align: middle; margin-top: 3px;"></span>
                                    <?php esc_html_e('Activate', 'cpt-table-engine'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <p class="description">
                            <?php
                            if ($is_active) {
                                esc_html_e('Your license is active. Click "Deactivate" to change or remove the license.', 'cpt-table-engine');
                            } else {
                                esc_html_e('Enter the license key you received after purchase.', 'cpt-table-engine');
                            }
                            ?>
                        </p>
                    </td>
                </tr>

                <!-- License Status -->
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Status', 'cpt-table-engine'); ?>
                    </th>
                    <td>
                        <?php if (empty($license_status)) : ?>
                            <span class="dashicons dashicons-minus" style="color: #999;"></span>
                            <strong><?php esc_html_e('Not Activated', 'cpt-table-engine'); ?></strong>
                        <?php elseif ($license_status === 'active') : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <strong style="color: #46b450;"><?php esc_html_e('Active', 'cpt-table-engine'); ?></strong>
                        <?php elseif ($license_status === 'inactive') : ?>
                            <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                            <strong style="color: #f0b849;"><?php esc_html_e('Inactive', 'cpt-table-engine'); ?></strong>
                        <?php else : ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                            <strong style="color: #dc3232;"><?php esc_html_e('Invalid', 'cpt-table-engine'); ?></strong>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <hr />

    <div class="cpt-license-help" style="margin-top: 30px;">
        <h3><?php esc_html_e('Need Help?', 'cpt-table-engine'); ?></h3>
        <ul>
            <li>
                <strong><?php esc_html_e('Where can I find my license key?', 'cpt-table-engine'); ?></strong><br />
                <?php
                printf(
                    /* translators: %s: website link */
                    esc_html__('Your license key was sent to you via email after purchase. You can also find it in your account on our %s.', 'cpt-table-engine'),
                    '<a href="https://slk-communications.de/account/" target="_blank" rel="noopener noreferrer">' . esc_html__('website', 'cpt-table-engine') . '</a>'
                );
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('How many sites can I activate?', 'cpt-table-engine'); ?></strong><br />
                <?php esc_html_e('This depends on your license type. Check your purchase confirmation email or contact support for details.', 'cpt-table-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('What happens if I deactivate my license?', 'cpt-table-engine'); ?></strong><br />
                <?php esc_html_e('Deactivating frees up an activation slot so you can use it on another site. The plugin will continue to work but you won\'t receive updates.', 'cpt-table-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Support', 'cpt-table-engine'); ?></strong><br />
                <?php
                printf(
                    /* translators: %s: support URL */
                    esc_html__('For support, please visit %s', 'cpt-table-engine'),
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
