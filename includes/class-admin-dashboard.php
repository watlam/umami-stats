<?php

if (!defined('ABSPATH')) {
    exit;
}

class Umami_Stats_Admin_Dashboard {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 9);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_umami_refresh_stats', [$this, 'ajax_refresh_stats']);
    }

    public function add_admin_menu() {
        // 仪表盘子菜单 - 显示在设置之前
        add_submenu_page(
            'umami-stats',
            '仪表盘',
            '仪表盘',
            'manage_options',
            'umami-stats',
            [$this, 'render_dashboard_page'],
            1
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_umami-stats' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'umami-stats-admin-dashboard',
            UMAMI_STATS_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            [],
            UMAMI_STATS_VERSION
        );

        wp_enqueue_script(
            'umami-stats-admin-dashboard',
            UMAMI_STATS_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            [],
            UMAMI_STATS_VERSION,
            true
        );

        wp_localize_script('umami-stats-admin-dashboard', 'umamiStatsData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('umami_stats_nonce'),
            'restUrl' => rest_url('umami/v1/stats')
        ]);
    }

    public function ajax_refresh_stats() {
        check_ajax_referer('umami_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '权限不足']);
        }

        $umami = umami_stats();
        $settings = $umami->get_settings();

        if (empty($settings['website_id']) || empty($settings['token'])) {
            wp_send_json_error(['message' => '请先配置 Umami 设置']);
        }

        $stats = $umami->get_stats();

        if (is_wp_error($stats)) {
            wp_send_json_error(['message' => $stats->get_error_message()]);
        }

        $cache_time = isset($settings['cache_time']) ? absint($settings['cache_time']) : 300;
        set_transient($umami->get_cache_key(), $stats, $cache_time);

        wp_send_json_success($stats);
    }

    public function render_dashboard_page() {
        $umami = umami_stats();
        $settings = $umami->get_settings();
        $stats = $umami->get_cached_stats();
        ?>
        <div class="wrap">
            <h1>Umami 统计仪表盘</h1>

        <?php if (is_wp_error($stats)): ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html($stats->get_error_message()); ?></p>
            </div>
        <?php elseif (empty($settings['website_id']) || empty($settings['token'])): ?>
            <div class="notice notice-info">
                <p>请先配置 Umami 设置。
                <a href="<?php echo admin_url('admin.php?page=umami-stats-settings'); ?>">前往设置</a></p>
            </div>
        <?php else: ?>
            <div class="umami-dashboard-actions">
                <button type="button" class="button button-primary" id="umami-refresh-btn">
                    <span class="dashicons dashicons-update"></span>
                    刷新统计
                </button>
                <span id="umami-last-update">
                    最后更新：
                    <?php echo isset($stats['updatedAt']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $stats['updatedAt']) : '从未'; ?>
                </span>
            </div>

            <div class="umami-stats-grid">
                <div class="umami-stat-card umami-stat-card-today">
                    <div class="umami-stat-icon">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="umami-stat-content">
                        <h3>今日访客</h3>
                        <div class="umami-stat-value" data-stat="todayVisitors">
                            <?php echo esc_html(number_format($stats['todayVisitors'] ?? 0)); ?>
                        </div>
                    </div>
                </div>

                <div class="umami-stat-card umami-stat-card-visits">
                    <div class="umami-stat-icon">
                        <span class="dashicons dashicons-visibility"></span>
                    </div>
                    <div class="umami-stat-content">
                        <h3>今日访问</h3>
                        <div class="umami-stat-value" data-stat="todayVisits">
                            <?php echo esc_html(number_format($stats['todayVisits'] ?? 0)); ?>
                        </div>
                    </div>
                </div>

                <div class="umami-stat-card umami-stat-card-month">
                    <div class="umami-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="umami-stat-content">
                        <h3>本月浏览</h3>
                        <div class="umami-stat-value" data-stat="monthVisits">
                            <?php echo esc_html(number_format($stats['monthVisits'] ?? 0)); ?>
                        </div>
                    </div>
                </div>

                <div class="umami-stat-card umami-stat-card-total">
                    <div class="umami-stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="umami-stat-content">
                        <h3>总浏览量</h3>
                        <div class="umami-stat-value" data-stat="totalVisits">
                            <?php echo esc_html(number_format($stats['totalVisits'] ?? 0)); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="umami-dashboard-links">
                <h2>快速链接</h2>
                <div class="umami-links-grid">
                    <a href="<?php echo esc_url($settings['umami_url']); ?>" target="_blank" class="umami-link-card">
                        <span class="dashicons dashicons-external"></span>
                        打开 Umami 控制台
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=umami-stats-settings'); ?>" class="umami-link-card">
                        <span class="dashicons dashicons-admin-generic"></span>
                        插件设置
                    </a>
                    <a href="<?php echo rest_url('umami/v1/stats'); ?>" target="_blank" class="umami-link-card">
                        <span class="dashicons dashicons-rest-api"></span>
                        查看 API 端点
                    </a>
                </div>
            </div>
        <?php endif; ?>
        </div>
        <?php
    }
}
