<?php

class MIS_Notifications
{

    public static function activate()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            data TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function __construct()
    {
        add_shortcode('mis_notification_bell', [$this, 'render_notification_bell']);
        add_shortcode('mis_user_notifications', [$this, 'render_user_notifications']);

        add_action('wp_ajax_mis_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_mis_mark_notification_read', [$this, 'ajax_mark_notification_read']);
        add_action('wp_ajax_mis_filter_notifications', [$this, 'ajax_filter_notifications']);
        add_action('wp_ajax_mis_mark_all_read', [$this, 'ajax_mark_all_read']);
        add_action('wp_ajax_mis_delete_all_notifications', [$this, 'ajax_delete_all_notifications']);
        add_action('wp_ajax_mis_delete_notification', [$this, 'ajax_delete_notification']);
    }

    public static function add_notification($user_id, $type, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'type' => sanitize_text_field($type),
            'data' => wp_json_encode($data),
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_user_notifications($user_id, $limit = 10, $offset = 0, $type = '', $read = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        $where = "WHERE user_id = %d";
        $params = [$user_id];

        if ($type) {
            $where .= " AND type = %s";
            $params[] = sanitize_text_field($type);
        }

        if ($read === 'read') {
            $where .= " AND is_read = 1";
        } elseif ($read === 'unread') {
            $where .= " AND is_read = 0";
        }

        $sql = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        foreach ($results as &$item) {
            $item['data'] = json_decode($item['data'], true);
        }

        return $results;
    }

    public static function count_unread($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }

    public static function mark_read($user_id, $id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        return $wpdb->update($table, ['is_read' => 1], [
            'id' => $id,
            'user_id' => $user_id
        ]);
    }

    public static function delete($user_id, $id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        return $wpdb->delete($table, [
            'id' => $id,
            'user_id' => $user_id
        ]);
    }

    // === Колокольчик уведомлений ===

    public function render_notification_bell()
    {
        if (!is_user_logged_in())
            return '';

        $user_id = get_current_user_id();
        $unread = self::count_unread($user_id);

        ob_start(); ?>
        <div class="mis-notification-bell" id="mis-bell">
            <span class="bell-icon">🔔</span>
            <?php if ($unread > 0): ?>
                <span class="mis-notification-count"><?= esc_html($unread); ?></span>
            <?php endif; ?>
            <div class="mis-notification-dropdown" style="display:none;"></div>
        </div>
        <?php return ob_get_clean();
    }

    // === Страница "Мои уведомления" ===

    public function render_user_notifications()
    {
        if (!is_user_logged_in())
            return '<p>Войдите, чтобы просматривать уведомления.</p>';

        ob_start(); ?>
        <div class="mis-user-notifications">
            <h3>Мои уведомления</h3>

            <div class="mis-filters">
                <select id="mis-filter-type">
                    <option value="">Все типы</option>
                    <option value="new_post_author">Новая запись от автора</option>
                    <option value="new_post_category">Новая запись в категории</option>
                    <option value="reply_to_comment">Ответ на комментарий</option>
                    <option value="reply_to_subscribed_comment">Ответ на отслеживаемый комментарий</option>
                    <option value="like_post">Лайк на запись</option>
                    <option value="like_comment">Лайк на комментарий</option>
                </select>

                <select id="mis-filter-read">
                    <option value="">Все</option>
                    <option value="unread">Непрочитанные</option>
                    <option value="read">Прочитанные</option>
                </select>
            </div>

            <div class="mis-actions">
                <button id="mis-mark-all-read">Отметить всё прочитанным</button>
                <button id="mis-delete-all">Удалить все уведомления</button>
            </div>

            <div id="mis-user-notification-list">
                <?= $this->render_notification_list(); ?>
            </div>

            <div class="mis-pagination">
                <button id="mis-load-more" data-page="1">Загрузить ещё</button>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function render_notification_list($type = '', $read = '', $page = 1)
    {
        $user_id = get_current_user_id();
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $notifications = self::get_user_notifications($user_id, $limit, $offset, $type, $read);

        if (empty($notifications))
            return '<p>Нет уведомлений по фильтру.</p>';

        ob_start(); ?>
        <ul>
            <?php foreach ($notifications as $note): ?>
                <li class="mis-notification-item <?= $note['is_read'] ? 'read' : ''; ?>" data-id="<?= esc_attr($note['id']); ?>">
                    <strong><?= esc_html($note['type']); ?></strong><br>
                    <?= esc_html($note['data']['message'] ?? ''); ?><br>
                    <small><?= esc_html($note['created_at']); ?></small>
                    <?php if (!$note['is_read']): ?>
                        <button class="mis-mark-read-btn" data-id="<?= $note['id']; ?>">Прочитано</button>
                    <?php endif; ?>
                    <button class="mis-delete-btn" data-id="<?= $note['id']; ?>">Удалить</button>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php return ob_get_clean();
    }

    // === AJAX ===

    public function ajax_get_notifications()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        $user_id = get_current_user_id();
        $notifications = self::get_user_notifications($user_id, 5);

        wp_send_json_success(['notifications' => $notifications]);
    }

    public function ajax_mark_notification_read()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        $user_id = get_current_user_id();
        $id = intval($_POST['notification_id']);

        self::mark_read($user_id, $id);
        wp_send_json_success(['message' => 'Прочитано']);
    }

    public function ajax_filter_notifications()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        $type = sanitize_text_field($_POST['type'] ?? '');
        $read = sanitize_text_field($_POST['read'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));

        echo $this->render_notification_list($type, $read, $page);
        wp_die();
    }

    public function ajax_mark_all_read()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'mis_notifications';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET is_read = 1 WHERE user_id = %d",
            $user_id
        ));

        wp_send_json_success(['message' => 'Отмечено как прочитано']);
    }

    public function ajax_delete_all_notifications()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'mis_notifications';

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE user_id = %d",
            $user_id
        ));

        wp_send_json_success(['message' => 'Все уведомления удалены']);
    }

    public function ajax_delete_notification()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        $user_id = get_current_user_id();
        $id = intval($_POST['notification_id']);

        self::delete($user_id, $id);
        wp_send_json_success(['message' => 'Уведомление удалено']);
    }
}
