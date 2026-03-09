<?php

if (!defined('ABSPATH')) {
    exit;
}

class Umami_Stats_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Umami Stats',
            'Umami Stats',
            'manage_options',
            'umami-stats',
            [$this, 'render_settings_page'],
            'dashicons-chart-line',
            30
        );

        add_submenu_page(
            'umami-stats',
            '设置',
            '设置',
            'manage_options',
            'umami-stats-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('umami_stats_settings_group', 'umami_stats_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);

        add_settings_section(
            'umami_stats_main_section',
            'Umami API 配置',
            null,
            'umami_stats_settings_page'
        );

        add_settings_field(
            'umami_url',
            'Umami 实例地址',
            [$this, 'render_umami_url_field'],
            'umami_stats_settings_page',
            'umami_stats_main_section'
        );

        add_settings_field(
            'website_id',
            '网站 ID',
            [$this, 'render_website_id_field'],
            'umami_stats_settings_page',
            'umami_stats_main_section'
        );

        add_settings_field(
            'token',
            'API Token',
            [$this, 'render_token_field'],
            'umami_stats_settings_page',
            'umami_stats_main_section'
        );

        add_settings_field(
            'timezone',
            '时区',
            [$this, 'render_timezone_field'],
            'umami_stats_settings_page',
            'umami_stats_main_section'
        );

        add_settings_field(
            'cache_time',
            '缓存时间（秒）',
            [$this, 'render_cache_time_field'],
            'umami_stats_settings_page',
            'umami_stats_main_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];

        if (isset($input['umami_url'])) {
            $sanitized['umami_url'] = esc_url_raw($input['umami_url']);
        }

        if (isset($input['website_id'])) {
            $sanitized['website_id'] = sanitize_text_field($input['website_id']);
        }

        if (isset($input['token'])) {
            $sanitized['token'] = sanitize_text_field($input['token']);
        }

        if (isset($input['timezone'])) {
            $sanitized['timezone'] = sanitize_text_field($input['timezone']);
        }

        if (isset($input['cache_time'])) {
            $sanitized['cache_time'] = absint($input['cache_time']);
        }

        return $sanitized;
    }

    public function render_umami_url_field() {
        $settings = get_option('umami_stats_settings');
        $value = isset($settings['umami_url']) ? $settings['umami_url'] : '';
        ?>
        <input type="url"
            name="umami_stats_settings[umami_url]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="https://umami.example.com">
        <p class="description">输入你的 Umami 实例地址，例如：https://umami.example.com</p>
        <?php
    }

    public function render_website_id_field() {
        $settings = get_option('umami_stats_settings');
        $value = isset($settings['website_id']) ? $settings['website_id'] : '';
        ?>
        <input type="text"
            name="umami_stats_settings[website_id]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="your-website-id">
        <p class="description">网站ID可在 Umami 后台网址中获取</p>
        <?php
    }

    public function render_token_field() {
        $settings = get_option('umami_stats_settings');
        $value = isset($settings['token']) ? $settings['token'] : '';
        ?>
        <input type="password"
            name="umami_stats_settings[token]"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="your-api-token">
        <p class="description">打开 Umami 后台，按 F12 查看 Network 请求中的 Authorization 头</p>
        <?php
    }

    public function render_timezone_field() {
        $settings = get_option('umami_stats_settings');
        $current_tz = isset($settings['timezone']) ? $settings['timezone'] : 'Asia/Shanghai';
        $timezones = timezone_identifiers_list();
        ?>
        <select name="umami_stats_settings[timezone]" class="regular-text">
        <?php foreach ($timezones as $tz): ?>
            <option value="<?php echo esc_attr($tz); ?>" <?php selected($tz, $current_tz); ?>>
                <?php echo esc_html($tz); ?>
            </option>
        <?php endforeach; ?>
        </select>
        <p class="description">选择服务器所在时区</p>
        <?php
    }

    public function render_cache_time_field() {
        $settings = get_option('umami_stats_settings');
        $value = isset($settings['cache_time']) ? $settings['cache_time'] : 300;
        ?>
        <input type="number"
            name="umami_stats_settings[cache_time]"
            value="<?php echo esc_attr($value); ?>"
            class="small-text"
            min="0"
            step="10">
        <p class="description">统计数据缓存时间，默认 300 秒</p>
        <?php
    }

    public function render_settings_page() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>设置已保存！</p></div>';
        }

        $test_result = '';
        if (isset($_POST['umami_test_connection']) && check_admin_referer('umami_test_connection')) {
            $umami = umami_stats();
            $stats = $umami->get_stats();
            if (is_wp_error($stats)) {
                $test_result = '<div class="notice notice-error is-dismissible"><p>连接失败，请检查设置是否正确。</p></div>';
            } else {
                $test_result = '<div class="notice notice-success is-dismissible"><p>连接成功！统计数据已更新。</p></div>';
            }
        }
        
        // 获取真实统计数据用于预览
        $umami = umami_stats();
        $real_stats = $umami->get_cached_stats();
        $stats_json = json_encode([
            'todayVisitors' => is_wp_error($real_stats) ? 0 : ($real_stats['todayVisitors'] ?? 0),
            'todayVisits' => is_wp_error($real_stats) ? 0 : ($real_stats['todayVisits'] ?? 0),
            'monthVisits' => is_wp_error($real_stats) ? 0 : ($real_stats['monthVisits'] ?? 0),
            'totalVisits' => is_wp_error($real_stats) ? 0 : ($real_stats['totalVisits'] ?? 0),
        ]);
        ?>
        <style>
        .umami-settings-container {
            max-width: 1400px;
        }
        .umami-settings-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dcdcde;
        }
        .umami-settings-header h1 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .umami-settings-header .dashicons {
            color: #2271b1;
        }
        .umami-form-section {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .umami-form-section h2 {
            margin: 0 0 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            font-size: 16px;
            color: #1e1e1e;
        }
        .umami-buttons-row {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        /* 三等分布局 */
        .umami-sections-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 30px;
        }
        @media (max-width: 1200px) {
            .umami-sections-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* 统一卡片样式 */
        .umami-card {
            background: #fff;
            border: 2px solid #e1e4e8;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        .umami-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border-color: #d0d7de;
        }
        .umami-card-header {
            color: #fff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .umami-card-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .umami-card-header .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .umami-card-body {
            padding: 20px;
        }
        
        /* 不同卡片的渐变色 */
        .umami-embed-section .umami-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .umami-preview-section .umami-card-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .umami-api-section .umami-card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        /* 代码编辑器 */
        .umami-code-editor {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 15px;
            position: relative;
        }
        .umami-code-editor textarea {
            width: 100%;
            min-height: 250px;
            height: 250px;
            background: transparent;
            color: #7CFC00;
            border: none;
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            line-height: 1.7;
            resize: vertical;
            outline: none;
            padding: 0;
        }
        .umami-copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .umami-copy-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .umami-copy-btn.copied {
            background: #00a32a;
            border-color: #00a32a;
        }
        
        /* 预览区域 - 减小高度 */
        .umami-preview-box {
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            border-radius: 8px;
            padding: 18px 20px;
            min-height: auto;
        }
        .umami-preview-content {
            font-size: 13px;
            line-height: 1.5;
        }
        .umami-preview-content .umami-stats-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .umami-preview-content .umami-stats-footer span {
            display: inline-block;
            white-space: nowrap;
        }
        
        /* 更新预览按钮间距 */
        .umami-update-preview-btn {
            width: 100%;
            margin-top: 20px;
            justify-content: center;
        }
        .umami-usage-tips {
            margin-top: 20px;
            padding: 15px;
            background: #f6f7f7;
            border-radius: 6px;
            font-size: 13px;
        }
        .umami-usage-tips h4 {
            margin: 0 0 10px;
            font-size: 13px;
        }
        .umami-usage-tips ol {
            margin: 0;
            padding-left: 20px;
        }
        .umami-usage-tips li {
            margin-bottom: 5px;
            color: #646970;
        }
        
        /* API 区域 */
        .umami-api-box {
            background: #f6f7f7;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .umami-api-method {
            display: inline-block;
            background: #2271b1;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 10px;
        }
        .umami-api-url {
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
            font-size: 13px;
            color: #1e1e1e;
            word-break: break-all;
        }
        .umami-api-response {
            background: #1e1e1e;
            border-radius: 6px;
            padding: 15px;
        }
        .umami-api-response h4 {
            color: #fff;
            margin: 0 0 10px;
            font-size: 13px;
        }
        .umami-api-response pre {
            color: #7CFC00;
            margin: 0;
            font-size: 12px;
            line-height: 1.6;
            overflow-x: auto;
        }
        </style>
        
        <div class="wrap umami-settings-container">
            <div class="umami-settings-header">
                <h1>
                    <span class="dashicons dashicons-chart-line"></span>
                    Umami Stats 设置
                </h1>
            </div>
            
            <?php echo $test_result; ?>
            
            <!-- API 配置区域 -->
            <div class="umami-form-section">
                <h2>
                    <span class="dashicons dashicons-admin-generic" style="margin-right: 8px;"></span>
                    API 配置
                </h2>
        <form action="options.php" method="post">
        <?php
            settings_fields('umami_stats_settings_group');
            do_settings_sections('umami_stats_settings_page');
            submit_button('保存设置', 'primary');
        ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('umami_test_connection'); ?>
            <input type="submit" name="umami_test_connection" class="button" value="测试连接">
        </form>
            </div>
            
            <!-- 三等分布局 -->
            <div class="umami-sections-grid">
                <!-- 嵌入代码 -->
                <div class="umami-embed-section">
                    <div class="umami-card">
                        <div class="umami-card-header">
                            <h3>
                                <span class="dashicons dashicons-editor-code"></span>
                                嵌入代码
                            </h3>
                        </div>
                        <div class="umami-card-body">
                            <div class="umami-code-editor">
                                <button type="button" class="umami-copy-btn" onclick="umamiCopyCode()">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <span id="umami-copy-text">复制</span>
                                </button>
                                <textarea id="umami-embed-code" spellcheck="false">&lt;div class="umami-stats-footer"&gt;
  &lt;span&gt;今日访客：&lt;b id="umami-tv"&gt;-&lt;/b&gt;&lt;/span&gt;
  &lt;span&gt;今日访问：&lt;b id="umami-ts"&gt;-&lt;/b&gt;&lt;/span&gt;
  &lt;span&gt;本月访问：&lt;b id="umami-mv"&gt;-&lt;/b&gt;&lt;/span&gt;
  &lt;span&gt;总访问：&lt;b id="umami-av"&gt;-&lt;/b&gt;&lt;/span&gt;
&lt;/div&gt;
&lt;script&gt;
fetch("/wp-json/umami/v1/stats")
  .then(r =&gt; r.json())
  .then(d =&gt; {
    document.getElementById("umami-tv").textContent = d.todayVisitors;
    document.getElementById("umami-ts").textContent = d.todayVisits;
    document.getElementById("umami-mv").textContent = d.monthVisits;
    document.getElementById("umami-av").textContent = d.totalVisits;
  });
&lt;/script&gt;
&lt;style&gt;
.umami-stats-footer { font-size: 13px; color: #8b949e; }
.umami-stats-footer span { margin-right: 12px; }
.umami-stats-footer b { color: #7CFC00; font-weight: 600; }
&lt;/style&gt;</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 预览效果 -->
                <div class="umami-preview-section">
                    <div class="umami-card">
                        <div class="umami-card-header">
                            <h3>
                                <span class="dashicons dashicons-visibility"></span>
                                预览效果
                            </h3>
                        </div>
                        <div class="umami-card-body">
                            <div class="umami-preview-box">
                                <div id="umami-preview-content" class="umami-preview-content">
                                    <!-- 预览内容将由JS填充 -->
                                </div>
                            </div>
                            <button type="button" class="button umami-update-preview-btn" onclick="umamiUpdatePreview()">
                                <span class="dashicons dashicons-update"></span>
                                更新预览
                            </button>
                            <div class="umami-usage-tips">
                                <h4>使用说明：</h4>
                                <ol>
                                    <li>可编辑左侧代码自定义样式</li>
                                    <li>点击「更新预览」查看效果</li>
                                    <li>点击「复制」复制代码</li>
                                    <li>粘贴到小工具或页脚</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- API 端点 -->
                <div class="umami-api-section">
                    <div class="umami-card">
                        <div class="umami-card-header">
                            <h3>
                                <span class="dashicons dashicons-rest-api"></span>
                                API 端点
                            </h3>
                        </div>
                        <div class="umami-card-body">
                            <div class="umami-api-box">
                                <span class="umami-api-method">GET</span>
                                <code class="umami-api-url"><?php echo esc_url(rest_url('umami/v1/stats')); ?></code>
                            </div>
                            <div class="umami-api-response">
                                <h4>示例响应</h4>
                                <pre>{
  "todayVisitors": 13,
  "todayVisits": 15,
  "monthVisits": 52,
  "totalVisits": 52,
  "updatedAt": 1736606400
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // 真实统计数据
        var umamiRealStats = <?php echo $stats_json; ?>;
        
        // 页面加载后初始化预览
        document.addEventListener('DOMContentLoaded', function() {
            umamiUpdatePreview();
        });
        
        // 复制代码
        function umamiCopyCode() {
            var code = document.getElementById('umami-embed-code').value;
            var btn = document.querySelector('.umami-copy-btn');
            var text = document.getElementById('umami-copy-text');
            
            navigator.clipboard.writeText(code).then(function() {
                btn.classList.add('copied');
                text.textContent = '已复制!';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    text.textContent = '复制';
                }, 2000);
            }).catch(function() {
                var textarea = document.getElementById('umami-embed-code');
                textarea.select();
                document.execCommand('copy');
                btn.classList.add('copied');
                text.textContent = '已复制!';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    text.textContent = '复制';
                }, 2000);
            });
        }
        
        // 更新预览
        function umamiUpdatePreview() {
            var code = document.getElementById('umami-embed-code').value;
            var previewContainer = document.getElementById('umami-preview-content');
            
            // 清空预览容器
            previewContainer.innerHTML = '';
            
            // 解析代码
            var parser = new DOMParser();
            var doc = parser.parseFromString(code, 'text/html');
            
            // 提取样式
            var styles = doc.querySelectorAll('style');
            var customStyles = '';
            styles.forEach(function(style) {
                customStyles += style.textContent;
            });
            
            // 创建style元素注入到预览容器
            if (customStyles) {
                var styleEl = document.createElement('style');
                styleEl.textContent = customStyles;
                previewContainer.appendChild(styleEl);
            }
            
            // 获取HTML内容
            var htmlContent = doc.querySelector('.umami-stats-footer') || doc.querySelector('div');
            
            if (htmlContent) {
                // 克隆节点
                var clone = htmlContent.cloneNode(true);
                
                // 移除script标签（保留style用于预览）
                clone.querySelectorAll('script').forEach(function(s) { s.remove(); });
                clone.querySelectorAll('style').forEach(function(s) { s.remove(); });
                
                // 用真实数据替换
                var html = clone.innerHTML;
                html = html.replace(/id="umami-tv"[^>]*>[^<]*/g, 'id="umami-tv">' + umamiRealStats.todayVisitors);
                html = html.replace(/id="umami-ts"[^>]*>[^<]*/g, 'id="umami-ts">' + umamiRealStats.todayVisits);
                html = html.replace(/id="umami-mv"[^>]*>[^<]*/g, 'id="umami-mv">' + umamiRealStats.monthVisits);
                html = html.replace(/id="umami-av"[^>]*>[^<]*/g, 'id="umami-av">' + umamiRealStats.totalVisits);
                
                clone.innerHTML = html;
                
                // 追加到预览容器
                previewContainer.appendChild(clone);
            } else {
                previewContainer.innerHTML = '<div style="color: #8b949e;">无法解析代码，请检查格式</div>';
            }
        }
        
        // 监听代码变化，实时更新预览
        var codeEditor = document.getElementById('umami-embed-code');
        var updateTimeout;
        codeEditor.addEventListener('input', function() {
            clearTimeout(updateTimeout);
            updateTimeout = setTimeout(umamiUpdatePreview, 500);
        });
        </script>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ('umami-stats_page_umami-stats-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'umami-stats-admin',
            UMAMI_STATS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            UMAMI_STATS_VERSION
        );
    }
}
