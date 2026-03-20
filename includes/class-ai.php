<?php
if (!defined('ABSPATH')) exit;

class ACE_AI {

    public static function generate($content) {
        $api_key = get_option('ace_openai_key');

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-4.1-mini',
                'input' => $content
            ])
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
