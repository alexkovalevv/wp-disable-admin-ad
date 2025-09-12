<?php

namespace ADSD\Logging;

/**
 * Minimal logger storing entries in options with rotation.
 */
class Logger {
    private string $option_key;

    public function __construct( string $option_key ) {
        $this->option_key = $option_key . '_logs';
    }

    /**
     * Log an event if logging is enabled.
     *
     * @param string               $event
     * @param array<string,mixed>  $context
     */
    public function log( string $event, array $context = [] ): void {
        $enabled = (bool) get_option( 'adsd_logging_enabled', true );
        $max     = (int) get_option( 'adsd_logging_max', 500 );
        if ( ! $enabled ) {
            return;
        }
        $logs = get_option( $this->option_key, [] );
        $logs[] = [
            'time'   => current_time( 'mysql', true ),
            'event'  => $event,
            'context'=> $context,
        ];
        if ( count( $logs ) > $max ) {
            $logs = array_slice( $logs, -$max );
        }
        update_option( $this->option_key, $logs );
    }

    /**
     * Get last N logs.
     *
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function get( int $limit = 50 ): array {
        $logs = (array) get_option( $this->option_key, [] );
        return array_slice( $logs, -$limit );
    }
}

