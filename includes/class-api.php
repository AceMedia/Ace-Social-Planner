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
    }

    public static function can_manage_plugin() {
        return current_user_can('manage_options');
    }

    public static function validate_content($value) {
        return is_string($value) && trim($value) !== '';
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
