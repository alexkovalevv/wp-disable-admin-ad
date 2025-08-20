<?php

namespace AIDAD\Settings;

use AIDAD\Logging\Logger;
use AIDAD\Security\Capabilities_Service;

/**
 * Renders plugin settings page and registers settings/sections/fields.
 */
class Settings_Page {
    private Options_Repository $options_repository;
    private Capabilities_Service $capabilities_service;
    private Logger $logger;

    private string $menu_slug = 'disable-admin-ad';

    public function __construct( Options_Repository $options_repository, Capabilities_Service $capabilities_service, Logger $logger ) {
        $this->options_repository   = $options_repository;
        $this->capabilities_service = $capabilities_service;
        $this->logger               = $logger;
    }

    /**
     * Register menu in WP admin.
     */
    public function register_menu(): void {
        add_options_page(
            __( 'Disable Admin Ad', 'disable-admin-ad' ),
            __( 'Disable Admin Ad', 'disable-admin-ad' ),
            'manage_options',
            $this->menu_slug,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render settings page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'disable-admin-ad' ) );
        }
        $opts = $this->options_repository->get_all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Disable Admin Ad', 'disable-admin-ad' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'aidad_settings' );
                do_settings_sections( $this->menu_slug );
                submit_button();
                ?>
            </form>
            <hr />
            <h2><?php echo esc_html__( 'Rules', 'disable-admin-ad' ); ?></h2>
            <p><?php echo esc_html__( 'Manage XPath rules below. Use the admin bar target icon to add rules interactively.', 'disable-admin-ad' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 8px 0; display:flex; gap:16px; align-items:center;">
                <?php wp_nonce_field( 'aidad_reset_rules' ); ?>
                <input type="hidden" name="action" value="aidad_reset_rules" />
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all rules?', 'disable-admin-ad' ) ); ?>');">
                    <?php echo esc_html__( 'Reset all rules', 'disable-admin-ad' ); ?>
                </button>
            </form>

            <hr />
            <h2><?php echo esc_html__( 'Logging', 'disable-admin-ad' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 8px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <?php wp_nonce_field( 'aidad_update_logging' ); ?>
                <input type="hidden" name="action" value="aidad_update_logging" />
                <label>
                    <input type="checkbox" name="logging_enabled" value="1" <?php checked( ! empty( $opts['logging']['enabled'] ) ); ?> />
                    <?php echo esc_html__( 'Enable logging', 'disable-admin-ad' ); ?>
                </label>
                <label>
                    <?php echo esc_html__( 'Keep last N entries', 'disable-admin-ad' ); ?>
                    <input type="number" name="logging_max" min="10" step="10" value="<?php echo esc_attr( (string) ( $opts['logging']['max_entries'] ?? 500 ) ); ?>" style="width:100px;" />
                </label>
                <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save logging settings', 'disable-admin-ad' ); ?></button>
            </form>

            <?php $this->render_rules_table( $opts ); ?>

            <script>
            (function(){
                document.addEventListener('click', function(e){
                    const btn = e.target.closest('[data-aidad-action]');
                    if(!btn) return;
                    const action = btn.getAttribute('data-aidad-action');
                    const row = btn.closest('tr');
                    if(!row) return;
                    if(action === 'edit'){
                        row.classList.add('aidad-editing');
                        const ta = row.querySelector('textarea[name="xpath"]');
                        if(ta){ ta.focus(); }
                        e.preventDefault();
                    } else if(action === 'cancel'){
                        row.classList.remove('aidad-editing');
                        e.preventDefault();
                    }
                });
            })();
            </script>

            <p style="margin-top:12px; max-width: 820px;">
                <?php echo esc_html__( 'Use the “Selector” in the admin bar to highlight a block, click it, then choose a hide duration. The block will be hidden or removed (depending on settings). You can edit XPath, labels, and expiry below, delete individual rules, or reset all rules if needed.', 'disable-admin-ad' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render rules table with basic controls (non-AJAX for simplicity; REST exists for JS UI).
     *
     * @param array<string,mixed> $opts
     */
    private function render_rules_table( array $opts ): void {
        $rules = (array) ( $opts['rules'] ?? [] );
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th style="width:70px"><?php echo esc_html__( 'Active', 'disable-admin-ad' ); ?></th>
                    <th><?php echo esc_html__( 'XPath', 'disable-admin-ad' ); ?></th>
                    <th style="width:180px"><?php echo esc_html__( 'Period', 'disable-admin-ad' ); ?></th>
                    <th style="width:160px"><?php echo esc_html__( 'Author', 'disable-admin-ad' ); ?></th>
                    <th style="width:180px"><?php echo esc_html__( 'Created', 'disable-admin-ad' ); ?></th>
                    <th style="width:260px"><?php echo esc_html__( 'Actions', 'disable-admin-ad' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rules as $rule ) :
                $expires_at = (int) ( $rule['expires_at'] ?? 0 );
                $period_label = $expires_at > 0
                    ? sprintf( /* translators: %s: human time diff */ esc_html__( 'Until %s', 'disable-admin-ad' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at ) )
                    : esc_html__( 'Forever', 'disable-admin-ad' );
                ?>
                <tr class="aidad-row" data-rule-id="<?php echo esc_attr( (string) $rule['id'] ); ?>">
                    <td><?php echo ! empty( $rule['active'] ) ? '✓' : '—'; ?></td>
                    <td>
                        <div class="aidad-xpath-view" style="font-family: Menlo, Monaco, Consolas, 'Courier New', monospace; font-size: 12px; line-height:1.4; word-break: break-all;">
                            <code style="font-size:12px; white-space: pre-wrap; display:block;">
                                <?php echo esc_html( (string) $rule['xpath'] ); ?>
                            </code>
                        </div>
                        <form class="aidad-xpath-edit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none; margin:0;">
                            <?php wp_nonce_field( 'aidad_update_rule' ); ?>
                            <input type="hidden" name="action" value="aidad_update_rule" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule['id'] ); ?>" />
                            <textarea name="xpath" rows="4" style="width:100%; font-family: Menlo, Monaco, Consolas, 'Courier New', monospace; font-size:12px;"><?php echo esc_textarea( (string) $rule['xpath'] ); ?></textarea>
                            <input type="hidden" name="expires_at" value="<?php echo esc_attr( (string) $expires_at ); ?>" />
                            <div style="margin-top:6px;">
                                <button type="submit" class="button button-small" data-aidad-action="save"><?php esc_html_e( 'Save', 'disable-admin-ad' ); ?></button>
                                <a href="#" class="button button-small" data-aidad-action="cancel"><?php esc_html_e( 'Cancel', 'disable-admin-ad' ); ?></a>
                            </div>
                        </form>
                    </td>
                    <td><?php echo $period_label; ?></td>
                    <td><?php echo esc_html( ( $rule['author'] ? get_the_author_meta( 'display_name', (int) $rule['author'] ) : '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $rule['created_at'] ?? '' ) ); ?></td>
                    <td>
                        <a href="#" class="button button-small" data-aidad-action="edit"><?php esc_html_e( 'Edit', 'disable-admin-ad' ); ?></a>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
                            <?php wp_nonce_field( 'aidad_toggle_rule_active' ); ?>
                            <input type="hidden" name="action" value="aidad_toggle_rule_active" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule['id'] ); ?>" />
                            <button type="submit" class="button button-small"><?php echo ! empty( $rule['active'] ) ? esc_html__( 'Deactivate', 'disable-admin-ad' ) : esc_html__( 'Activate', 'disable-admin-ad' ); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
                            <?php wp_nonce_field( 'aidad_delete_rule' ); ?>
                            <input type="hidden" name="action" value="aidad_delete_rule" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule['id'] ); ?>" />
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'disable-admin-ad' ) ); ?>');"><?php echo esc_html__( 'Delete', 'disable-admin-ad' ); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <style>
            .aidad-row.aidad-editing .aidad-xpath-view{ display:none; }
            .aidad-row.aidad-editing .aidad-xpath-edit{ display:block; }
        </style>
        <?php
    }
    /**
     * Handle reset all rules (admin-post)
     */
    public function handle_reset_rules(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'disable-admin-ad' ) );
        }
        check_admin_referer( 'aidad_reset_rules' );
        $this->options_repository->clear_rules();
        $redirect = add_query_arg( [ 'page' => $this->menu_slug, 'aidad_notice' => 'reset_ok' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle delete single rule (admin-post)
     */
    public function handle_delete_rule(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'disable-admin-ad' ) );
        }
        check_admin_referer( 'aidad_delete_rule' );
        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_key( (string) $_POST['rule_id'] ) : '';
        if ( $rule_id ) {
            $this->options_repository->delete_rule( $rule_id );
        }
        $redirect = add_query_arg( [ 'page' => $this->menu_slug, 'aidad_notice' => 'delete_ok' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }
    /**
     * Handle update rule (admin-post)
     */
    public function handle_update_rule(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'disable-admin-ad' ) );
        }
        check_admin_referer( 'aidad_update_rule' );
        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_key( (string) $_POST['rule_id'] ) : '';
        $label   = isset( $_POST['label'] ) ? sanitize_text_field( (string) $_POST['label'] ) : '';
        $xpath   = isset( $_POST['xpath'] ) ? wp_kses_post( (string) $_POST['xpath'] ) : '';
        $expires = isset( $_POST['expires_at'] ) ? (int) $_POST['expires_at'] : 0;
        if ( $rule_id ) {
            $this->options_repository->update_rule( $rule_id, [
                'label'      => $label,
                'xpath'      => $xpath,
                'expires_at' => $expires,
            ] );
        }
        $redirect = add_query_arg( [ 'page' => $this->menu_slug, 'aidad_notice' => 'update_ok' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Toggle rule active flag (admin-post)
     */
    public function handle_toggle_rule_active(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'disable-admin-ad' ) );
        }
        check_admin_referer( 'aidad_toggle_rule_active' );
        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_key( (string) $_POST['rule_id'] ) : '';
        if ( $rule_id ) {
            $rule = $this->options_repository->get_rule( $rule_id );
            if ( is_array( $rule ) ) {
                $current = ! empty( $rule['active'] );
                $this->options_repository->update_rule( $rule_id, [ 'active' => ! $current ] );
            }
        }
        $redirect = add_query_arg( [ 'page' => $this->menu_slug, 'aidad_notice' => 'toggle_ok' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle update logging (admin-post)
     */
    public function handle_update_logging(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'disable-admin-ad' ) );
        }
        check_admin_referer( 'aidad_update_logging' );
        $enabled = isset( $_POST['logging_enabled'] ) ? 1 : 0;
        $max     = isset( $_POST['logging_max'] ) ? max( 10, (int) $_POST['logging_max'] ) : 500;
        $opts    = $this->options_repository->get_all();
        $opts['logging']['enabled']     = (bool) $enabled;
        $opts['logging']['max_entries'] = (int) $max;
        $this->options_repository->save_all( $opts );
        $redirect = add_query_arg( [ 'page' => $this->menu_slug, 'aidad_notice' => 'logging_ok' ], admin_url( 'options-general.php' ) );
        wp_safe_redirect( $redirect );
        exit;
    }
}

