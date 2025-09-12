<?php

namespace ADSD\Security;

use WP_REST_Request;

/**
 * Nonce service to create and verify action nonces for REST/AJAX.
 */
class Nonce_Service {
    private string $action;

    public function __construct( string $action ) {
        $this->action = $action;
    }

    /**
     * Create nonce string.
     */
    public function create(): string {
        return wp_create_nonce( $this->action );
    }

    /**
     * Verify nonce from REST request headers.
     */
    public function verify_request( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! is_string( $nonce ) ) {
            $nonce = $request->get_param( '_wpnonce' ) ?? '';
        }
        return wp_verify_nonce( (string) $nonce, $this->action ) === 1;
    }
}

