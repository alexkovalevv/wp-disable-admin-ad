<?php

namespace AIDAD\Core;

/**
 * Lightweight service container for dependency management.
 */
class Service_Container {
    /** @var array<string, callable|object> */
    private array $services = [];

    /**
     * Register a service factory or instance.
     *
     * @param string            $id
     * @param callable|object   $service
     */
    public function set( string $id, $service ): void {
        $this->services[ $id ] = $service;
    }

    /**
     * Get a service by id, lazily instantiating if necessary.
     *
     * @param string $id
     * @return mixed
     */
    public function get( string $id ) {
        if ( ! isset( $this->services[ $id ] ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- used for exception context only
            throw new \RuntimeException( 'Service not found: ' . sanitize_key( (string) $id ) );
        }
        $entry = $this->services[ $id ];
        if ( is_callable( $entry ) ) {
            $instance = $entry();
            $this->services[ $id ] = $instance;
            return $instance;
        }
        return $entry;
    }
}

