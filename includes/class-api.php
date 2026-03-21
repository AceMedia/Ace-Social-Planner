<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_API {

    public static function register_routes() {
        register_rest_route('ace-social/v1', '/ai/generate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'generate_ai'],
            'permission_callback' => [__CLASS__, 'can_manage_plugin'],
            'args' => [
                'content' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => [__CLASS__, 'validate_content'],
                ],
            ],
        ]);

        register_rest_route('ace-social/v1', '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_settings'],
                'permission_callback' => [__CLASS__, 'can_manage_plugin'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'update_settings'],
                'permission_callback' => [__CLASS__, 'can_manage_plugin'],
            ],
        ]);
    }

    public static function can_manage_plugin() {
        return current_user_can('manage_options');
    }

    public static function validate_content($value) {
        return is_string($value) && trim($value) !== '';
    }

    public static function get_settings() {
        $settings = ACE_Admin::get_settings();

        return rest_ensure_response([
            'settings' => $settings,
            'networkStatuses' => ACE_Admin::get_network_statuses($settings),
            'calendar' => ACE_Admin::get_calendar_preview($settings),
            'hasApiKey' => get_option('ace_openai_key', '') !== '',
        ]);
    }

    public static function update_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $settings_payload = isset($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : [];
        $api_key = isset($payload['apiKey']) ? (string) $payload['apiKey'] : null;

        $sanitized_settings = ACE_Admin::sanitize_settings($settings_payload);
        update_option(ACE_Admin::SETTINGS_OPTION, $sanitized_settings);

        if ($api_key !== null) {
            update_option('ace_openai_key', ACE_Admin::sanitize_api_key($api_key));
        }

        return self::get_settings();
    }

    public static function generate_ai(WP_REST_Request $request) {
        $content = (string) $request->get_param('content');
        $result = ACE_AI::generate($content);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }
}
