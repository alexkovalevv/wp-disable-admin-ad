<?php

namespace AIDAD\Settings;

use WP_Error;

/**
 * Options repository: handles schema, defaults, CRUD for rules, multisite storage, and migrations.
 */
class Options_Repository
{
    private string $option_name = 'wp_admin_disable_ad_options';
    private string $network_option_name = 'wp_admin_network_disable_ad_options';
    private string $version = '1.0.0';

    /**
     * Get all options according to current site/network context.
     *
     * @return array<string,mixed>
     */
    public function get_all(): array
    {
        if ($this->is_network_wide()) {
            $opts = get_site_option($this->network_option_name, []);
        } else {
            $opts = get_option($this->option_name, []);
        }
        if (empty($opts)) {
            $opts = $this->get_defaults();
        }
        return $this->normalize($opts);
    }

    /**
     * Ensure defaults exist on first run/activation.
     */
    public function ensure_defaults(): void
    {
        $current = $this->get_all();
        $saved = $this->is_network_wide() ? get_site_option($this->network_option_name, null) : get_option($this->option_name, null);
        if ($saved === null) {
            $this->save_all($current);
        }
    }

    /**
     * Save all options.
     *
     * @param array<string,mixed> $options
     */
    public function save_all(array $options): void
    {
        $options['version'] = $this->version;
        if ($this->is_network_wide()) {
            update_site_option($this->network_option_name, $options);
        } else {
            update_option($this->option_name, $options);
        }
    }

    /**
     * Add a new rule.
     *
     * @param array{xpath:string,label:string,active:bool} $data
     * @return array<string,mixed>
     */
    public function add_rule(array $data): array
    {
        $all = $this->get_all();
        $id = $this->generate_id($data['xpath']);
        $rule = [
            'id' => $id,
            'xpath' => $data['xpath'],
            'label' => $data['label'] ?? '',
            'active' => (bool)($data['active'] ?? true),
            'author' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'expires_at' => isset($data['expires_at']) ? (int)$data['expires_at'] : 0,
        ];
        $all['rules'][] = $rule;
        $this->save_all($all);
        return $rule;
    }

    /**
     * Update a rule by id.
     *
     * @param string $id
     * @param array{xpath?:string,label?:string,active?:bool} $data
     * @return array<string,mixed>|null
     */
    public function update_rule(string $id, array $data): ?array
    {
        $all = $this->get_all();
        foreach ($all['rules'] as &$rule) {
            if ($rule['id'] === $id) {
                if (isset($data['xpath'])) {
                    $rule['xpath'] = $this->sanitize_xpath((string)$data['xpath']);
                }
                if (isset($data['label'])) {
                    $rule['label'] = sanitize_text_field((string)$data['label']);
                }
                if (isset($data['active'])) {
                    $rule['active'] = (bool)$data['active'];
                }
                if (isset($data['expires_at'])) {
                    $rule['expires_at'] = (int)$data['expires_at'];
                }
                $this->save_all($all);
                return $rule;
            }
        }
        return null;
    }

    /**
     * Delete a rule by id.
     */
    public function delete_rule(string $id): bool
    {
        $all = $this->get_all();
        $original = $all['rules'];
        $all['rules'] = array_values(array_filter($all['rules'], static function ($r) use ($id) {
            return $r['id'] !== $id;
        }));
        if (count($original) === count($all['rules'])) {
            return false;
        }
        $this->save_all($all);
        return true;
    }

    /**
     * Clear all rules at once.
     */
    public function clear_rules(): void
    {
        $all = $this->get_all();
        $all['rules'] = [];
        $this->save_all($all);
    }

    /**
     * Perform migrations if versions differ.
     */
    public function maybe_migrate(): void
    {
        $all = $this->get_all();
        $saved_version = $all['version'] ?? '0.0.0';
        if (version_compare((string)$saved_version, $this->version, '<')) {
            // No migrations yet; placeholder.
            $all['version'] = $this->version;
            $this->save_all($all);
        }
    }

    /**
     * Get defaults with provided initial schema.
     *
     * @return array<string,mixed>
     */
    private function get_defaults(): array
    {
        return [
            'enabled' => true,
            'rules' => [],
            'ui' => [
                'hover_color' => '#00c853',
                'selected_color' => '#d50000',
                'dimming_opacity' => 0.4,
                'blur' => '2px',
                'hotkey' => 'Ctrl+Shift+X',
            ],
            'mode' => [
                'delete_nodes' => false,
            ],
            'access' => [
                'roles_allowed' => ['administrator'],
                'network_wide' => false,
            ],
            'logging' => [
                'enabled' => true,
                'max_entries' => 500,
            ],
            'safe_preview' => false,
            'version' => $this->version,
        ];
    }

    /**
     * Normalize incoming options array to ensure keys exist.
     *
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    private function normalize(array $opts): array
    {
        $defaults = $this->get_defaults();
        $opts = array_replace_recursive($defaults, $opts);
        // Enforce types
        $opts['enabled'] = (bool)$opts['enabled'];
        $opts['mode']['delete_nodes'] = (bool)$opts['mode']['delete_nodes'];
        $opts['logging']['enabled'] = (bool)$opts['logging']['enabled'];
        $opts['logging']['max_entries'] = (int)$opts['logging']['max_entries'];
        $opts['ui']['dimming_opacity'] = (float)$opts['ui']['dimming_opacity'];
        $opts['access']['network_wide'] = (bool)$opts['access']['network_wide'];
        return $opts;
    }

    /**
     * Check if network-wide settings are enabled and multisite active.
     */
    private function is_network_wide(): bool
    {
        return function_exists('is_multisite') && is_multisite() && (bool)get_site_option('aidad_network_wide', false);
    }

    /**
     * Sanitize XPath string conservatively.
     */
    private function sanitize_xpath(string $xpath): string
    {
        $xpath = wp_strip_all_tags($xpath);
        $xpath = preg_replace('/[\x00-\x1F\x7F]/u', '', $xpath);
        $xpath = trim($xpath);
        if (strlen($xpath) > 500) {
            $xpath = substr($xpath, 0, 500);
        }
        return $xpath;
    }

    /**
     * Generate stable rule id from xpath.
     */
    private function generate_id(string $xpath): string
    {
        return substr(md5($xpath . '|' . microtime(true)), 0, 12);
    }
}

