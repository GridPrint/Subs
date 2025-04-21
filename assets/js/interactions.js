jQuery(document).ready(function ($) {

    // === Подписка / Отписка ===
    $(document).on('click', '.mis-subscribe-toggle', function () {
        const btn = $(this);
        const id = btn.data('id');
        const type = btn.data('type');

        $.post(mis_ajax.ajax_url, {
            action: 'mis_subscribe_toggle',
            nonce: mis_ajax.nonce,
            object_id: id,
            object_type: type
        }, function (res) {
            if (res.success) {
                btn.text(res.data.label);
            }
        });
    });

    // === Лайк / Дизлайк ===
    $(document).on('click', '.mis-like-toggle', function () {
        const btn = $(this);
        const id = btn.data('id');
        const type = btn.data('type');

        $.post(mis_ajax.ajax_url, {
            action: 'mis_toggle_like',
            nonce: mis_ajax.nonce,
            object_id: id,
            object_type: type
        }, function (res) {
            if (res.success) {
                btn.text(res.data.label + ' (' + res.data.count + ')');
            }
        });
    });

    // === Колокольчик: раскрытие и подгрузка ===
    $('#mis-bell').on('click', function () {
        const dropdown = $(this).find('.mis-notification-dropdown');
        dropdown.toggle();

        if (dropdown.is(':empty')) {
            $.post(mis_ajax.ajax_url, {
                action: 'mis_get_notifications',
                nonce: mis_ajax.nonce
            }, function (res) {
                if (res.success && res.data.notifications.length > 0) {
                    res.data.notifications.forEach(item => {
                        dropdown.append(`<div class="mis-notification-item ${item.is_read ? 'read' : ''}">
                            <strong>${item.type}</strong><br>
                            ${item.data.message}<br>
                            <small>${item.created_at}</small>
                        </div>`);
                    });
                } else {
                    dropdown.append('<div class="mis-notification-item">Уведомлений нет</div>');
                }
            });
        }
    });

    // === Страница уведомлений: фильтр, пагинация, действия ===

    function reloadNotificationList(page = 1) {
        $.post(mis_ajax.ajax_url, {
            action: 'mis_filter_notifications',
            nonce: mis_ajax.nonce,
            type: $('#mis-filter-type').val(),
            read: $('#mis-filter-read').val(),
            page: page
        }, function (html) {
            $('#mis-user-notification-list').html(html);
            $('#mis-load-more').data('page', page + 1);
        });
    }

    $('#mis-filter-type, #mis-filter-read').on('change', function () {
        reloadNotificationList(1);
    });

    $('#mis-load-more').on('click', function () {
        const nextPage = $(this).data('page') || 2;
        reloadNotificationList(nextPage);
    });

    $(document).on('click', '.mis-mark-read-btn', function () {
        const id = $(this).data('id');
        $.post(mis_ajax.ajax_url, {
            action: 'mis_mark_notification_read',
            nonce: mis_ajax.nonce,
            notification_id: id
        }, reloadNotificationList);
    });

    $(document).on('click', '.mis-delete-btn', function () {
        const id = $(this).data('id');
        $.post(mis_ajax.ajax_url, {
            action: 'mis_delete_notification',
            nonce: mis_ajax.nonce,
            notification_id: id
        }, reloadNotificationList);
    });

    $('#mis-mark-all-read').on('click', function () {
        $.post(mis_ajax.ajax_url, {
            action: 'mis_mark_all_read',
            nonce: mis_ajax.nonce
        }, reloadNotificationList);
    });

    $('#mis-delete-all').on('click', function () {
        if (confirm('Удалить все уведомления?')) {
            $.post(mis_ajax.ajax_url, {
                action: 'mis_delete_all_notifications',
                nonce: mis_ajax.nonce
            }, reloadNotificationList);
        }
    });
});
