<?php

namespace AIDAD\Admin;

use AIDAD\Settings\Options_Repository;

/**
 * Renders admin bar icon and handles client-side toggle (state stored in user meta via JS).
 */
class Admin_Bar_Controller {
    private Options_Repository $options_repository;

    public function __construct( Options_Repository $options_repository ) {
        $this->options_repository = $options_repository;
    }

    /**
     * Add toggle icon to admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function render_admin_bar_icon( $wp_admin_bar ): void {
        if ( ! is_admin() ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) { // refined via settings roles in Assets_Manager
            return;
        }

        $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 9.01 9.01 0 0 0-9-9Zm0 16a7 7 0 1 1 7-7 7.008 7.008 0 0 1-7 7Zm0-12a5 5 0 1 0 5 5 5.006 5.006 0 0 0-5-5Zm0 7a2 2 0 1 1 2-2 2.003 2.003 0 0 1-2 2Z" fill="currentColor"/></svg>';

        $wp_admin_bar->add_node(
            [
                'id'    => 'aidad-toggle',
                'title' => '<span class="ab-icon" id="aidad-icon" aria-hidden="true">' . $icon_svg . '</span><span class="ab-label">' . esc_html__( 'Selector', 'disable-admin-ad' ) . '</span>',
                'href'  => '#',
                'meta'  => [
                    'title' => esc_attr__( 'Toggle selection mode', 'disable-admin-ad' ),
                ],
            ]
        );
    }
}

