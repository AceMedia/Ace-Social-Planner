<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_AI {

    public static function generate($content) {
        $content = trim(wp_strip_all_tags((string) $content));

        if ($content === '') {
            return new WP_Error('ace_empty_content', 'Content is required.', ['status' => 400]);
        }

        $api_key = trim((string) get_option('ace_openai_key', ''));

        if ($api_key === '') {
            return new WP_Error('ace_missing_api_key', 'Add an OpenAI API key in the plugin settings before generating content.', ['status' => 400]);
        }

        $prompt = self::build_prompt($content);

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4.1-mini',
                'input' => $prompt,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_openai_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = self::extract_error_message($decoded);

            return new WP_Error(
                'ace_openai_http_error',
                $message ?: 'OpenAI returned an unexpected error.',
                ['status' => 502, 'openai_status' => $status_code]
            );
        }

        if (!is_array($decoded)) {
            return new WP_Error('ace_invalid_openai_response', 'OpenAI returned an invalid JSON response.', ['status' => 502]);
        }

        $output_text = self::extract_output_text($decoded);

        if ($output_text === '') {
            return new WP_Error('ace_empty_openai_output', 'OpenAI did not return any usable text.', ['status' => 502]);
        }

        return [
            'prompt' => $prompt,
            'output_text' => $output_text,
            'model' => isset($decoded['model']) ? (string) $decoded['model'] : 'gpt-4.1-mini',
            'response_id' => isset($decoded['id']) ? (string) $decoded['id'] : '',
            'raw' => $decoded,
        ];
    }

    private static function build_prompt($content) {
        return implode("\n", [
            'You are helping a WordPress publisher prepare social copy.',
            'Generate concise output in plain text with these sections:',
            '1. Summary',
            '2. Three social captions',
            '3. Suggested hashtags',
            '4. Best posting angle',
            'Keep the tone practical and publication-ready.',
            'Source content:',
            $content,
        ]);
    }

    private static function extract_error_message($decoded) {
        if (!is_array($decoded)) {
            return '';
        }

        if (!empty($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        return '';
    }

    private static function extract_output_text($decoded) {
        if (!is_array($decoded)) {
            return '';
        }

        if (!empty($decoded['output_text']) && is_string($decoded['output_text'])) {
            return trim($decoded['output_text']);
        }

        if (empty($decoded['output']) || !is_array($decoded['output'])) {
            return '';
        }

        $chunks = [];

        foreach ($decoded['output'] as $item) {
            if (empty($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content_item) {
                if (!empty($content_item['text']) && is_string($content_item['text'])) {
                    $chunks[] = trim($content_item['text']);
                }
            }
        }

        return trim(implode("\n\n", array_filter($chunks)));
    }
}
