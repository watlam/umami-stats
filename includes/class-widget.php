<?php

if (!defined('ABSPATH')) {
    exit;
}

class Umami_Stats_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'umami_stats_widget',
            __('Umami Stats', 'umami-stats'),
            [
                'description' => __('Display Umami statistics in a widget.', 'umami-stats'),
            ]
        );
    }

    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title'] ?? '', $instance, $this->id_base);
        $show_today_visitors = !empty($instance['show_today_visitors']);
        $show_today_visits = !empty($instance['show_today_visits']);
        $show_month_visits = !empty($instance['show_month_visits']);
        $show_total_visits = !empty($instance['show_total_visits']);
        $cache_time = absint($instance['cache_time'] ?? 300);

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $umami = umami_stats();
        $settings = $umami->get_settings();

        if (empty($settings['website_id']) || empty($settings['token'])) {
            echo '<p class="umami-stats-error">' . esc_html__('Please configure Umami settings.', 'umami-stats') . '</p>';
            echo $args['after_widget'];
            return;
        }

        $stats = $umami->get_cached_stats($cache_time);

        if (is_wp_error($stats)) {
            echo '<p class="umami-stats-error">' . esc_html__('Unable to fetch statistics.', 'umami-stats') . '</p>';
            echo $args['after_widget'];
            return;
        }

        wp_enqueue_style('umami-stats');

        echo '<div class="umami-widget-content">';

        if ($show_today_visitors) {
            echo $this->render_stat('todayVisitors', __('今日访客', 'umami-stats'), $stats);
        }

        if ($show_today_visits) {
            echo $this->render_stat('todayVisits', __('今日访问', 'umami-stats'), $stats);
        }

        if ($show_month_visits) {
            echo $this->render_stat('monthVisits', __('本月浏览', 'umami-stats'), $stats);
        }

        if ($show_total_visits) {
            echo $this->render_stat('totalVisits', __('总浏览量', 'umami-stats'), $stats);
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    private function render_stat($key, $label, $stats) {
        return sprintf(
            '<div class="umami-widget-stat"><span class="umami-widget-label">%s</span><span class="umami-widget-value">%s</span></div>',
            esc_html($label),
            esc_html(number_format($stats[$key] ?? 0))
        );
    }

    public function form($instance) {
        $title = $instance['title'] ?? __('网站统计', 'umami-stats');
        $show_today_visitors = !empty($instance['show_today_visitors']);
        $show_today_visits = !empty($instance['show_today_visits']);
        $show_month_visits = !empty($instance['show_month_visits']);
        $show_total_visits = !empty($instance['show_total_visits']);
        $cache_time = absint($instance['cache_time'] ?? 300);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'umami-stats'); ?>
            </label>
            <input type="text" class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label><?php esc_html_e('Display:', 'umami-stats'); ?></label><br>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_today_visitors')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_today_visitors')); ?>" 
                   <?php checked($show_today_visitors); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_today_visitors')); ?>">
                <?php esc_html_e('今日访客', 'umami-stats'); ?>
            </label><br>

            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_today_visits')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_today_visits')); ?>" 
                   <?php checked($show_today_visits); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_today_visits')); ?>">
                <?php esc_html_e('今日访问', 'umami-stats'); ?>
            </label><br>

            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_month_visits')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_month_visits')); ?>" 
                   <?php checked($show_month_visits); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_month_visits')); ?>">
                <?php esc_html_e('本月浏览', 'umami-stats'); ?>
            </label><br>

            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_total_visits')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_total_visits')); ?>" 
                   <?php checked($show_total_visits); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_total_visits')); ?>">
                <?php esc_html_e('总浏览量', 'umami-stats'); ?>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('cache_time')); ?>">
                <?php esc_html_e('Cache Time (seconds):', 'umami-stats'); ?>
            </label>
            <input type="number" class="small-text" id="<?php echo esc_attr($this->get_field_id('cache_time')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('cache_time')); ?>" 
                   value="<?php echo esc_attr($cache_time); ?>" min="0" step="60">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['show_today_visitors'] = !empty($new_instance['show_today_visitors']) ? 1 : 0;
        $instance['show_today_visits'] = !empty($new_instance['show_today_visits']) ? 1 : 0;
        $instance['show_month_visits'] = !empty($new_instance['show_month_visits']) ? 1 : 0;
        $instance['show_total_visits'] = !empty($new_instance['show_total_visits']) ? 1 : 0;
        $instance['cache_time'] = absint($new_instance['cache_time'] ?? 300);
        return $instance;
    }
}

function umami_stats_register_widget() {
    register_widget('Umami_Stats_Widget');
}
add_action('widgets_init', 'umami_stats_register_widget');
