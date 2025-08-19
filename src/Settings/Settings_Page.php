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

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 8px 0;">
                <?php wp_nonce_field( 'aidad_reset_rules' ); ?>
                <input type="hidden" name="action" value="aidad_reset_rules" />
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all rules?', 'disable-admin-ad' ) ); ?>');">
                    <?php echo esc_html__( 'Reset all rules', 'disable-admin-ad' ); ?>
                </button>
            </form>

            <?php $this->render_rules_table( $opts ); ?>
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
                    <th><?php echo esc_html__( 'Active', 'disable-admin-ad' ); ?></th>
                    <th><?php echo esc_html__( 'XPath', 'disable-admin-ad' ); ?></th>
                    <th><?php echo esc_html__( 'Label', 'disable-admin-ad' ); ?></th>
                    <th><?php echo esc_html__( 'Author', 'disable-admin-ad' ); ?></th>
                    <th><?php echo esc_html__( 'Created', 'disable-admin-ad' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rules as $rule ) : ?>
                <tr>
                    <td><?php echo ! empty( $rule['active'] ) ? '✓' : '—'; ?></td>
                    <td><code><?php echo esc_html( (string) $rule['xpath'] ); ?></code></td>
                    <td><?php echo esc_html( (string) ( $rule['label'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $rule['author'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $rule['created_at'] ?? '' ) ); ?></td>
                </tr>
                <tr>
                    <td colspan="5">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:4px 0;">
                            <?php wp_nonce_field( 'aidad_delete_rule' ); ?>
                            <input type="hidden" name="action" value="aidad_delete_rule" />
                            <input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule['id'] ); ?>" />
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'disable-admin-ad' ) ); ?>');">
                                <?php echo esc_html__( 'Delete rule', 'disable-admin-ad' ); ?>
                            </button>
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
}

