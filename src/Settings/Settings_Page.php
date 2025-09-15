<?php

namespace ADSD\Settings;

use ADSD\Logging\Logger;
use ADSD\Security\Capabilities_Service;
use ADSD\Admin\Rules_List_Table;

/**
 * Renders plugin settings page and registers settings/sections/fields.
 */
class Settings_Page
{
    private Options_Repository $options_repository;
    private Capabilities_Service $capabilities_service;
    private Logger $logger;

    private string $menu_slug = 'ads-destroyer';

    public function __construct(Options_Repository $options_repository, Capabilities_Service $capabilities_service, Logger $logger)
    {
        $this->options_repository = $options_repository;
        $this->capabilities_service = $capabilities_service;
        $this->logger = $logger;
    }

    /**
     * Register menu in WP admin.
     */
    public function register_menu(): void
    {
        add_options_page(
                __('AdsDestroyer', 'ads-destroyer'),
                __('AdsDestroyer', 'ads-destroyer'),
                'manage_options',
                $this->menu_slug,
                [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin scripts and styles for settings page.
     */
    public function enqueue_admin_assets(): void
    {
        // Only load on our settings page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'settings_page_' . $this->menu_slug) {
            return;
        }

        // Enqueue admin settings CSS
        wp_enqueue_style(
            'ads-destroyer-admin-settings',
            plugin_dir_url(dirname(__DIR__)) . 'build/css/admin-settings-style.css',
            [],
            ADSD_PLUGIN_VERSION
        );

        // Enqueue admin settings JavaScript
        wp_enqueue_script(
            'ads-destroyer-admin-settings',
            plugin_dir_url(dirname(__DIR__)) . 'build/js/admin-settings.js',
            ['jquery'],
            ADSD_PLUGIN_VERSION,
            true
        );
        
        // Localize script with AJAX URL
        wp_localize_script('ads-destroyer-admin-settings', 'adsd_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adsd_update_rule_ajax'),
        ]);
    }

