jQuery(document).ready(function($) {
    // Existing code for activity log and progress
    function refreshActivityLog() {
        $.ajax({
            url: mkwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_refresh_activity',
                nonce: mkwaAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.mkwa-activity-log').html(response.data.html);
                }
            }
        });
    }

    function refreshProgress() {
        $.ajax({
            url: mkwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_refresh_progress',
                nonce: mkwaAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.mkwa-progress-tracker').html(response.data.html);
                    updateDashboardHeader(response.data.stats);
                }
            }
        });
    }

    function updateDashboardHeader(stats) {
        $('.mkwa-level').text('Level ' + stats.current_level);
        $('.mkwa-points').text(stats.total_points + ' points');
    }

    function logActivity(activityType) {
        $.ajax({
            url: mkwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_log_activity',
                nonce: mkwaAjax.nonce,
                activity_type: activityType
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    refreshActivityLog();
                    refreshProgress();
                } else {
                    showNotification('error', response.data.message);
                }
            }
        });
    }

    // New code for class management
    function refreshClassList() {
        $.ajax({
            url: mkwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_refresh_classes',
                nonce: mkwaAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.mkwa-classes-grid').html(response.data.html);
                }
            }
        });
    }

    // Class filter handling
    $('.mkwa-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        $('.mkwa-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        if (filter === 'all') {
            $('.mkwa-class-card').show();
        } else {
            $('.mkwa-class-card').hide();
            $('.mkwa-class-card.' + filter).show();
        }
    });

    // Class registration
    $('.mkwa-btn-register').on('click', function() {
        const classId = $(this).data('class-id');
        const $button = $(this);
        const $card = $button.closest('.mkwa-class-card');

        $.ajax({
            url: mkwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mkwa_register_class',
                nonce: mkwaAjax.nonce,
                class_id: classId
            },
            beforeSend: function() {
                $button.prop('disabled', true).text(mkwaStrings.registering);
            },
            success: function(response) {
                if (response.success) {
                    $card.removeClass('available').addClass('registered');
                    $button.replaceWith(`
                        <button class="mkwa-btn mkwa-btn-cancel" data-class-id="${classId}">
                            ${mkwaStrings.cancelRegistration}
                        </button>
                    `);
                    $card.find('.mkwa-class-capacity').text(response.data.spots_left);
                    showNotification('success', response.data.message);
                    refreshProgress();
                } else {
                    showNotification('error', response.data.message);
                    $button.prop('disabled', false).text(mkwaStrings.register);
                }
            },
            error: function() {
                showNotification('error', mkwaStrings.errorOccurred);
                $button.prop('disabled', false).text(mkwaStrings.register);
            }
        });
    });

    // Class cancellation
    $(document).on('click', '.mkwa-btn-cancel', function() {
        const classId = $(this).data('class-id');
        const $button = $(this);
        const $card = $button.closest('.mkwa-class-card');

        if (confirm(mkwaStrings.confirmCancel)) {
            $.ajax({
                url: mkwaAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mkwa_cancel_class',
                    nonce: mkwaAjax.nonce,
                    class_id: classId
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text(mkwaStrings.cancelling);
                },
                success: function(response) {
                    if (response.success) {
                        $card.removeClass('registered').addClass('available');
                        $button.replaceWith(`
                            <button class="mkwa-btn mkwa-btn-register" data-class-id="${classId}">
                                ${mkwaStrings.register}
                            </button>
                        `);
                        $card.find('.mkwa-class-capacity').text(response.data.spots_left);
                        showNotification('success', response.data.message);
                    } else {
                        showNotification('error', response.data.message);
                        $button.prop('disabled', false).text(mkwaStrings.cancelRegistration);
                    }
                },
                error: function() {
                    showNotification('error', mkwaStrings.errorOccurred);
                    $button.prop('disabled', false).text(mkwaStrings.cancelRegistration);
                }
            });
        }
    });

    // Existing notification code
    function showNotification(type, message) {
        const notification = $('<div>')
            .addClass('mkwa-notification')
            .addClass('mkwa-notification-' + type)
            .text(message);

        $('body').append(notification);

        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Event handlers
    $('.mkwa-log-activity-btn').on('click', function(e) {
        e.preventDefault();
        const activityType = $(this).data('activity-type');
        logActivity(activityType);
    });

    // Set up periodic refreshes
    setInterval(refreshActivityLog, 300000);
    setInterval(refreshProgress, 300000);
    setInterval(refreshClassList, 300000);

    // Add notification styles
    $('<style>')
        .text(`
            .mkwa-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 5px;
                color: white;
                z-index: 1000;
                animation: slideIn 0.3s ease-out;
            }
            .mkwa-notification-success {
                background-color: #28a745;
            }
            .mkwa-notification-error {
                background-color: #dc3545;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        `)
        .appendTo('head');
});