jQuery(document).ready(function($) {
    $('#umami-refresh-btn').on('click', function() {
        var $btn = $(this);
        var $lastUpdate = $('#umami-last-update');
        var $cards = $('.umami-stat-card');
        
        // 显示加载状态
        $btn.addClass('loading').prop('disabled', true);
        $cards.addClass('refreshing');
        
        $.ajax({
            url: umamiStatsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'umami_refresh_stats',
                nonce: umamiStatsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    
                    // 更新统计数据
                    $('.umami-stat-value').each(function() {
                        var statKey = $(this).data('stat');
                        if (stats[statKey] !== undefined) {
                            $(this).text(formatNumber(stats[statKey]));
                        }
                    });
                    
                    // 更新时间
                    if (stats.updatedAt) {
                        var date = new Date(stats.updatedAt * 1000);
                        $lastUpdate.html(umamiStatsL10n.lastUpdate + ' ' + formatDateTime(date));
                    }
                    
                    // 显示成功提示
                    showNotice('success', umamiStatsL10n.refreshSuccess);
                } else {
                    showNotice('error', response.data.message || umamiStatsL10n.refreshError);
                }
            },
            error: function() {
                showNotice('error', umamiStatsL10n.connectionError);
            },
            complete: function() {
                $btn.removeClass('loading').prop('disabled', false);
                $cards.removeClass('refreshing');
            }
        });
    });
    
    // 格式化数字
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // 格式化日期时间
    function formatDateTime(date) {
        var year = date.getFullYear();
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var day = ('0' + date.getDate()).slice(-2);
        var hours = ('0' + date.getHours()).slice(-2);
        var minutes = ('0' + date.getMinutes()).slice(-2);
        return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
    }
    
    // 显示通知
    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
});

// 本地化字符串
var umamiStatsL10n = {
    lastUpdate: '最后更新：',
    refreshSuccess: '统计数据已刷新！',
    refreshError: '刷新失败，请检查设置。',
    connectionError: '连接失败，请稍后重试。'
};