    /**
     * Render settings page.
     */
    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ads-destroyer'));
        }
        $opts = $this->options_repository->get_all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AdsDestroyer', 'ads-destroyer'); ?></h1>
            <?php
            $notice_code = isset($_GET['adsd_notice']) ? sanitize_key((string) $_GET['adsd_notice']) : '';
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if ($notice_code && $nonce && wp_verify_nonce($nonce, 'adsd_notice_' . $notice_code)) {
                $messages = [
                    'general_ok' => __('Settings saved.', 'ads-destroyer'),
                    'logging_ok' => __('Settings saved.', 'ads-destroyer'),
                    'reset_ok'   => __('All rules have been reset.', 'ads-destroyer'),
                    'delete_ok'  => __('Rule deleted.', 'ads-destroyer'),
                    'update_ok'  => __('Rule updated.', 'ads-destroyer'),
                    'toggle_ok'  => __('Rule status updated.', 'ads-destroyer'),
                ];
                $msg = $messages[$notice_code] ?? '';
                if ($msg) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
                }
            }
            ?>
            <h2><?php echo esc_html__('General', 'ads-destroyer'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                  style="margin: 8px 0; display:grid; grid-template-columns: 1fr; gap:12px; max-width:840px;">
                <?php wp_nonce_field('adsd_update_general'); ?>
                <input type="hidden" name="action" value="adsd_update_general"/>
                <label title="<?php echo esc_attr__('Show the Selector button in the admin bar for allowed roles.', 'ads-destroyer'); ?>">
                    <input type="checkbox" name="show_admin_bar_button"
                           value="1" <?php checked(empty($opts['ui']['show_admin_bar_button']) ? true : (bool)$opts['ui']['show_admin_bar_button']); ?> />
                    <?php echo esc_html__('Show “Selector” button in the admin bar', 'ads-destroyer'); ?>
                </label>
                <div>
                    <label for="adsd-roles-box" style="display:block; font-weight:600;"
                           title="<?php echo esc_attr__('Choose which user roles can hide blocks and manage rules.', 'ads-destroyer'); ?>"><?php echo esc_html__('Available roles', 'ads-destroyer'); ?></label>
                    <div id="adsd-roles-box"
                         style="border:1px solid #ccd0d4; height:150px; width:250px; overflow:auto; padding:8px; display:block; background:#fff;">
                        <?php
                        $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : [];
                        $allowed_roles = (array)($opts['access']['roles_allowed'] ?? ['administrator']);
                        foreach ($editable_roles as $role_key => $role_data) {
                            $checked = in_array($role_key, $allowed_roles, true);
                            $name = translate_user_role($role_data['name']);
                            echo '<label style="display:block; margin:2px 0;"><input type="checkbox" style="margin-right:6px;" name="roles_allowed[]" value="' . esc_attr($role_key) . '" ' . checked($checked, true, false) . ' />' . esc_html($name) . '</label>';
                        }
                        ?>
                    </div>
                    <p class="description"><?php echo esc_html__('Unchecked roles will not see the “Selector” button and cannot modify rules.', 'ads-destroyer'); ?></p>
                </div>
                <label title="<?php echo esc_attr__('Enable or disable writing plugin events to the log.', 'ads-destroyer'); ?>">
                    <input type="checkbox" name="logging_enabled"
                           value="1" <?php checked(!empty($opts['logging']['enabled'])); ?> />
                    <?php echo esc_html__('Enable logging', 'ads-destroyer'); ?>
                </label>
                <button type="submit" class="button button-primary"
                        style="width:auto; display:inline-block; justify-self:start;">
                    <?php echo esc_html__('Save general settings', 'ads-destroyer'); ?>
                </button>
            </form>

            <hr/>
            <h2><?php echo esc_html__('Rules', 'ads-destroyer'); ?></h2>
            <p><?php echo esc_html__('Manage XPath rules below. Use the admin bar target icon to add rules interactively.', 'ads-destroyer'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                  style="margin: 8px 0; display:flex; gap:16px; align-items:center;">
                <?php wp_nonce_field('adsd_reset_rules'); ?>
                <input type="hidden" name="action" value="adsd_reset_rules"/>
                <button type="submit" class="button button-secondary"
                        onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all rules?', 'ads-destroyer')); ?>');"
                        title="<?php echo esc_attr__('Delete all rules permanently. This cannot be undone.', 'ads-destroyer'); ?>">
                    <?php echo esc_html__('Reset all rules', 'ads-destroyer'); ?>
                </button>
            </form>


            <?php $this->render_rules_table(); ?>

            <div class="adsd-instructions"
                 style="margin-top:16px; width:100%; background:#fff; border:1px solid #e2e4e7; padding:16px; border-radius:4px; box-sizing:border-box;">
                <h3 style="margin-top:0;">&nbsp;<?php echo esc_html__('Usage instructions', 'ads-destroyer'); ?></h3>
                <ol style="padding-left:18px;">
                    <li><?php echo esc_html__('Make sure your role is checked in "Available roles" and the "Selector" button is enabled in the admin bar.', 'ads-destroyer'); ?></li>
                    <li><?php echo esc_html__('In the admin area, click the "Selector" button in the top toolbar.', 'ads-destroyer'); ?></li>
                    <li><?php echo esc_html__('Hover to highlight the block you want to hide, click it, then confirm creating a rule.', 'ads-destroyer'); ?></li>
                    <li><?php echo esc_html__('The rule will appear in the table below. Click "Edit" to modify XPath inline and save.', 'ads-destroyer'); ?></li>
                    <li><?php echo esc_html__('Use "Activate/Deactivate" to toggle a rule, "Delete" to remove it, and "Reset all rules" to clear all rules.', 'ads-destroyer'); ?></li>
                </ol>
                <p class="description" style="margin-bottom:0;">
                    &nbsp;<?php echo esc_html__('Tooltips are available when hovering over settings elements.', 'ads-destroyer'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render rules table using WP_List_Table
     */
    private function render_rules_table(): void
    {
        $rules_table = new Rules_List_Table($this->options_repository);
        $rules_table->display();
        
        // Add modal for editing rules
        $this->render_edit_modal();
    }

    /**
     * Render modal for editing rules
     */
    private function render_edit_modal(): void
    {
        ?>
        <div id="adsd-edit-modal" class="adsd-modal" style="display: none;">
            <div class="adsd-modal-content">
                <div class="adsd-modal-header">
                    <h2><?php echo esc_html__('Edit Rule', 'ads-destroyer'); ?></h2>
                    <span class="adsd-modal-close">&times;</span>
                </div>
                <div class="adsd-modal-body">
                    <form id="adsd-edit-form">
                        <input type="hidden" id="adsd-rule-id" name="rule_id" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="adsd-xpath"><?php echo esc_html__('XPath Rule', 'ads-destroyer'); ?></label>
                                </th>
                                <td>
                                    <textarea id="adsd-xpath" name="xpath" rows="6" cols="50" 
                                              class="large-text code" 
                                              placeholder="<?php echo esc_attr__('Enter XPath rule...', 'ads-destroyer'); ?>"></textarea>
                                    <p class="description">
                                        <?php echo esc_html__('XPath expression to select elements that should be hidden.', 'ads-destroyer'); ?>
                                    </p>
                    </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="adsd-description"><?php echo esc_html__('Description', 'ads-destroyer'); ?></label>
                                </th>
                                <td>
                                    <textarea id="adsd-description" name="description" rows="3" cols="50" 
                                              class="large-text" 
                                              placeholder="<?php echo esc_attr__('Enter rule description...', 'ads-destroyer'); ?>"></textarea>
                                    <p class="description">
                                        <?php echo esc_html__('Optional description of what this rule does.', 'ads-destroyer'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="adsd-expiration"><?php echo esc_html__('Expiration', 'ads-destroyer'); ?></label>
                                </th>
                                <td>
                                    <select id="adsd-expiration" name="expiration" class="regular-text">
                                        <option value="forever"><?php echo esc_html__('Forever', 'ads-destroyer'); ?></option>
                                        <option value="1hour"><?php echo esc_html__('1 Hour', 'ads-destroyer'); ?></option>
                                        <option value="1day"><?php echo esc_html__('1 Day', 'ads-destroyer'); ?></option>
                                        <option value="1week"><?php echo esc_html__('1 Week', 'ads-destroyer'); ?></option>
                                        <option value="1month"><?php echo esc_html__('1 Month', 'ads-destroyer'); ?></option>
                                        <option value="3months"><?php echo esc_html__('3 Months', 'ads-destroyer'); ?></option>
                                        <option value="6months"><?php echo esc_html__('6 Months', 'ads-destroyer'); ?></option>
                                        <option value="1year"><?php echo esc_html__('1 Year', 'ads-destroyer'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php echo esc_html__('How long should this rule be active?', 'ads-destroyer'); ?>
                                    </p>
                    </td>
                </tr>
        </table>
                    </form>
                </div>
                <div class="adsd-modal-footer">
                    <button type="button" id="adsd-save-rule" class="button button-primary">
                        <?php echo esc_html__('Save', 'ads-destroyer'); ?>
                    </button>
                    <button type="button" id="adsd-cancel-edit" class="button">
                        <?php echo esc_html__('Cancel', 'ads-destroyer'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle reset all rules (admin-post)
     */
    public function handle_reset_rules(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_reset_rules');
        $this->options_repository->clear_rules();
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'reset_ok', '_wpnonce' => wp_create_nonce('adsd_notice_reset_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle delete single rule (admin-post)
     */
    public function handle_delete_rule(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_delete_rule');
        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';
        if ($rule_id) {
            $this->options_repository->delete_rule($rule_id);
        }
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'delete_ok', '_wpnonce' => wp_create_nonce('adsd_notice_delete_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle update rule (admin-post)
     */
    public function handle_update_rule(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_update_rule');
        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash((string)$_POST['label'])) : '';
        $xpath = isset($_POST['xpath']) ? sanitize_textarea_field(wp_unslash((string)$_POST['xpath'])) : '';
        $expires = isset($_POST['expires_at']) ? (int)$_POST['expires_at'] : 0;
        if ($rule_id) {
            $this->options_repository->update_rule($rule_id, [
                    'label' => $label,
                    'xpath' => $xpath,
                    'expires_at' => $expires,
            ]);
        }
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'update_ok', '_wpnonce' => wp_create_nonce('adsd_notice_update_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Toggle rule active flag (admin-post)
     */
    public function handle_toggle_rule_active(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_toggle_rule_active');
        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';
        if ($rule_id) {
            $rule = $this->options_repository->get_rule($rule_id);
            if (is_array($rule)) {
                $current = !empty($rule['active']);
                $this->options_repository->update_rule($rule_id, ['active' => !$current]);
            }
        }
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'toggle_ok', '_wpnonce' => wp_create_nonce('adsd_notice_toggle_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle update logging (admin-post)
     */
    public function handle_update_logging(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_update_logging');
        $enabled = isset($_POST['logging_enabled']) ? 1 : 0;
        // Sync both repository option and global logger option for consistency
        $opts = $this->options_repository->get_all();
        $opts['logging']['enabled'] = (bool)$enabled;
        $this->options_repository->save_all($opts);
        update_option('adsd_logging_enabled', (bool)$enabled);
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'logging_ok', '_wpnonce' => wp_create_nonce('adsd_notice_logging_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle update general (roles + admin bar button)
     */
    public function handle_update_general(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'ads-destroyer'));
        }
        check_admin_referer('adsd_update_general');
        $show_button = isset($_POST['show_admin_bar_button']) ? 1 : 0;
        $roles = isset($_POST['roles_allowed']) && is_array($_POST['roles_allowed']) ? array_map('sanitize_key', (array)$_POST['roles_allowed']) : [];
        $logging_enabled = isset($_POST['logging_enabled']) ? 1 : 0;
        if (empty($roles)) {
            // Always keep at least administrator to avoid lockout
            $roles = ['administrator'];
        }
        $opts = $this->options_repository->get_all();
        $opts['ui']['show_admin_bar_button'] = (bool)$show_button;
        $opts['access']['roles_allowed'] = array_values(array_unique($roles));
        $opts['logging']['enabled'] = (bool)$logging_enabled;
        $this->options_repository->save_all($opts);
        // Keep global logger option in sync as well.
        update_option('adsd_logging_enabled', (bool)$logging_enabled);
        $redirect = add_query_arg(['page' => $this->menu_slug, 'adsd_notice' => 'general_ok', '_wpnonce' => wp_create_nonce('adsd_notice_general_ok')], admin_url('options-general.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * AJAX handler for updating rule
     */
    public function handle_ajax_update_rule(): void
    {
        check_ajax_referer('adsd_update_rule_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ads-destroyer')]);
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';
        $xpath = isset($_POST['xpath']) ? sanitize_textarea_field(wp_unslash((string)$_POST['xpath'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string)$_POST['description'])) : '';
        $expiration = isset($_POST['expiration']) ? sanitize_key((string)$_POST['expiration']) : 'forever';

        if (!$rule_id || !$xpath) {
            wp_send_json_error(['message' => __('Invalid data', 'ads-destroyer')]);
        }

        // Calculate expiration timestamp
        $expires_at = $this->calculate_expiration_timestamp($expiration);

        $updated_rule = $this->options_repository->update_rule($rule_id, [
            'xpath' => $xpath,
            'description' => $description,
            'expires_at' => $expires_at,
        ]);

        if ($updated_rule) {
            wp_send_json_success([
                'message' => __('Rule saved successfully', 'ads-destroyer'),
                'rule' => $updated_rule
            ]);
        } else {
            wp_send_json_error(['message' => __('Error saving rule', 'ads-destroyer')]);
        }
    }

    /**
     * AJAX handler for toggling rule status
     */
    public function handle_ajax_toggle_rule(): void
    {
        check_ajax_referer('adsd_update_rule_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ads-destroyer')]);
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';
        $action = isset($_POST['toggle_action']) ? sanitize_key((string)$_POST['toggle_action']) : '';

        if (!$rule_id) {
            wp_send_json_error(['message' => __('Invalid data', 'ads-destroyer')]);
        }

        $rule = $this->options_repository->get_rule($rule_id);
        if (!$rule) {
            wp_send_json_error(['message' => __('Rule not found', 'ads-destroyer')]);
        }

        $new_status = ($action === 'activate');
        $updated_rule = $this->options_repository->update_rule($rule_id, ['active' => $new_status]);

        if ($updated_rule) {
            $status_text = $new_status ? __('activated', 'ads-destroyer') : __('deactivated', 'ads-destroyer');
            wp_send_json_success([
                'message' => sprintf(__('Rule %s', 'ads-destroyer'), $status_text),
                'rule' => $updated_rule
            ]);
        } else {
            wp_send_json_error(['message' => __('Error changing rule status', 'ads-destroyer')]);
        }
    }

    /**
     * AJAX handler for deleting rule
     */
    public function handle_ajax_delete_rule(): void
    {
        check_ajax_referer('adsd_update_rule_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ads-destroyer')]);
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';

        if (!$rule_id) {
            wp_send_json_error(['message' => __('Invalid data', 'ads-destroyer')]);
        }

        $deleted = $this->options_repository->delete_rule($rule_id);

        if ($deleted) {
            wp_send_json_success(['message' => __('Rule deleted', 'ads-destroyer')]);
        } else {
            wp_send_json_error(['message' => __('Error deleting rule', 'ads-destroyer')]);
        }
    }

    /**
     * Get single rule for AJAX
     */
    public function handle_ajax_get_rule(): void
    {
        check_ajax_referer('adsd_update_rule_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ads-destroyer')]);
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_key((string)$_POST['rule_id']) : '';

        if (!$rule_id) {
            wp_send_json_error(['message' => __('Invalid data', 'ads-destroyer')]);
        }

        $rule = $this->options_repository->get_rule($rule_id);

        if ($rule) {
            wp_send_json_success(['rule' => $rule]);
        } else {
            wp_send_json_error(['message' => __('Rule not found', 'ads-destroyer')]);
        }
    }

    /**
     * Calculate expiration timestamp based on period
     */
    private function calculate_expiration_timestamp(string $period): int
    {
        $now = current_time('timestamp', true);
        
        switch ($period) {
            case '1hour':
                return $now + HOUR_IN_SECONDS;
            case '1day':
                return $now + DAY_IN_SECONDS;
            case '1week':
                return $now + WEEK_IN_SECONDS;
            case '1month':
                return $now + (30 * DAY_IN_SECONDS);
            case '3months':
                return $now + (90 * DAY_IN_SECONDS);
            case '6months':
                return $now + (180 * DAY_IN_SECONDS);
            case '1year':
                return $now + YEAR_IN_SECONDS;
            case 'forever':
            default:
                return 0;
        }
    }
}

