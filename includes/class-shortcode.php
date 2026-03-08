<?php

if (!defined('ABSPATH')) {
    exit;
}

class Umami_Stats_Shortcode {

    public function __construct() {
        add_shortcode('umami_stats', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'show' => 'all',
            'format' => 'grid',
            'cache' => 300,
        ], $atts, 'umami_stats');

        $umami = umami_stats();
        $settings = $umami->get_settings();

        if (empty($settings['website_id']) || empty($settings['token'])) {
            return '<p class="umami-stats-error">' . esc_html__('Umami Stats: Please configure settings first.', 'umami-stats') . '</p>';
        }

        $stats = $umami->get_cached_stats(absint($atts['cache']));

        if (is_wp_error($stats)) {
            return '<p class="umami-stats-error">' . esc_html__('Umami Stats: Unable to fetch statistics.', 'umami-stats') . '</p>';
        }

        wp_enqueue_style('umami-stats');

        $show = explode(',', $atts['show']);
        $format = sanitize_text_field($atts['format']);

        ob_start();

        if ($format === 'inline') {
            echo '<span class="umami-stats-inline">';
            foreach ($show as $item) {
                $item = trim($item);
                echo $this->render_stat_item($item, $stats, true);
            }
            echo '</span>';
        } else {
            echo '<div class="umami-stats-widget">';
            echo '<div class="umami-stats-grid umami-stats-grid-' . esc_attr($format) . '">';
            
            foreach ($show as $item) {
                $item = trim($item);
                if ($item === 'all' || in_array($item, ['todayVisitors', 'todayVisits', 'monthVisits', 'totalVisits'])) {
                    if ($item === 'all') {
                        echo $this->render_stat_card('todayVisitors', $stats);
                        echo $this->render_stat_card('todayVisits', $stats);
                        echo $this->render_stat_card('monthVisits', $stats);
                        echo $this->render_stat_card('totalVisits', $stats);
                    } else {
                        echo $this->render_stat_card($item, $stats);
                    }
                }
            }
            
            echo '</div>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    private function render_stat_card($key, $stats) {
        $labels = [
            'todayVisitors' => __('今日访客', 'umami-stats'),
            'todayVisits' => __('今日访问', 'umami-stats'),
            'monthVisits' => __('本月浏览', 'umami-stats'),
            'totalVisits' => __('总浏览量', 'umami-stats'),
        ];

        $icons = [
            'todayVisitors' => 'users',
            'todayVisits' => 'eye',
            'monthVisits' => 'calendar',
            'totalVisits' => 'chart-line',
        ];

        $label = $labels[$key] ?? $key;
        $icon = $icons[$key] ?? 'chart-bar';
        $value = number_format($stats[$key] ?? 0);

        ob_start();
        ?>
        <div class="umami-stat-card umami-stat-<?php echo esc_attr($key); ?>">
            <span class="umami-stat-icon dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
            <div class="umami-stat-content">
                <span class="umami-stat-label"><?php echo esc_html($label); ?></span>
                <span class="umami-stat-value"><?php echo esc_html($value); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_stat_item($key, $stats, $inline = false) {
        $labels = [
            'todayVisitors' => __('今日访客', 'umami-stats'),
            'todayVisits' => __('今日访问', 'umami-stats'),
            'monthVisits' => __('本月浏览', 'umami-stats'),
            'totalVisits' => __('总浏览量', 'umami-stats'),
        ];

        if (!isset($labels[$key])) {
            return '';
        }

        $value = number_format($stats[$key] ?? 0);

        if ($inline) {
            return sprintf(
                '<span class="umami-stat-item"><span class="umami-stat-label">%s:</span> <span class="umami-stat-value">%s</span></span>',
                esc_html($labels[$key]),
                esc_html($value)
            );
        }

        return sprintf(
            '<div class="umami-stat-item"><span class="umami-stat-label">%s</span> <span class="umami-stat-value">%s</span></div>',
            esc_html($labels[$key]),
            esc_html($value)
        );
    }
}
