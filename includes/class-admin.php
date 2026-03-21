<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_Admin {

    const MENU_SLUG = 'ace-social-planner';
    const SETTINGS_OPTION = 'ace_social_planner_settings';

    public static function register_menu() {
        add_menu_page(
            'ACE Social Planner',
            'ACE Social Planner',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-share',
            58
        );
    }

    public static function register_settings() {
        register_setting('ace_social_planner', self::SETTINGS_OPTION, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default' => self::get_default_settings(),
        ]);

        register_setting('ace_social_planner', 'ace_openai_key', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
            'default' => '',
        ]);
    }

    public static function sanitize_api_key($value) {
        return trim(sanitize_text_field((string) $value));
    }

    public static function get_default_settings() {
        return [
            'workspace_name' => 'ACE Social Planner',
            'default_timezone' => wp_timezone_string() ?: 'UTC',
            'default_publish_time' => '09:00',
            'week_starts_on' => 'monday',
            'networks' => [
                'facebook' => [
                    'label' => 'Facebook',
                    'account_name' => '',
                    'app_id' => '',
                    'app_secret' => '',
                    'access_token' => '',
                ],
                'instagram' => [
                    'label' => 'Instagram',
                    'account_name' => '',
                    'app_id' => '',
                    'app_secret' => '',
                    'access_token' => '',
                ],
                'linkedin' => [
                    'label' => 'LinkedIn',
                    'account_name' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'access_token' => '',
                ],
                'x' => [
                    'label' => 'X',
                    'account_name' => '',
                    'api_key' => '',
                    'api_secret' => '',
                    'access_token' => '',
                ],
            ],
        ];
    }

    public static function get_settings() {
        $saved = get_option(self::SETTINGS_OPTION, []);

        return self::merge_settings(self::get_default_settings(), is_array($saved) ? $saved : []);
    }

    public static function sanitize_settings($value) {
        $defaults = self::get_default_settings();
        $value = is_array($value) ? $value : [];

        $sanitized = [
            'workspace_name' => sanitize_text_field($value['workspace_name'] ?? $defaults['workspace_name']),
            'default_timezone' => sanitize_text_field($value['default_timezone'] ?? $defaults['default_timezone']),
            'default_publish_time' => sanitize_text_field($value['default_publish_time'] ?? $defaults['default_publish_time']),
            'week_starts_on' => in_array(($value['week_starts_on'] ?? ''), ['monday', 'sunday'], true) ? $value['week_starts_on'] : $defaults['week_starts_on'],
            'networks' => [],
        ];

        foreach ($defaults['networks'] as $network_key => $network_defaults) {
            $network_value = isset($value['networks'][$network_key]) && is_array($value['networks'][$network_key])
                ? $value['networks'][$network_key]
                : [];

            $sanitized['networks'][$network_key] = [
                'label' => $network_defaults['label'],
                'account_name' => sanitize_text_field($network_value['account_name'] ?? ''),
                'app_id' => sanitize_text_field($network_value['app_id'] ?? ''),
                'app_secret' => sanitize_text_field($network_value['app_secret'] ?? ''),
                'client_id' => sanitize_text_field($network_value['client_id'] ?? ''),
                'client_secret' => sanitize_text_field($network_value['client_secret'] ?? ''),
                'api_key' => sanitize_text_field($network_value['api_key'] ?? ''),
                'api_secret' => sanitize_text_field($network_value['api_secret'] ?? ''),
                'access_token' => sanitize_text_field($network_value['access_token'] ?? ''),
            ];
        }

        return $sanitized;
    }

    public static function get_network_statuses($settings = null) {
        $settings = is_array($settings) ? $settings : self::get_settings();
        $statuses = [];

        foreach ($settings['networks'] as $network_key => $network) {
            $has_identity = !empty($network['account_name']);
            $has_secret = !empty($network['access_token']) || !empty($network['app_secret']) || !empty($network['client_secret']) || !empty($network['api_secret']);

            $statuses[$network_key] = [
                'configured' => $has_identity || $has_secret,
                'status' => ($has_identity || $has_secret) ? 'Configured' : 'Not configured',
            ];
        }

        return $statuses;
    }

    public static function get_calendar_preview($settings = null) {
        $settings = is_array($settings) ? $settings : self::get_settings();
        $time = $settings['default_publish_time'];

        return [
            ['day' => 'Mon', 'date' => 'Planning', 'items' => [['time' => $time, 'title' => 'Editorial planning block', 'network' => 'Internal']]],
            ['day' => 'Tue', 'date' => 'Drafts', 'items' => [['time' => $time, 'title' => 'Product teaser draft', 'network' => 'LinkedIn']]],
            ['day' => 'Wed', 'date' => 'Review', 'items' => [['time' => $time, 'title' => 'Campaign review and approvals', 'network' => 'Facebook']]],
            ['day' => 'Thu', 'date' => 'Publish', 'items' => [['time' => $time, 'title' => 'Launch post slot', 'network' => 'Instagram']]],
            ['day' => 'Fri', 'date' => 'Recycle', 'items' => [['time' => $time, 'title' => 'Evergreen repost window', 'network' => 'X']]],
            ['day' => 'Sat', 'date' => 'Queue', 'items' => []],
            ['day' => 'Sun', 'date' => 'Buffer', 'items' => []],
        ];
    }

    public static function enqueue_assets($hook_suffix) {
        if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'ace-social-planner-admin',
            plugin_dir_url(__FILE__) . '../admin/style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../admin/style.css')
        );

        wp_enqueue_script(
            'ace-social-planner-admin',
            plugin_dir_url(__FILE__) . '../admin/app.js',
            ['wp-element'],
            filemtime(plugin_dir_path(__FILE__) . '../admin/app.js'),
            true
        );

        $settings = self::get_settings();

        wp_localize_script('ace-social-planner-admin', 'aceSocialPlanner', [
            'restBase' => rest_url('ace-social/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => $settings,
            'networkStatuses' => self::get_network_statuses($settings),
            'calendar' => self::get_calendar_preview($settings),
            'hasApiKey' => get_option('ace_openai_key', '') !== '',
        ]);
    }

    private static function merge_settings($defaults, $saved) {
        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = self::merge_settings($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    public static function render_page() {
        ?>
        <div class="wrap ace-social-planner-page">
            <div id="ace-social-planner-app"></div>
        </div>
        <?php
    }
}
