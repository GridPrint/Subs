<?php
// Защита от прямого доступа
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Таблицы, созданные плагином
$tables = [
    $wpdb->prefix . 'mis_subscriptions',
    $wpdb->prefix . 'mis_likes',
    $wpdb->prefix . 'mis_notifications',
];

// Удаляем каждую таблицу, если она существует
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Удаляем все user_meta лайков на комментарии (по шаблону 'liked_comment_%')
$users = get_users(['fields' => 'ID']);
foreach ($users as $user_id) {
    $all_meta = get_user_meta($user_id);
    foreach ($all_meta as $key => $value) {
        if (strpos($key, 'liked_comment_') === 0) {
            delete_user_meta($user_id, $key);
        }
    }
}
