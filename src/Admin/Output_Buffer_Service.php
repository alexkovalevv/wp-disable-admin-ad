<?php

namespace ADSD\Admin;

use ADSD\Domain\XPath_Engine;
use ADSD\Logging\Logger;
use ADSD\Settings\Options_Repository;

/**
 * Starts output buffering on admin pages and manipulates HTML before sending to browser.
 */
class Output_Buffer_Service {
    private Options_Repository $options_repository;
    private XPath_Engine $xpath_engine;
    private Logger $logger;

    private bool $buffering = false;

    public function __construct( Options_Repository $options_repository, XPath_Engine $xpath_engine, Logger $logger ) {
        $this->options_repository = $options_repository;
        $this->xpath_engine       = $xpath_engine;
        $this->logger             = $logger;
    }

    /**
     * Start output buffer early in admin head if there are active rules.
     */
    public function start_buffer(): void {
        if ( ! is_admin() ) {
            return;
        }
        $opts = $this->options_repository->get_all();
        if ( empty( $opts['enabled'] ) ) {
            return;
        }
        $now = time();
        $rules = array_filter( (array) ( $opts['rules'] ?? [] ), static function ( $r ) use ( $now ) {
            if ( empty( $r['active'] ) ) return false;
            $exp = isset( $r['expires_at'] ) ? (int) $r['expires_at'] : 0;
            return $exp === 0 || $exp > $now;
        } );
        if ( empty( $rules ) ) {
            return;
        }
        if ( ! $this->buffering ) {
            $this->buffering = true;
            ob_start( [ $this, 'process_html' ], 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE );
        }
    }

    /**
     * End buffer in admin footer to ensure output is flushed.
     */
    public function end_buffer(): void {
        if ( $this->buffering ) {
            // Flush buffer safely.
            @ob_end_flush();
            $this->buffering = false;
        }
    }

    /**
     * Callback to process buffered HTML.
     *
     * @param string $html
     * @return string
     */
    public function process_html( string $html ): string {
        try {
            $opts   = $this->options_repository->get_all();
            $now    = time();
            $rules  = array_filter( (array) ( $opts['rules'] ?? [] ), static function ( $r ) use ( $now ) {
                if ( empty( $r['active'] ) ) return false;
                $exp = isset( $r['expires_at'] ) ? (int) $r['expires_at'] : 0;
                return $exp === 0 || $exp > $now;
            } );
            if ( empty( $rules ) ) {
                return $html;
            }
            $delete_nodes = (bool) ( $opts['mode']['delete_nodes'] ?? false );
            $safe_preview = (bool) ( $opts['safe_preview'] ?? false );
            if ( $safe_preview && current_user_can( 'manage_options' ) ) {
                // In safe preview, do not hide for admins.
                return $html;
            }
            $xpaths = array_map( static function ( $r ) { return (string) $r['xpath']; }, $rules );
            return $this->xpath_engine->apply_rules( $html, $xpaths, $delete_nodes );
        } catch ( \Throwable $e ) {
            $this->logger->log( 'buffer_error', [ 'message' => $e->getMessage() ] );
            return $html; // Fail-safe: return original HTML
        }
    }

}

