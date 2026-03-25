<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_Provider_Facebook {

    const OPTION_CONNECTION = 'ace_social_provider_facebook_connection';
    const TRANSIENT_PREFIX = 'ace_social_facebook_oauth_';
    const ERROR_OPTION_PREFIX = 'ace_social_facebook_error_';
    const SUCCESS_OPTION_PREFIX = 'ace_social_facebook_success_';
    const GRAPH_BASE = 'https://graph.facebook.com/v23.0';
    const SCOPES = 'pages_show_list,pages_read_engagement,pages_manage_posts';

    public static function get_callback_url() {
        return admin_url('admin.php?page=' . ACE_Admin::MENU_SLUG . '&provider=facebook');
    }

    public static function get_connection() {
        $connection = get_option(self::OPTION_CONNECTION, []);

        return is_array($connection) ? $connection : [];
    }

    public static function get_connection_status() {
        $settings = ACE_Admin::get_settings();
        $network = $settings['networks']['facebook'];
        $connection = self::get_connection();

        $is_configured = !empty($network['app_id']) && !empty($network['app_secret']);
        $is_connected = !empty($connection['user_access_token']) && !empty($connection['user_id']);
        $pages = self::public_pages(isset($connection['pages']) && is_array($connection['pages']) ? $connection['pages'] : []);
        $selected_page_id = isset($connection['selected_page_id']) ? (string) $connection['selected_page_id'] : '';
        $selected_page_name = isset($connection['selected_page_name']) ? (string) $connection['selected_page_name'] : '';

        return [
            'configured' => $is_configured,
            'connected' => $is_connected,
            'status' => $is_connected
                ? ($selected_page_id !== '' ? 'Connected and page selected' : 'Connected, select a page')
                : ($is_configured ? 'Ready to connect' : 'Missing app ID or app secret'),
            'name' => isset($connection['name']) ? (string) $connection['name'] : '',
            'user_id' => isset($connection['user_id']) ? (string) $connection['user_id'] : '',
            'connected_at' => isset($connection['connected_at']) ? (string) $connection['connected_at'] : '',
            'callback_url' => self::get_callback_url(),
            'scopes' => self::SCOPES,
            'pages' => $pages,
            'selected_page_id' => $selected_page_id,
            'selected_page_name' => $selected_page_name,
            'profile_posting_supported' => false,
        ];
    }

    public static function get_authorize_url($user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        $settings = ACE_Admin::get_settings();
        $app_id = trim((string) ($settings['networks']['facebook']['app_id'] ?? ''));
        $app_secret = trim((string) ($settings['networks']['facebook']['app_secret'] ?? ''));

        if ($app_id === '' || $app_secret === '') {
            return new WP_Error(
                'ace_facebook_missing_app_credentials',
                'Add both Facebook App ID and App Secret before starting the connection flow.',
                ['status' => 400]
            );
        }

        $state = wp_generate_password(24, false, false);

        set_transient(self::TRANSIENT_PREFIX . $user_id, [
            'state' => $state,
            'created_at' => time(),
        ], 15 * MINUTE_IN_SECONDS);

        $query = [
            'client_id' => $app_id,
            'redirect_uri' => self::get_callback_url(),
            'state' => $state,
            'scope' => self::SCOPES,
            'response_type' => 'code',
        ];

        return self::GRAPH_BASE . '/dialog/oauth?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function handle_callback() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== ACE_Admin::MENU_SLUG) {
            return;
        }

        if (!isset($_GET['provider']) || $_GET['provider'] !== 'facebook') {
            return;
        }

        if (isset($_GET['ace_facebook_connected']) || isset($_GET['ace_facebook_error_notice'])) {
            return;
        }

        $user_id = get_current_user_id();

        if (!empty($_GET['error'])) {
            self::set_notice('error', sanitize_text_field(wp_unslash($_GET['error_description'] ?? $_GET['error'])));
            wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_error_notice' => 1]));
            exit;
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

        if ($code === '' || $state === '') {
            return;
        }

        $oauth_state = get_transient(self::TRANSIENT_PREFIX . $user_id);

        if (!is_array($oauth_state) || empty($oauth_state['state']) || !hash_equals((string) $oauth_state['state'], $state)) {
            self::set_notice('error', 'The Facebook connection state could not be verified. Start the connection flow again.');
            wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_error_notice' => 1]));
            exit;
        }

        delete_transient(self::TRANSIENT_PREFIX . $user_id);

        $token_result = self::exchange_code_for_token($code);

        if (is_wp_error($token_result)) {
            self::set_notice('error', $token_result->get_error_message());
            wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_error_notice' => 1]));
            exit;
        }

        $user_profile = self::fetch_user_profile((string) $token_result['access_token']);

        if (is_wp_error($user_profile)) {
            self::set_notice('error', $user_profile->get_error_message());
            wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_error_notice' => 1]));
            exit;
        }

        $pages = self::fetch_pages((string) $token_result['access_token']);

        if (is_wp_error($pages)) {
            self::set_notice('error', $pages->get_error_message());
            wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_error_notice' => 1]));
            exit;
        }

        $selected_page = !empty($pages) ? $pages[0] : null;

        update_option(self::OPTION_CONNECTION, [
            'user_id' => (string) ($user_profile['id'] ?? ''),
            'name' => (string) ($user_profile['name'] ?? ''),
            'user_access_token' => (string) $token_result['access_token'],
            'token_type' => isset($token_result['token_type']) ? (string) $token_result['token_type'] : 'bearer',
            'expires_in' => isset($token_result['expires_in']) ? (int) $token_result['expires_in'] : 0,
            'connected_at' => gmdate('c'),
            'pages' => $pages,
            'selected_page_id' => $selected_page ? (string) $selected_page['id'] : '',
            'selected_page_name' => $selected_page ? (string) $selected_page['name'] : '',
        ], false);

        self::set_notice('success', 'Connected Facebook account ' . (string) ($user_profile['name'] ?? '') . '.');
        wp_safe_redirect(self::get_admin_redirect_url(['ace_facebook_connected' => 1]));
        exit;
    }

    public static function disconnect() {
        delete_option(self::OPTION_CONNECTION);

        return [
            'disconnected' => true,
            'connection' => self::get_connection_status(),
        ];
    }

    public static function get_pages() {
        $connection = self::get_connection();
        $pages = self::public_pages(isset($connection['pages']) && is_array($connection['pages']) ? $connection['pages'] : []);

        return [
            'pages' => $pages,
            'selectedPageId' => isset($connection['selected_page_id']) ? (string) $connection['selected_page_id'] : '',
        ];
    }

    public static function select_page($page_id) {
        $page_id = sanitize_text_field((string) $page_id);

        if ($page_id === '') {
            return new WP_Error('ace_facebook_page_required', 'Choose a Facebook Page before saving.', ['status' => 400]);
        }

        $connection = self::get_connection();
        $pages = isset($connection['pages']) && is_array($connection['pages']) ? array_values($connection['pages']) : [];
        $selected_page = null;

        foreach ($pages as $page) {
            if ((string) ($page['id'] ?? '') === $page_id) {
                $selected_page = $page;
                break;
            }
        }

        if (!$selected_page) {
            return new WP_Error('ace_facebook_page_not_found', 'That Facebook Page is not available for this connection.', ['status' => 404]);
        }

        $connection['selected_page_id'] = (string) ($selected_page['id'] ?? '');
        $connection['selected_page_name'] = (string) ($selected_page['name'] ?? '');

        update_option(self::OPTION_CONNECTION, $connection, false);

        return [
            'selectedPageId' => $connection['selected_page_id'],
            'selectedPageName' => $connection['selected_page_name'],
            'connection' => self::get_connection_status(),
        ];
    }

    public static function publish_test($message, $link = '') {
        $message = sanitize_textarea_field((string) $message);
        $link = esc_url_raw((string) $link);

        if ($message === '') {
            return new WP_Error('ace_facebook_missing_message', 'Add a message before testing a Facebook post.', ['status' => 400]);
        }

        $connection = self::get_connection();
        $selected_page_id = isset($connection['selected_page_id']) ? (string) $connection['selected_page_id'] : '';

        if ($selected_page_id === '') {
            return new WP_Error('ace_facebook_missing_page', 'Connect Facebook and choose a Page before publishing.', ['status' => 400]);
        }

        $pages = isset($connection['pages']) && is_array($connection['pages']) ? array_values($connection['pages']) : [];
        $selected_page = null;

        foreach ($pages as $page) {
            if ((string) ($page['id'] ?? '') === $selected_page_id) {
                $selected_page = $page;
                break;
            }
        }

        if (!$selected_page || empty($selected_page['access_token'])) {
            return new WP_Error('ace_facebook_missing_page_token', 'The selected Facebook Page token is missing. Reconnect Facebook and select the page again.', ['status' => 400]);
        }

        $body = [
            'message' => $message,
            'access_token' => (string) $selected_page['access_token'],
        ];

        if ($link !== '') {
            $body['link'] = $link;
        }

        $response = wp_remote_post(self::GRAPH_BASE . '/' . rawurlencode($selected_page_id) . '/feed', [
            'timeout' => 30,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_facebook_publish_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || empty($decoded['id'])) {
            $message = is_array($decoded) && !empty($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Facebook did not accept the post.';

            return new WP_Error('ace_facebook_publish_rejected', $message, ['status' => 502]);
        }

        return [
            'published' => true,
            'postId' => (string) $decoded['id'],
            'pageId' => $selected_page_id,
            'pageName' => isset($selected_page['name']) ? (string) $selected_page['name'] : '',
        ];
    }

    private static function exchange_code_for_token($code) {
        $settings = ACE_Admin::get_settings();
        $app_id = trim((string) ($settings['networks']['facebook']['app_id'] ?? ''));
        $app_secret = trim((string) ($settings['networks']['facebook']['app_secret'] ?? ''));

        if ($app_id === '' || $app_secret === '') {
            return new WP_Error('ace_facebook_missing_app_credentials', 'Facebook app credentials are missing.', ['status' => 400]);
        }

        $query = [
            'client_id' => $app_id,
            'redirect_uri' => self::get_callback_url(),
            'client_secret' => $app_secret,
            'code' => $code,
        ];

        $response = wp_remote_get(self::GRAPH_BASE . '/oauth/access_token?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_facebook_token_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
            $message = is_array($decoded) && !empty($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Facebook did not return a usable access token.';

            return new WP_Error('ace_facebook_token_exchange_failed', $message, ['status' => 502]);
        }

        return $decoded;
    }

    private static function fetch_user_profile($access_token) {
        $query = [
            'fields' => 'id,name',
            'access_token' => $access_token,
        ];

        $response = wp_remote_get(self::GRAPH_BASE . '/me?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), [
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_facebook_profile_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || empty($decoded['id'])) {
            $message = is_array($decoded) && !empty($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Facebook did not return account details.';

            return new WP_Error('ace_facebook_profile_lookup_failed', $message, ['status' => 502]);
        }

        return $decoded;
    }

    private static function fetch_pages($access_token) {
        $query = [
            'fields' => 'id,name,access_token,category',
            'access_token' => $access_token,
        ];

        $response = wp_remote_get(self::GRAPH_BASE . '/me/accounts?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), [
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_facebook_pages_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || !is_array($decoded)) {
            $message = is_array($decoded) && !empty($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Facebook did not return page data.';

            return new WP_Error('ace_facebook_pages_lookup_failed', $message, ['status' => 502]);
        }

        $pages = [];
        $items = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

        foreach ($items as $item) {
            if (empty($item['id']) || empty($item['name']) || empty($item['access_token'])) {
                continue;
            }

            $pages[] = [
                'id' => sanitize_text_field((string) $item['id']),
                'name' => sanitize_text_field((string) $item['name']),
                'category' => isset($item['category']) ? sanitize_text_field((string) $item['category']) : '',
                'access_token' => sanitize_text_field((string) $item['access_token']),
            ];
        }

        return $pages;
    }

    private static function get_admin_redirect_url($args = []) {
        return add_query_arg($args, admin_url('admin.php?page=' . ACE_Admin::MENU_SLUG . '&provider=facebook'));
    }

    private static function set_notice($type, $message) {
        $user_id = get_current_user_id();
        $option_name = $type === 'success' ? self::SUCCESS_OPTION_PREFIX . $user_id : self::ERROR_OPTION_PREFIX . $user_id;
        update_option($option_name, sanitize_text_field($message), false);
    }

    public static function pop_notice($type) {
        $user_id = get_current_user_id();
        $option_name = $type === 'success' ? self::SUCCESS_OPTION_PREFIX . $user_id : self::ERROR_OPTION_PREFIX . $user_id;
        $message = get_option($option_name, '');

        if ($message !== '') {
            delete_option($option_name);
        }

        return $message;
    }

    private static function public_pages($pages) {
        $pages = is_array($pages) ? array_values($pages) : [];

        return array_values(array_map(static function ($page) {
            return [
                'id' => sanitize_text_field((string) ($page['id'] ?? '')),
                'name' => sanitize_text_field((string) ($page['name'] ?? '')),
                'category' => sanitize_text_field((string) ($page['category'] ?? '')),
            ];
        }, array_filter($pages, static function ($page) {
            return is_array($page) && !empty($page['id']) && !empty($page['name']);
        })));
    }
}