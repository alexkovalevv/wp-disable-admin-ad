<?php

namespace AIDAD\Admin;

use AIDAD\Logging\Logger;
use AIDAD\Security\Capabilities_Service;
use AIDAD\Security\Nonce_Service;
use AIDAD\Settings\Options_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers REST API routes for CRUD operations on XPath rules.
 */
class Selection_Mode_Controller {
    private Options_Repository $options_repository;
    private Nonce_Service $nonce_service;
    private Capabilities_Service $capabilities_service;
    private Logger $logger;

    public function __construct(
        Options_Repository $options_repository,
        Nonce_Service $nonce_service,
        Capabilities_Service $capabilities_service,
        Logger $logger
    ) {
        $this->options_repository    = $options_repository;
        $this->nonce_service         = $nonce_service;
        $this->capabilities_service  = $capabilities_service;
        $this->logger                = $logger;
    }

    /**
     * Register routes.
     */
    public function register_routes(): void {
        register_rest_route(
            'aidad/v1',
            '/rules',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'handle_list_rules' ],
                    'permission_callback' => [ $this, 'check_read_permission' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'handle_create_rule' ],
                    'permission_callback' => [ $this, 'check_write_permission' ],
                ],
            ]
        );

        // Reset all rules at once (register before parameterized route to avoid conflicts)
        register_rest_route(
            'aidad/v1',
            '/rules/reset',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_reset_rules' ],
                'permission_callback' => [ $this, 'check_write_permission' ],
            ]
        );

        register_rest_route(
            'aidad/v1',
            '/rules/(?P<id>[a-zA-Z0-9_-]+)',
            [
                [
                    'methods'             => 'DELETE',
                    'callback'            => [ $this, 'handle_delete_rule' ],
                    'permission_callback' => [ $this, 'check_write_permission' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'handle_update_rule' ],
                    'permission_callback' => [ $this, 'check_write_permission' ],
                ],
            ]
        );

        register_rest_route(
            'aidad/v1',
            '/test',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_test_xpath' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ]
        );
    }

    public function check_read_permission(): bool {
        return $this->capabilities_service->current_user_can_access();
    }

    public function check_write_permission(): bool {
        return $this->capabilities_service->current_user_can_manage();
    }

    /**
     * GET /rules
     */
    public function handle_list_rules( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $opts = $this->options_repository->get_all();
        $rules = (array) ( $opts['rules'] ?? [] );
        return new WP_REST_Response( [ 'rules' => $rules ] );
    }

    /**
     * POST /rules
     */
    public function handle_create_rule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $params = $this->sanitize_rule_params( $request->get_json_params() ?? [] );
        if ( is_wp_error( $params ) ) {
            return $params;
        }
        $rule  = $this->options_repository->add_rule( $params );
        $this->logger->log( 'rule_added', [ 'id' => $rule['id'] ] );
        return new WP_REST_Response( [ 'rule' => $rule ] );
    }

    /**
     * POST /rules/{id}
     */
    public function handle_update_rule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $id = sanitize_key( $request['id'] );
        $params = $this->sanitize_rule_params( $request->get_json_params() ?? [] );
        if ( is_wp_error( $params ) ) {
            return $params;
        }
        $rule = $this->options_repository->update_rule( $id, $params );
        if ( ! $rule ) {
            return new WP_Error( 'not_found', __( 'Rule not found', 'ads-destroyer' ), [ 'status' => 404 ] );
        }
        $this->logger->log( 'rule_updated', [ 'id' => $id ] );
        return new WP_REST_Response( [ 'rule' => $rule ] );
    }

    /**
     * DELETE /rules/{id}
     */
    public function handle_delete_rule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $id = sanitize_key( $request['id'] );
        $ok = $this->options_repository->delete_rule( $id );
        if ( ! $ok ) {
            return new WP_Error( 'not_found', __( 'Rule not found', 'ads-destroyer' ), [ 'status' => 404 ] );
        }
        $this->logger->log( 'rule_deleted', [ 'id' => $id ] );
        return new WP_REST_Response( [ 'deleted' => true ] );
    }

    /**
     * POST /rules/reset
     */
    public function handle_reset_rules( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $this->options_repository->clear_rules();
        $this->logger->log( 'rules_reset' );
        return new WP_REST_Response( [ 'reset' => true ] );
    }

    /**
     * POST /test
     */
    public function handle_test_xpath( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! $this->nonce_service->verify_request( $request ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'ads-destroyer' ), [ 'status' => 403 ] );
        }
        $body = $request->get_json_params() ?? [];
        $xpath = isset( $body['xpath'] ) ? (string) $body['xpath'] : '';
        $xpath = $this->sanitize_xpath( $xpath );
        if ( $xpath === '' ) {
            return new WP_Error( 'invalid_xpath', __( 'Invalid XPath', 'ads-destroyer' ), [ 'status' => 400 ] );
        }
        // Client-side testing is preferred; here we just echo back sanitized.
        return new WP_REST_Response( [ 'xpath' => $xpath, 'ok' => true ] );
    }

    /**
     * Sanitize and validate rule params.
     *
     * @param array $data
     * @return array|WP_Error
     */
    private function sanitize_rule_params( array $data ) {
        $xpath = isset( $data['xpath'] ) ? $this->sanitize_xpath( (string) $data['xpath'] ) : '';
        if ( $xpath === '' ) {
            return new WP_Error( 'invalid_xpath', __( 'Invalid XPath', 'ads-destroyer' ), [ 'status' => 400 ] );
        }
        $label  = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : '';
        $active = isset( $data['active'] ) ? (bool) $data['active'] : true;
        $expires_at = 0;
        if ( isset( $data['expires_at'] ) ) {
            $expires_at = (int) $data['expires_at'];
            if ( $expires_at < 0 ) { $expires_at = 0; }
        }
        return [
            'xpath'      => $xpath,
            'label'      => $label,
            'active'     => $active,
            'expires_at' => $expires_at,
        ];
    }

    /**
     * Sanitize XPath to a safe, bounded string.
     */
    private function sanitize_xpath( string $xpath ): string {
        $xpath = wp_strip_all_tags( $xpath );
        $xpath = preg_replace( '/[\x00-\x1F\x7F]/u', '', $xpath );
        $xpath = trim( $xpath );
        if ( strlen( $xpath ) > 500 ) {
            $xpath = substr( $xpath, 0, 500 );
        }
        return $xpath;
    }
}

