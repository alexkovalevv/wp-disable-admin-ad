<?php

namespace AIDAD\Security;

use AIDAD\Settings\Options_Repository;

/**
 * Capabilities service determining access to selection mode and settings.
 */
class Capabilities_Service {
    private Options_Repository $options_repository;

    public function __construct( Options_Repository $options_repository ) {
        $this->options_repository = $options_repository;
    }

    private function user_has_allowed_role(): bool {
        $opts = $this->options_repository->get_all();
        $roles_allowed = (array) ( $opts['access']['roles_allowed'] ?? [ 'administrator' ] );
        $user = wp_get_current_user();
        $user_roles = is_a( $user, 'WP_User' ) ? (array) $user->roles : [];
        foreach ( $user_roles as $role ) {
            if ( in_array( $role, $roles_allowed, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if current user can access selection mode (read operations).
     */
    public function current_user_can_access(): bool {
        return $this->user_has_allowed_role();
    }

    /**
     * Check if current user can manage rules/settings (write operations).
     */
    public function current_user_can_manage(): bool {
        return $this->user_has_allowed_role();
    }
}

