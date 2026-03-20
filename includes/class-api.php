<?php
if (!defined('ABSPATH')) exit;

class ACE_API {

    public static function register_routes() {

        register_rest_route('ace-social/v1', '/ai/generate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'generate_ai'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public static function generate_ai($request) {
        $content = sanitize_text_field($request->get_param('content'));
        return ACE_AI::generate($content);
    }
}
