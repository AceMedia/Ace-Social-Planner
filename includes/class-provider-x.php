<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_Provider_X {

    const OPTION_CONNECTION = 'ace_social_provider_x_connection';
    const TRANSIENT_PREFIX = 'ace_social_x_pkce_';
    const ERROR_OPTION_PREFIX = 'ace_social_x_error_';
    const SUCCESS_OPTION_PREFIX = 'ace_social_x_success_';
    const SCOPES = 'tweet.read users.read tweet.write offline.access';

    public static function get_callback_url() {
        return admin_url('admin.php?page=' . ACE_Admin::MENU_SLUG . '&provider=x');
    }

    public static function get_connection() {
        $connection = get_option(self::OPTION_CONNECTION, []);

        return is_array($connection) ? $connection : [];
    }

    public static function get_connection_status() {
        $settings = ACE_Admin::get_settings();
        $network = $settings['networks']['x'];
        $connection = self::get_connection();
        $is_connected = !empty($connection['access_token']) && !empty($connection['user_id']);

        return [
            'configured' => !empty($network['client_id']),
            'connected' => $is_connected,
            'status' => $is_connected ? 'Connected' : (!empty($network['client_id']) ? 'Ready to connect' : 'Missing client ID'),
            'username' => isset($connection['username']) ? (string) $connection['username'] : '',
            'name' => isset($connection['name']) ? (string) $connection['name'] : '',
            'connected_at' => isset($connection['connected_at']) ? (string) $connection['connected_at'] : '',
            'callback_url' => self::get_callback_url(),
            'scopes' => self::SCOPES,
        ];
    }

    public static function get_authorize_url($user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        $settings = ACE_Admin::get_settings();
        $client_id = trim((string) ($settings['networks']['x']['client_id'] ?? ''));

        if ($client_id === '') {
            return new WP_Error('ace_x_missing_client_id', 'Add the X OAuth Client ID before starting the connection flow.', ['status' => 400]);
        }

        $state = wp_generate_password(24, false, false);
        $verifier = self::generate_code_verifier();
        $challenge = self::generate_code_challenge($verifier);

        set_transient(self::TRANSIENT_PREFIX . $user_id, [
            'state' => $state,
            'code_verifier' => $verifier,
            'created_at' => time(),
        ], 15 * MINUTE_IN_SECONDS);

        $query = [
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => self::get_callback_url(),
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return 'https://twitter.com/i/oauth2/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function handle_callback() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] !== ACE_Admin::MENU_SLUG) {
            return;
        }

        if (!isset($_GET['provider']) || $_GET['provider'] !== 'x') {
            return;
        }

        if (isset($_GET['ace_x_connected']) || isset($_GET['ace_x_error_notice'])) {
            return;
        }

        $user_id = get_current_user_id();

        if (!empty($_GET['error'])) {
            self::set_notice('error', sanitize_text_field(wp_unslash($_GET['error_description'] ?? $_GET['error'])));
            wp_safe_redirect(self::get_admin_redirect_url(['ace_x_error_notice' => 1]));
            exit;
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

        if ($code === '' || $state === '') {
            return;
        }

        $pkce = get_transient(self::TRANSIENT_PREFIX . $user_id);

        if (!is_array($pkce) || empty($pkce['state']) || !hash_equals((string) $pkce['state'], $state)) {
            self::set_notice('error', 'The X connection state could not be verified. Start the connection flow again.');
            wp_safe_redirect(self::get_admin_redirect_url(['ace_x_error_notice' => 1]));
            exit;
        }

        delete_transient(self::TRANSIENT_PREFIX . $user_id);

        $tokens = self::exchange_code_for_token($code, (string) $pkce['code_verifier']);

        if (is_wp_error($tokens)) {
            self::set_notice('error', $tokens->get_error_message());
            wp_safe_redirect(self::get_admin_redirect_url(['ace_x_error_notice' => 1]));
            exit;
        }

        $profile = self::fetch_authenticated_user((string) $tokens['access_token']);

        if (is_wp_error($profile)) {
            self::set_notice('error', $profile->get_error_message());
            wp_safe_redirect(self::get_admin_redirect_url(['ace_x_error_notice' => 1]));
            exit;
        }

        update_option(self::OPTION_CONNECTION, [
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : '',
            'scope' => isset($tokens['scope']) ? (string) $tokens['scope'] : self::SCOPES,
            'token_type' => isset($tokens['token_type']) ? (string) $tokens['token_type'] : 'bearer',
            'user_id' => (string) $profile['id'],
            'username' => (string) $profile['username'],
            'name' => (string) $profile['name'],
            'connected_at' => gmdate('c'),
        ], false);

        self::set_notice('success', 'Connected X account @' . $profile['username'] . '.');
        wp_safe_redirect(self::get_admin_redirect_url(['ace_x_connected' => 1]));
        exit;
    }

    public static function disconnect() {
        delete_option(self::OPTION_CONNECTION);

        return [
            'disconnected' => true,
            'connection' => self::get_connection_status(),
        ];
    }

    private static function exchange_code_for_token($code, $code_verifier) {
        $settings = ACE_Admin::get_settings();
        $client_id = trim((string) ($settings['networks']['x']['client_id'] ?? ''));

        if ($client_id === '') {
            return new WP_Error('ace_x_missing_client_id', 'The X Client ID is missing.', ['status' => 400]);
        }

        $response = wp_remote_post('https://api.x.com/2/oauth2/token', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'redirect_uri' => self::get_callback_url(),
                'code_verifier' => $code_verifier,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_x_token_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
            $message = is_array($decoded) && !empty($decoded['error_description'])
                ? (string) $decoded['error_description']
                : 'X did not return a usable access token.';

            return new WP_Error('ace_x_token_exchange_failed', $message, ['status' => 502]);
        }

        return $decoded;
    }

    private static function fetch_authenticated_user($access_token) {
        $response = wp_remote_get('https://api.x.com/2/users/me?user.fields=name,username', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ace_x_profile_request_failed', $response->get_error_message(), ['status' => 502]);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || empty($decoded['data']['id'])) {
            $message = is_array($decoded) && !empty($decoded['title'])
                ? (string) $decoded['title']
                : 'X did not return account details after approval.';

            return new WP_Error('ace_x_profile_lookup_failed', $message, ['status' => 502]);
        }

        return $decoded['data'];
    }

    private static function generate_code_verifier() {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private static function generate_code_challenge($verifier) {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private static function get_admin_redirect_url($args = []) {
        return add_query_arg($args, admin_url('admin.php?page=' . ACE_Admin::MENU_SLUG . '&provider=x'));
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
}
