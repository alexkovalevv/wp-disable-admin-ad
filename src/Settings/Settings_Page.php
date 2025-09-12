<?php

namespace ADSD\Settings;

use ADSD\Logging\Logger;
use ADSD\Security\Capabilities_Service;

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
            [],
            ADSD_PLUGIN_VERSION,
            true
        );
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


            <?php $this->render_rules_table($opts); ?>

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
     * Render rules table with basic controls (non-AJAX for simplicity; REST exists for JS UI).
     *
     * @param array<string,mixed> $opts
     */
    private function render_rules_table(array $opts): void
    {
        $rules = (array)($opts['rules'] ?? []);
        ?>
        <table class="widefat adsd-rules-table">
            <thead>
            <tr>
                <th style="width:70px"><?php echo esc_html__('Active', 'ads-destroyer'); ?></th>
                <th><?php echo esc_html__('XPath', 'ads-destroyer'); ?></th>
                <th style="width:260px"><?php echo esc_html__('Info', 'ads-destroyer'); ?></th>
                <th style="width:260px"><?php echo esc_html__('Actions', 'ads-destroyer'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule) :
                $expires_at = (int)($rule['expires_at'] ?? 0);
                $period_label = $expires_at > 0
                        ? sprintf( /* translators: %s: until datetime */ esc_html__('Until %s', 'ads-destroyer'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_at))
                        : esc_html__('Forever', 'ads-destroyer');
                $author_name = $rule['author'] ? get_the_author_meta('display_name', (int)$rule['author']) : '';
                $created = (string)($rule['created_at'] ?? '');
                $info = trim(sprintf('%s | %s%s', $created, $period_label, $author_name ? ' | ' . $author_name : ''));
                ?>
                <tr class="adsd-row" data-rule-id="<?php echo esc_attr((string)$rule['id']); ?>">
                    <td><?php echo !empty($rule['active']) ? '✓' : '—'; ?></td>
                    <td>
                        <div class="adsd-xpath-view"
                             style="font-family: Menlo, Monaco, Consolas, 'Courier New', monospace; font-size: 12px; line-height:1.4; word-break: break-all;">
                            <code style="font-size:12px; white-space: pre-wrap; display:block;">
                                <?php echo esc_html((string)$rule['xpath']); ?>
                            </code>
                        </div>
                        <form class="adsd-xpath-edit" method="post"
                              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              style="display:none; margin:0;">
                            <?php wp_nonce_field('adsd_update_rule'); ?>
                            <input type="hidden" name="action" value="adsd_update_rule"/>
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr((string)$rule['id']); ?>"/>
                            <textarea name="xpath" rows="4"
                                      style="width:100%; font-family: Menlo, Monaco, Consolas, 'Courier New', monospace; font-size:12px;"><?php echo esc_textarea((string)$rule['xpath']); ?></textarea>
                            <input type="hidden" name="expires_at"
                                   value="<?php echo esc_attr((string)$expires_at); ?>"/>
                            <div style="margin-top:6px;">
                                <button type="submit" class="button button-small"
                                        data-adsd-action="save"><?php esc_html_e('Save', 'ads-destroyer'); ?></button>
                                <a href="#" class="button button-small"
                                   data-adsd-action="cancel"><?php esc_html_e('Cancel', 'ads-destroyer'); ?></a>
                            </div>
                        </form>
                    </td>
                    <td><span style="font-size:12px; color:#555;"><?php echo esc_html($info); ?></span></td>
                    <td>
                        <a href="#" class="button button-small"
                           data-adsd-action="edit"><?php esc_html_e('Edit', 'ads-destroyer'); ?></a>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              style="display:inline-block;margin-left:6px;">
                            <?php wp_nonce_field('adsd_toggle_rule_active'); ?>
                            <input type="hidden" name="action" value="adsd_toggle_rule_active"/>
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr((string)$rule['id']); ?>"/>
                            <button type="submit"
                                    class="button button-small"><?php echo !empty($rule['active']) ? esc_html__('Deactivate', 'ads-destroyer') : esc_html__('Activate', 'ads-destroyer'); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              style="display:inline-block;margin-left:6px;">
                            <?php wp_nonce_field('adsd_delete_rule'); ?>
                            <input type="hidden" name="action" value="adsd_delete_rule"/>
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr((string)$rule['id']); ?>"/>
                            <button type="submit" class="button button-small button-link-delete"
                                    onclick="return confirm('<?php echo esc_js(__('Delete this rule?', 'ads-destroyer')); ?>');"><?php echo esc_html__('Delete', 'ads-destroyer'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
        $xpath = isset($_POST['xpath']) ? wp_kses_post(wp_unslash((string)$_POST['xpath'])) : '';
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
}

