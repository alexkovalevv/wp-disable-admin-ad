<?php

namespace AIDAD\Admin;

use AIDAD\Settings\Options_Repository;

/**
 * Renders admin bar icon and handles client-side toggle (state stored in user meta via JS).
 */
class Admin_Bar_Controller
{
    private Options_Repository $options_repository;

    public function __construct(Options_Repository $options_repository)
    {
        $this->options_repository = $options_repository;
    }

    /**
     * Add toggle icon to admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function render_admin_bar_icon($wp_admin_bar): void
    {
        if (!is_admin()) {
            return;
        }

        $opts = $this->options_repository->get_all();
        $show_button = isset($opts['ui']['show_admin_bar_button']) ? (bool)$opts['ui']['show_admin_bar_button'] : true;
        if (!$show_button) {
            return;
        }
        // Check role access
        $roles_allowed = (array)($opts['access']['roles_allowed'] ?? ['administrator']);
        $user = wp_get_current_user();
        $user_roles = is_a($user, 'WP_User') ? (array)$user->roles : [];
        $has_access = false;
        foreach ($user_roles as $role) {
            if (in_array($role, $roles_allowed, true)) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            return;
        }

        $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 9.01 9.01 0 0 0-9-9Zm0 16a7 7 0 1 1 7-7 7.008 7.008 0 0 1-7 7Zm0-12a5 5 0 1 0 5 5 5.006 5.006 0 0 0-5-5Zm0 7a2 2 0 1 1 2-2 2.003 2.003 0 0 1-2 2Z" fill="currentColor"/></svg>';

        $wp_admin_bar->add_node(
            [
                'id' => 'aidad-toggle',
                'title' => '<span class="ab-icon" id="aidad-icon" aria-hidden="true">' . $icon_svg . '</span><span class="ab-label">' . esc_html__('Disable ad', 'disable-admin-ad') . '</span>',
                'href' => '#',
                'meta' => [
                    'title' => esc_attr__('Toggle selection mode', 'disable-admin-ad'),
                ],
            ]
        );
    }
}

