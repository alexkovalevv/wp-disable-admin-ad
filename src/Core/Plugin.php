<?php

namespace ADSD\Core;

use ADSD\Admin\Admin_Bar_Controller;
use ADSD\Admin\Assets_Manager;
use ADSD\Admin\Notice_ID_Manager;
use ADSD\Admin\Output_Buffer_Service;
use ADSD\Admin\Selection_Mode_Controller;
use ADSD\Logging\Logger;
use ADSD\Security\Capabilities_Service;
use ADSD\Security\Nonce_Service;
use ADSD\Settings\Options_Repository;
use ADSD\Settings\Settings_Page;
use ADSD\Domain\XPath_Engine;

/**
 * Class Plugin
 *
 * Entry point. Registers hooks and composes services via a lightweight container.
 */
class Plugin
{
    /** @var Service_Container */
    private Service_Container $container;

    public function __construct()
    {
        $this->container = new Service_Container();
        $this->register_services();
    }

    /**
     * Run the plugin and register WordPress hooks.
     */
    public function run(): void
    {
       
        add_action('admin_init', [$this, 'bootstrap_admin']);
        add_action('admin_bar_menu', [$this->container->get('admin_bar_controller'), 'render_admin_bar_icon'], 100);
        add_action('admin_enqueue_scripts', [$this->container->get('assets_manager'), 'enqueue_assets']);
        add_action('admin_head', [$this->container->get('output_buffer_service'), 'start_buffer'], 0);
        add_action('admin_footer', [$this->container->get('output_buffer_service'), 'end_buffer'], PHP_INT_MAX);
        add_action('rest_api_init', [$this->container->get('selection_mode_controller'), 'register_routes']);
        add_action('admin_menu', [$this->container->get('settings_page'), 'register_menu']);
        add_action('admin_enqueue_scripts', [$this->container->get('settings_page'), 'enqueue_admin_assets']);
        
        // Initialize notice ID manager for WordPress hooks (after admin is fully loaded)
        add_action('admin_init', function() {
            Notice_ID_Manager::get_instance()->init();
        }, 1);
        // Admin-post handlers for settings actions
        add_action('admin_post_adsd_reset_rules', [$this->container->get('settings_page'), 'handle_reset_rules']);
        add_action('admin_post_adsd_delete_rule', [$this->container->get('settings_page'), 'handle_delete_rule']);
        add_action('admin_post_adsd_update_rule', [$this->container->get('settings_page'), 'handle_update_rule']);
        add_action('admin_post_adsd_update_logging', [$this->container->get('settings_page'), 'handle_update_logging']);
        add_action('admin_post_adsd_update_general', [$this->container->get('settings_page'), 'handle_update_general']);
        add_action('admin_post_adsd_toggle_rule_active', [$this->container->get('settings_page'), 'handle_toggle_rule_active']);
        
        // AJAX handlers for modal operations
        add_action('wp_ajax_adsd_update_rule', [$this->container->get('settings_page'), 'handle_ajax_update_rule']);
        add_action('wp_ajax_adsd_toggle_rule', [$this->container->get('settings_page'), 'handle_ajax_toggle_rule']);
        add_action('wp_ajax_adsd_delete_rule', [$this->container->get('settings_page'), 'handle_ajax_delete_rule']);
        add_action('wp_ajax_adsd_get_rule', [$this->container->get('settings_page'), 'handle_ajax_get_rule']);
    }

    /**
     * Expose container for activation hook usage.
     */
    public function get_container(): Service_Container
    {
        return $this->container;
    }

    /**
     * Bootstrap admin related services and options.
     */
    public function bootstrap_admin(): void
    {
        // Ensure defaults exist.
        /** @var Options_Repository $options */
        $options = $this->container->get('options_repository');
        $options->maybe_migrate();
    }

    /**
     * Register services in the container.
     */
    private function register_services(): void
    {
        $this->container->set('logger', static function () {
            return new Logger('ads-destroyer');
        });

        $this->container->set('nonce_service', static function () {
            // Use WordPress REST nonce so core validates X-WP-Nonce properly.
            return new Nonce_Service('wp_rest');
        });

        $this->container->set('capabilities_service', function () {
            return new Capabilities_Service($this->container->get('options_repository'));
        });

        $this->container->set('options_repository', static function () {
            return new Options_Repository();
        });

        $this->container->set('xpath_engine', static function () {
            return new XPath_Engine();
        });

        $this->container->set('assets_manager', function () {
            return new Assets_Manager($this->container->get('options_repository'), $this->container->get('nonce_service'));
        });

        $this->container->set('admin_bar_controller', function () {
            return new Admin_Bar_Controller($this->container->get('options_repository'));
        });

        $this->container->set('selection_mode_controller', function () {
            return new Selection_Mode_Controller(
                $this->container->get('options_repository'),
                $this->container->get('nonce_service'),
                $this->container->get('capabilities_service'),
                $this->container->get('logger')
            );
        });

        $this->container->set('output_buffer_service', function () {
            return new Output_Buffer_Service(
                $this->container->get('options_repository'),
                $this->container->get('xpath_engine'),
                $this->container->get('logger')
            );
        });

        $this->container->set('settings_page', function () {
            return new Settings_Page(
                $this->container->get('options_repository'),
                $this->container->get('capabilities_service'),
                $this->container->get('logger')
            );
        });
    }
}

