<?php

namespace AIDAD\Security;

/**
 * Capabilities service determining access to selection mode and settings.
 */
class Capabilities_Service {
    /**
     * Check if current user can access selection mode (read operations).
     */
    public function current_user_can_access(): bool {
        return current_user_can( 'read' );
    }

    /**
     * Check if current user can manage rules/settings (write operations).
     */
    public function current_user_can_manage(): bool {
        return current_user_can( 'manage_options' );
    }
}

