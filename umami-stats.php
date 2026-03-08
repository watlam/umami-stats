<?php
/*
Plugin Name: Umami Stats
Plugin URI: https://github.com/watlam/umami-stats
Description: 通过 WordPress REST API 端点暴露 Umami v3 统计数据，包含后台设置页面和仪表盘
Version: 1.0.0
Author: 煜轩
Author URI:  https://www.watlam.com/337.html
Text Domain: umami-stats
License: GPL v2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

define('UMAMI_STATS_VERSION', '1.0.0');
define('UMAMI_STATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMAMI_STATS_PLUGIN_URL', plugin_dir_url(__FILE__));

class Umami_Stats {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once UMAMI_STATS_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once UMAMI_STATS_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
        require_once UMAMI_STATS_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once UMAMI_STATS_PLUGIN_DIR . 'includes/class-widget.php';
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_init', [$this, 'check_requirements']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // 初始化后台管理类
        add_action('plugins_loaded', [$this, 'init_admin_classes']);
    }
    
    public function init_admin_classes() {
        // 只在后台加载
        if (is_admin()) {
            Umami_Stats_Admin_Settings::get_instance();
            Umami_Stats_Admin_Dashboard::get_instance();
        }
        
        // 前端短代码始终加载
        new Umami_Stats_Shortcode();
    }

    public function load_textdomain() {
        load_plugin_textdomain('umami-stats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function check_requirements() {
        if (!class_exists('WP_REST_Controller')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . esc_html__('Umami Stats requires WordPress 4.7 or later.', 'umami-stats') . '</p></div>';
            });
        }
    }

    public function register_rest_routes() {
        register_rest_route('umami/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'umami-stats',
            UMAMI_STATS_PLUGIN_URL . 'assets/css/umami-stats.css',
            [],
            UMAMI_STATS_VERSION
        );
    }

    public function get_settings() {
        return get_option('umami_stats_settings', [
            'umami_url' => '',
            'website_id' => '',
            'token' => '',
            'timezone' => 'Asia/Shanghai',
        ]);
    }

    public function get_stats() {
        $settings = $this->get_settings();

        if (empty($settings['website_id']) || empty($settings['token'])) {
            return new WP_Error('missing_config', __('Please configure Umami settings.', 'umami-stats'), ['status' => 400]);
        }

        $umami_api = rtrim($settings['umami_url'], '/') . '/api';
        $website_id = $settings['website_id'];
        $token = $settings['token'];
        $timezone = !empty($settings['timezone']) ? $settings['timezone'] : 'Asia/Shanghai';

        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $e) {
            $tz = new DateTimeZone('Asia/Shanghai');
        }

        $now = new DateTime('now', $tz);
        $nowAt = $now->getTimestamp() * 1000;
        $todayStartAt = (new DateTime('today', $tz))->getTimestamp() * 1000;
        $monthStartAt = (new DateTime('first day of this month 00:00:00', $tz))->getTimestamp() * 1000;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $today = $this->fetch_stats("$umami_api/websites/$website_id/stats?startAt=$todayStartAt&endAt=$nowAt", $headers);
        $month = $this->fetch_stats("$umami_api/websites/$website_id/stats?startAt=$monthStartAt&endAt=$nowAt", $headers);
        $all = $this->fetch_stats("$umami_api/websites/$website_id/stats?startAt=0&endAt=$nowAt", $headers);

        return [
            'todayVisitors' => intval($today['visitors'] ?? 0),
            'todayVisits' => intval($today['visits'] ?? 0),
            'monthVisits' => intval($month['pageviews'] ?? 0),
            'totalVisits' => intval($all['pageviews'] ?? 0),
            'updatedAt' => time(),
        ];
    }

    private function fetch_stats($url, $headers) {
        $response = wp_remote_get($url, ['headers' => $headers, 'timeout' => 15]);

        if (is_wp_error($response)) {
            return ['visitors' => 0, 'visits' => 0, 'pageviews' => 0];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['visitors' => 0, 'visits' => 0, 'pageviews' => 0];
        }

        return $data;
    }

    public function get_cache_key() {
        return 'umami_stats_cache_' . md5($this->get_settings()['website_id'] ?? '');
    }

    public function get_cached_stats($cache_time = 300) {
        $cache = get_transient($this->get_cache_key());

        if (false !== $cache) {
            return $cache;
        }

        $stats = $this->get_stats();

        if (!is_wp_error($stats)) {
            set_transient($this->get_cache_key(), $stats, $cache_time);
        }

        return $stats;
    }
}

function umami_stats() {
    return Umami_Stats::get_instance();
}

umami_stats();
