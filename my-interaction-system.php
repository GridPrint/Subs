<?php
/**
 * Plugin Name: My Interaction System
 * Description: Система подписок, лайков, уведомлений и AJAX-комментариев.
 * Version: 1.0
 * Author: You
 * License: MIT
 */

defined('ABSPATH') || exit;

// Константы
define('MIS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MIS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Подключение файлов
require_once MIS_PLUGIN_DIR . 'includes/class-subscriber.php';
require_once MIS_PLUGIN_DIR . 'includes/class-likes.php';
require_once MIS_PLUGIN_DIR . 'includes/class-notifications.php';
require_once MIS_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once MIS_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once MIS_PLUGIN_DIR . 'includes/class-mis-settings.php';
require_once MIS_PLUGIN_DIR . 'includes/class-mis-cron.php';
require_once MIS_PLUGIN_DIR . 'includes/class-mis-hooks.php';

// Активация и деактивация
register_activation_hook(__FILE__, function () {
    MIS_Subscriber::activate();
    MIS_Likes::activate();
    MIS_Notifications::activate();
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('mis_cleanup_notifications');
});

// Подключение стилей и скриптов
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('mis-style', MIS_PLUGIN_URL . 'assets/css/interactions.css', [], '1.0');
    wp_enqueue_script('mis-script', MIS_PLUGIN_URL . 'assets/js/interactions.js', ['jquery'], '1.0', true);
    wp_enqueue_script('mis-comments', MIS_PLUGIN_URL . 'assets/js/comments.js', ['jquery'], '1.0', true);

    wp_localize_script('mis-script', 'mis_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mis_nonce'),
    ]);
    wp_localize_script('mis-comments', 'mis_comments_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mis_comments_nonce'),
    ]);
});

// Инициализация классов
add_action('plugins_loaded', function () {
    new MIS_Subscriber();
    new MIS_Likes();
    new MIS_Notifications();
    new MIS_Ajax_Handler();
    new MIS_REST_API();
    MIS_Settings::init();
    MIS_Cron::init();
    MIS_Hooks::init();
});

// Универсальная функция для автоопределения object_type и ID
function mis_get_context_object() {
    if (is_single() || is_page()) {
        return ['object_type' => 'post', 'object_id' => get_the_ID()];
    }
    if (is_author()) {
        return ['object_type' => 'author', 'object_id' => get_queried_object_id()];
    }
    if (is_category() || is_tax()) {
        return ['object_type' => 'category', 'object_id' => get_queried_object_id()];
    }
    return null;
}
