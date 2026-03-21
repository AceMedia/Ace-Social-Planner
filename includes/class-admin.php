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

    public static function maybe_handle_oauth_callbacks() {
        ACE_Provider_X::handle_callback();
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
            'content_types' => ['post'],
            'post_statuses' => [
                'not_planned',
                'drafted',
                'awaiting_approval',
                'approved',
                'scheduled',
                'published',
            ],
            'networks' => [
                'x' => [
                    'label' => 'X',
                    'account_name' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'access_token' => '',
                    'refresh_token' => '',
                ],
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
            'content_types' => array_values(array_filter(array_map('sanitize_key', isset($value['content_types']) && is_array($value['content_types']) ? $value['content_types'] : $defaults['content_types']))),
            'post_statuses' => array_values(array_filter(array_map('sanitize_key', isset($value['post_statuses']) && is_array($value['post_statuses']) ? $value['post_statuses'] : $defaults['post_statuses']))),
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
                'access_token' => sanitize_text_field($network_value['access_token'] ?? ''),
                'refresh_token' => sanitize_text_field($network_value['refresh_token'] ?? ''),
            ];
        }

        return $sanitized;
    }

    public static function get_network_statuses($settings = null) {
        $settings = is_array($settings) ? $settings : self::get_settings();
        $statuses = [];

        foreach ($settings['networks'] as $network_key => $network) {
            $has_identity = !empty($network['account_name']);
            $has_key = !empty($network['app_id']) || !empty($network['client_id']);
            $has_secret = !empty($network['access_token']) || !empty($network['app_secret']) || !empty($network['client_secret']) || !empty($network['refresh_token']);
            $configured = $has_identity || $has_key || $has_secret;

            $statuses[$network_key] = [
                'configured' => $configured,
                'status' => $configured ? 'Configured' : 'Not configured',
            ];
        }

        $statuses['x'] = array_merge($statuses['x'], ACE_Provider_X::get_connection_status());

        return $statuses;
    }

    public static function get_calendar_preview($settings = null) {
        $settings = is_array($settings) ? $settings : self::get_settings();
        $time = $settings['default_publish_time'];

        return [
            ['day' => 'Mon', 'date' => '24 Mar', 'items' => [['time' => $time, 'title' => 'Match reaction post', 'network' => 'X', 'status' => 'Scheduled']]],
            ['day' => 'Tue', 'date' => '25 Mar', 'items' => [['time' => $time, 'title' => 'LinkedIn promo variant', 'network' => 'LinkedIn', 'status' => 'Awaiting approval']]],
            ['day' => 'Wed', 'date' => '26 Mar', 'items' => [['time' => $time, 'title' => 'Newsletter support post', 'network' => 'Facebook', 'status' => 'Drafted']]],
            ['day' => 'Thu', 'date' => '27 Mar', 'items' => [['time' => $time, 'title' => 'Evergreen tutorial reshare', 'network' => 'X', 'status' => 'Scheduled']]],
            ['day' => 'Fri', 'date' => '28 Mar', 'items' => [['time' => $time, 'title' => 'Weekend teaser post', 'network' => 'Instagram', 'status' => 'Approved']]],
            ['day' => 'Sat', 'date' => '29 Mar', 'items' => []],
            ['day' => 'Sun', 'date' => '30 Mar', 'items' => []],
        ];
    }

    public static function get_admin_bootstrap_data() {
        $settings = self::get_settings();
        $statuses = self::get_network_statuses($settings);

        return [
            'restBase' => rest_url('ace-social/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => $settings,
            'networkStatuses' => $statuses,
            'calendar' => self::get_calendar_preview($settings),
            'hasApiKey' => get_option('ace_openai_key', '') !== '',
            'notices' => [
                'success' => ACE_Provider_X::pop_notice('success'),
                'error' => ACE_Provider_X::pop_notice('error'),
            ],
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

        wp_localize_script('ace-social-planner-admin', 'aceSocialPlanner', self::get_admin_bootstrap_data());
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
