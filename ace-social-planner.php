<?php
/**
 * Plugin Name: ACE Social Planner
 * Description: AI-powered social scheduling plugin.
 * Version: 0.1.0
 * Author: AceMedia
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-ai.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';

class ACE_Social_Planner {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        ACE_API::register_routes();
    }
}

new ACE_Social_Planner();
