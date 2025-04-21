<?php

class MIS_Hooks {

    public static function init() {
        // Работает для всех типов записей: посты, кастомные и т.п.
        // На самом деле, ниже есть проверка на 'post', так что сейчас только для них.
        add_action('transition_post_status', [self::class, 'on_post_publish'], 10, 3);
    }

    public static function on_post_publish($new_status, $old_status, $post) {
        // --- DEBUG: Логируем сам факт вызова хука ---
        error_log('[MIS_Hooks DEBUG] Hook transition_post_status triggered. New: ' . $new_status . ', Old: ' . $old_status . ', Post Type: ' . $post->post_type . ', Post ID: ' . $post->ID);

        // --- Проверка 1: Переход именно в статус 'publish' из другого статуса ---
        if ($new_status !== 'publish' || $old_status === 'publish') {
             // --- DEBUG: Логируем причину выхода (неправильный переход статуса) ---
             error_log('[MIS_Hooks DEBUG] Exiting on_post_publish: Status transition is not to publish from non-publish. New: ' . $new_status . ', Old: ' . $old_status);
             return;
        }

        // --- Проверка 2: Это должен быть стандартный пост ---
        if (!in_array($post->post_type, ['post'])) {
             // --- DEBUG: Логируем причину выхода (неправильный тип поста) ---
             error_log('[MIS_Hooks DEBUG] Exiting on_post_publish: Post type is not \'post\'. Actual type: ' . $post->post_type);
             return;
        }

        // --- DEBUG: Логируем, что все проверки пройдены и вызываем нотификатор ---
        error_log('[MIS_Hooks DEBUG] Conditions met in on_post_publish. Calling notify_subscribers for post ID: ' . $post->ID);
        self::notify_subscribers($post);
    }

    public static function notify_subscribers($post) {
        // --- DEBUG: Логируем вход в функцию нотификатора ---
        error_log('[MIS_Hooks DEBUG] Inside notify_subscribers for post ID: ' . $post->ID);

        // --- Проверка безопасности (хотя уже проверено выше) ---
        if (!$post || $post->post_status !== 'publish') {
            error_log('[MIS_Hooks DEBUG] Exiting notify_subscribers early: Invalid post object or status not publish.');
            return;
        }

        // --- Получаем настройки ---
        $settings = get_option('mis_settings');
        // --- DEBUG: Логируем загруженные настройки ---
        error_log('[MIS_Hooks DEBUG] Settings loaded: ' . print_r($settings, true));


        // --- Проверяем глобальное включение уведомлений ---
        if (empty($settings['enable_notifications'])) {
             error_log('[MIS_Hooks DEBUG] Exiting notify_subscribers: Global notifications disabled (enable_notifications is empty).');
             return;
        }

        // 🔹 Подписчики на автора
        if (!empty($settings['notify_new_posts_by_author'])) {
            error_log('[MIS_Hooks DEBUG] Author notifications enabled. Getting subscribers for author ID: ' . $post->post_author);
            $author_subs = MIS_Subscriber::get_subscribers($post->post_author, 'author');
            // --- DEBUG: Логируем найденных подписчиков автора ---
            error_log('[MIS_Hooks DEBUG] Author subscribers found: ' . print_r($author_subs, true));

            foreach ($author_subs as $user_id) {
                if ($user_id == $post->post_author) {
                    error_log('[MIS_Hooks DEBUG] Skipping author notification for user ID (is author): ' . $user_id);
                    continue; // Пропускаем самого автора
                }

                // --- DEBUG: Логируем попытку добавить уведомление для подписчика автора ---
                error_log('[MIS_Hooks DEBUG] Attempting to add AUTHOR notification for user ID: ' . $user_id . ' for post ID: ' . $post->ID);
                MIS_Notifications::add_notification($user_id, 'new_post_author', [
                    'message' => 'Новая запись от автора: ' . get_the_title($post->ID),
                    'post_id' => $post->ID
                ]);
            }
        } else {
             error_log('[MIS_Hooks DEBUG] Author notifications disabled in settings.');
        }

        // 🔹 Подписчики на категории
        if (!empty($settings['notify_new_posts_by_category'])) {
            error_log('[MIS_Hooks DEBUG] Category notifications enabled. Getting categories for post ID: ' . $post->ID);
            $categories = wp_get_post_categories($post->ID);
             error_log('[MIS_Hooks DEBUG] Post categories found: ' . print_r($categories, true));

            foreach ($categories as $cat_id) {
                error_log('[MIS_Hooks DEBUG] Getting subscribers for category ID: ' . $cat_id);
                $cat_subs = MIS_Subscriber::get_subscribers($cat_id, 'category');
                // --- DEBUG: Логируем найденных подписчиков категории ---
                error_log('[MIS_Hooks DEBUG] Category subscribers for cat ID ' . $cat_id . ': ' . print_r($cat_subs, true));

                foreach ($cat_subs as $user_id) {
                    if ($user_id == $post->post_author) {
                         error_log('[MIS_Hooks DEBUG] Skipping category notification for user ID (is author): ' . $user_id . ' in category ' . $cat_id);
                         continue; // Пропускаем самого автора
                    }

                     // --- DEBUG: Логируем попытку добавить уведомление для подписчика категории ---
                    error_log('[MIS_Hooks DEBUG] Attempting to add CATEGORY notification for user ID: ' . $user_id . ' for post ID: ' . $post->ID . ' in category ' . $cat_id);
                    MIS_Notifications::add_notification($user_id, 'new_post_category', [
                        'message'     => 'Новая запись в категории: ' . get_the_title($post->ID),
                        'post_id'     => $post->ID,
                        'category_id' => $cat_id
                    ]);
                }
            }
        } else {
            error_log('[MIS_Hooks DEBUG] Category notifications disabled in settings.');
        }

         error_log('[MIS_Hooks DEBUG] Finished notify_subscribers for post ID: ' . $post->ID);
    }
}

// --- Не забудьте где-то вызвать инициализацию! ---
// Например, в основном файле вашего плагина или в functions.php темы:
// MIS_Hooks::init();
// Или лучше подключить через хук 'plugins_loaded' или 'after_setup_theme':
// add_action('plugins_loaded', ['MIS_Hooks', 'init']);

?>