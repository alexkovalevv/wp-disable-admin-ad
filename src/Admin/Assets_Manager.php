<?php

namespace AIDAD\Admin;

use AIDAD\Security\Nonce_Service;
use AIDAD\Settings\Options_Repository;

/**
 * Registers and enqueues admin assets, passes settings via wp_localize_script and wp_set_script_translations.
 */
class Assets_Manager {
    private Options_Repository $options_repository;
    private Nonce_Service $nonce_service;

    public function __construct( Options_Repository $options_repository, Nonce_Service $nonce_service ) {
        $this->options_repository = $options_repository;
        $this->nonce_service      = $nonce_service;
    }

    /**
     * Enqueue assets in admin and provide runtime config.
     */
    public function enqueue_assets(): void {
        if ( ! is_admin() ) {
            return;
        }

        $opts = $this->options_repository->get_all();
        $enabled = (bool) ( $opts['enabled'] ?? true );

        // Only enqueue if plugin enabled or safe-preview requested by admin.
        if ( ! $enabled && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $handle_js  = 'aidad-admin-js';
        $handle_css = 'aidad-admin-css';

        $script_path = AIDAD_PLUGIN_DIR . '/build/js/admin.js';
        $script_url  = AIDAD_PLUGIN_URL . 'build/js/admin.js';
        $style_path  = AIDAD_PLUGIN_DIR . '/build/css/style.css';
        $style_url   = AIDAD_PLUGIN_URL . 'build/css/style.css';

        $ver = defined('AIDAD_PLUGIN_VERSION') ? AIDAD_PLUGIN_VERSION : null;
        if ( file_exists( $script_path ) ) {
            wp_register_script( $handle_js, $script_url, [], $ver, true );
            wp_enqueue_script( $handle_js );
            wp_set_script_translations( $handle_js, 'disable-admin-ad', AIDAD_PLUGIN_DIR . '/languages' );
        }
        if ( file_exists( $style_path ) ) {
            wp_register_style( $handle_css, $style_url, [], $ver );
            wp_enqueue_style( $handle_css );
        }

        $roles_allowed = (array) ( $opts['access']['roles_allowed'] ?? [ 'administrator' ] );
        $ui = (array) ( $opts['ui'] ?? [] );

        $config = [
            'rest_url'         => esc_url_raw( rest_url( 'aidad/v1' ) ),
            'nonce'            => $this->nonce_service->create(),
            'enabled'          => $enabled,
            'roles_allowed'    => $roles_allowed,
            'ui'               => [
                'hover_color'    => $ui['hover_color'] ?? '#00c853',
                'selected_color' => $ui['selected_color'] ?? '#d50000',
                'dimming_opacity'=> (float) ( $ui['dimming_opacity'] ?? 0.4 ),
                'blur'           => $ui['blur'] ?? '2px',
                'hotkey'         => $ui['hotkey'] ?? 'Ctrl+Shift+X',
            ],
            'mode'             => [
                'delete_nodes' => (bool) ( $opts['mode']['delete_nodes'] ?? false ),
            ],
            'safe_preview'     => (bool) ( $opts['safe_preview'] ?? false ),
        ];

        wp_localize_script( $handle_js, 'AIDAD_CONFIG', $config );

        // Ensure hidden placeholders are not visible if delete mode uses placeholders
        $inline_css = '[data-aidad-removed]{display:none !important;}';
        wp_add_inline_style( $handle_css, $inline_css );
    }
}

