<?php
if (!defined('ABSPATH')) {
    exit;
}

class ACE_Admin {

    const MENU_SLUG = 'ace-social-planner';

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
        register_setting('ace_social_planner', 'ace_openai_key', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
            'default' => '',
        ]);
    }

    public static function sanitize_api_key($value) {
        return trim(sanitize_text_field((string) $value));
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
            [],
            filemtime(plugin_dir_path(__FILE__) . '../admin/app.js'),
            true
        );

        wp_localize_script('ace-social-planner-admin', 'aceSocialPlanner', [
            'apiUrl' => rest_url('ace-social/v1/ai/generate'),
            'nonce' => wp_create_nonce('wp_rest'),
            'hasApiKey' => get_option('ace_openai_key', '') !== '',
        ]);
    }

    public static function render_page() {
        $api_key = (string) get_option('ace_openai_key', '');
        ?>
        <div class="wrap ace-social-planner">
            <h1>ACE Social Planner</h1>
            <p class="ace-social-planner__intro">Generate draft social copy from inside WordPress and keep the API key stored server-side.</p>

            <div class="ace-social-planner__grid">
                <section class="ace-social-planner__panel">
                    <h2>Settings</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('ace_social_planner'); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="ace_openai_key">OpenAI API Key</label></th>
                                <td>
                                    <input
                                        id="ace_openai_key"
                                        name="ace_openai_key"
                                        type="password"
                                        class="regular-text"
                                        value="<?php echo esc_attr($api_key); ?>"
                                        autocomplete="off"
                                    />
                                    <p class="description">Stored in WordPress options and used only for server-side API requests.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button('Save Settings'); ?>
                    </form>
                </section>

                <section class="ace-social-planner__panel">
                    <h2>AI Draft Generator</h2>
                    <label class="screen-reader-text" for="ace-social-planner-content">Source content</label>
                    <textarea id="ace-social-planner-content" rows="10" placeholder="Paste post content, a topic summary, or working notes."></textarea>
                    <div class="ace-social-planner__actions">
                        <button type="button" class="button button-primary" id="ace-social-planner-generate">Generate Draft</button>
                        <span id="ace-social-planner-status" aria-live="polite"></span>
                    </div>
                    <div class="ace-social-planner__output" id="ace-social-planner-output">Generated copy will appear here.</div>
                </section>
            </div>
        </div>
        <?php
    }
}
