<?php

namespace AIDAD\Core;

use AIDAD\Admin\Admin_Bar_Controller;
use AIDAD\Admin\Assets_Manager;
use AIDAD\Admin\Output_Buffer_Service;
use AIDAD\Admin\Selection_Mode_Controller;
use AIDAD\Logging\Logger;
use AIDAD\Security\Capabilities_Service;
use AIDAD\Security\Nonce_Service;
use AIDAD\Settings\Options_Repository;
use AIDAD\Settings\Settings_Page;
use AIDAD\Domain\XPath_Engine;

/**
 * Class Plugin
 *
 * Entry point. Registers hooks and composes services via a lightweight container.
 */
class Plugin {
    /** @var Service_Container */
    private Service_Container $container;

    public function __construct() {
        $this->container = new Service_Container();
        $this->register_services();
    }

    /**
     * Run the plugin and register WordPress hooks.
     */
    public function run(): void {
        add_action( 'admin_init', [ $this, 'bootstrap_admin' ] );
        add_action( 'admin_bar_menu', [ $this->container->get( 'admin_bar_controller' ), 'render_admin_bar_icon' ], 100 );
        add_action( 'admin_enqueue_scripts', [ $this->container->get( 'assets_manager' ), 'enqueue_assets' ] );
        add_action( 'admin_head', [ $this->container->get( 'output_buffer_service' ), 'start_buffer' ], 0 );
        add_action( 'admin_footer', [ $this->container->get( 'output_buffer_service' ), 'end_buffer' ], PHP_INT_MAX );
        add_action( 'rest_api_init', [ $this->container->get( 'selection_mode_controller' ), 'register_routes' ] );
        add_action( 'admin_menu', [ $this->container->get( 'settings_page' ), 'register_menu' ] );
        // Admin-post handlers for settings actions
        add_action( 'admin_post_aidad_reset_rules', [ $this->container->get( 'settings_page' ), 'handle_reset_rules' ] );
        add_action( 'admin_post_aidad_delete_rule', [ $this->container->get( 'settings_page' ), 'handle_delete_rule' ] );
    }

    /**
     * Expose container for activation hook usage.
     */
    public function get_container(): Service_Container {
        return $this->container;
    }

    /**
     * Bootstrap admin related services and options.
     */
    public function bootstrap_admin(): void {
        // Ensure defaults exist.
        /** @var Options_Repository $options */
        $options = $this->container->get( 'options_repository' );
        $options->maybe_migrate();
    }

    /**
     * Register services in the container.
     */
    private function register_services(): void {
        $this->container->set( 'logger', static function () {
            return new Logger( 'disable-admin-ad' );
        } );

        $this->container->set( 'nonce_service', static function () {
            // Use WordPress REST nonce so core validates X-WP-Nonce properly.
            return new Nonce_Service( 'wp_rest' );
        } );

        $this->container->set( 'capabilities_service', static function () {
            return new Capabilities_Service();
        } );

        $this->container->set( 'options_repository', static function () {
            return new Options_Repository();
        } );

        $this->container->set( 'xpath_engine', static function () {
            return new XPath_Engine();
        } );

        $this->container->set( 'assets_manager', function () {
            return new Assets_Manager( $this->container->get( 'options_repository' ), $this->container->get( 'nonce_service' ) );
        } );

        $this->container->set( 'admin_bar_controller', function () {
            return new Admin_Bar_Controller( $this->container->get( 'options_repository' ) );
        } );

        $this->container->set( 'selection_mode_controller', function () {
            return new Selection_Mode_Controller(
                $this->container->get( 'options_repository' ),
                $this->container->get( 'nonce_service' ),
                $this->container->get( 'capabilities_service' ),
                $this->container->get( 'logger' )
            );
        } );

        $this->container->set( 'output_buffer_service', function () {
            return new Output_Buffer_Service(
                $this->container->get( 'options_repository' ),
                $this->container->get( 'xpath_engine' ),
                $this->container->get( 'logger' )
            );
        } );

        $this->container->set( 'settings_page', function () {
            return new Settings_Page(
                $this->container->get( 'options_repository' ),
                $this->container->get( 'capabilities_service' ),
                $this->container->get( 'logger' )
            );
        } );
    }
}

