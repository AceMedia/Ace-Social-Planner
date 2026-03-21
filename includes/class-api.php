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

        register_rest_route('ace-social/v1', '/planner-items', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_planner_items'],
                'permission_callback' => [__CLASS__, 'can_manage_plugin'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'save_planner_item'],
                'permission_callback' => [__CLASS__, 'can_manage_plugin'],
            ],
        ]);

        register_rest_route('ace-social/v1', '/planner-items/(?P<id>[a-zA-Z0-9-]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [__CLASS__, 'delete_planner_item'],
            'permission_callback' => [__CLASS__, 'can_manage_plugin'],
        ]);

        register_rest_route('ace-social/v1', '/providers/x/connect-url', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_x_connect_url'],
            'permission_callback' => [__CLASS__, 'can_manage_plugin'],
        ]);

        register_rest_route('ace-social/v1', '/providers/x/disconnect', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'disconnect_x'],
            'permission_callback' => [__CLASS__, 'can_manage_plugin'],
        ]);
    }

    public static function can_manage_plugin() {
        return current_user_can('manage_options');
    }

    public static function validate_content($value) {
        return is_string($value) && trim($value) !== '';
    }

    public static function get_settings() {
        return rest_ensure_response(ACE_Admin::get_admin_bootstrap_data());
    }

    public static function update_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $settings_payload = isset($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : [];
        $api_key = isset($payload['apiKey']) ? (string) $payload['apiKey'] : null;

        update_option(ACE_Admin::SETTINGS_OPTION, ACE_Admin::sanitize_settings($settings_payload), false);

        if ($api_key !== null) {
            update_option('ace_openai_key', ACE_Admin::sanitize_api_key($api_key), false);
        }

        return self::get_settings();
    }

    public static function get_planner_items() {
        return rest_ensure_response([
            'items' => ACE_Planner::get_items(),
        ]);
    }

    public static function save_planner_item(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $item = isset($payload['item']) && is_array($payload['item']) ? $payload['item'] : [];
        $sanitized_item = ACE_Planner::sanitize_item($item);

        if ($sanitized_item['title'] === '') {
            return new WP_Error('ace_planner_missing_title', 'A scheduled social post needs a title.', ['status' => 400]);
        }

        if ($sanitized_item['start'] === '') {
            return new WP_Error('ace_planner_missing_start', 'A scheduled social post needs a start date.', ['status' => 400]);
        }

        return rest_ensure_response([
            'items' => ACE_Planner::save_item($sanitized_item),
        ]);
    }

    public static function delete_planner_item(WP_REST_Request $request) {
        return rest_ensure_response([
            'items' => ACE_Planner::delete_item((string) $request['id']),
        ]);
    }

    public static function get_x_connect_url() {
        $url = ACE_Provider_X::get_authorize_url(get_current_user_id());

        if (is_wp_error($url)) {
            return $url;
        }

        return rest_ensure_response([
            'authorizeUrl' => $url,
            'connection' => ACE_Provider_X::get_connection_status(),
        ]);
    }

    public static function disconnect_x() {
        return rest_ensure_response(ACE_Provider_X::disconnect());
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
