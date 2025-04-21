<?php

class MIS_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu() {
        add_options_page(
            'Настройки взаимодействий',
            'MIS Настройки',
            'manage_options',
            'mis-settings',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings() {
        register_setting('mis_settings_group', 'mis_settings');

        add_settings_section(
            'mis_main_section',
            'Основные настройки уведомлений',
            null,
            'mis-settings'
        );

        self::add_checkbox('enable_notifications', 'Включить уведомления');
        self::add_checkbox('notify_on_likes', 'Уведомлять о лайках');
        self::add_checkbox('notify_new_posts_by_author', 'О новых постах от авторов');
        self::add_checkbox('notify_new_posts_by_category', 'О новых постах в категориях');
        self::add_checkbox('auto_subscribe_own_comment', 'Автоподписка на свой комментарий');

        add_settings_field(
            'notification_lifetime_days',
            'Срок хранения уведомлений (в днях)',
            [__CLASS__, 'number_field'],
            'mis-settings',
            'mis_main_section',
            [
                'label_for' => 'notification_lifetime_days',
                'option_name' => 'mis_settings',
                'field_name' => 'notification_lifetime_days',
                'default' => 90
            ]
        );
    }

    private static function add_checkbox($field, $label) {
        add_settings_field(
            $field,
            $label,
            [__CLASS__, 'checkbox_field'],
            'mis-settings',
            'mis_main_section',
            [
                'label_for' => $field,
                'option_name' => 'mis_settings',
                'field_name' => $field
            ]
        );
    }

    public static function checkbox_field($args) {
        $options = get_option($args['option_name']);
        $checked = !empty($options[$args['field_name']]) ? 'checked' : '';
        echo "<input type='checkbox' id='{$args['field_name']}' name='{$args['option_name']}[{$args['field_name']}]' value='1' $checked />";
    }

    public static function number_field($args) {
        $options = get_option($args['option_name']);
        $value = isset($options[$args['field_name']]) ? esc_attr($options[$args['field_name']]) : ($args['default'] ?? 90);
        echo "<input type='number' id='{$args['field_name']}' name='{$args['option_name']}[{$args['field_name']}]' value='$value' min='1' />";
    }

    public static function get($key, $default = false) {
        $options = get_option('mis_settings');
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1>Настройки My Interaction System</h1>

            <div class="mis-settings-description" style="margin-bottom: 30px; background: #fff; border: 1px solid #ccd0d4; padding: 20px;">
                <h2>Описание функционала</h2>
                <ul>
                    <li><strong>Подписки:</strong> авторы, категории, комментарии.</li>
                    <li><strong>Лайки:</strong> записи и комментарии.</li>
                    <li><strong>Уведомления:</strong> новые записи, лайки, ответы.</li>
                    <li><strong>Комментарии AJAX:</strong> отправка, редактирование, лайки, подписка.</li>
                    <li><strong>REST API:</strong> доступ к уведомлениям извне.</li>
                </ul>
            </div>

            <div class="mis-shortcodes-help" style="margin-bottom: 30px; background: #fff; border: 1px solid #ccd0d4; padding: 20px;">
                <h2>Шорткоды</h2>
                <ul>
                    <li><code>[mis_subscribe_button object_type="author|category|comment" object_id="123"]</code></li>
                    <li><code>[mis_like_button object_type="post|comment" object_id="123"]</code></li>
                    <li><code>[mis_notification_bell]</code></li>
                    <li><code>[mis_user_notifications]</code></li>
                    <li><code>[mis_manage_subscriptions]</code></li>
                </ul>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('mis_settings_group');
                do_settings_sections('mis-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
