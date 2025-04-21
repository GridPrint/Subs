<?php

class MIS_Subscriber {

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_subscriptions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(20) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_subscription (user_id, object_id, object_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function __construct() {
        add_shortcode('mis_auto_subscribe_button', [$this, 'render_auto_subscribe_button']);
        add_shortcode('mis_comment_subscribe_button', [$this, 'render_comment_subscribe_auto']);        


        add_shortcode('mis_subscribe_button', [$this, 'render_subscribe_button']);
        add_shortcode('mis_manage_subscriptions', [$this, 'render_manage_page']);
        add_action('wp_ajax_mis_subscribe_toggle', [$this, 'ajax_toggle_subscription']);
        add_filter('comment_form_field_comment', [$this, 'add_subscribe_checkbox']);
        add_action('comment_post', [$this, 'handle_comment_subscribe'], 20, 2);
    }

    public function render_auto_subscribe_button() {
        $ctx = mis_get_context_object();
        if (!$ctx || !is_user_logged_in()) return '';
    
        return $this->render_subscribe_button([
            'object_id'   => $ctx['object_id'],
            'object_type' => $ctx['object_type'],
        ]);
    }
    
    public function render_comment_subscribe_auto() {
        global $comment;
        if (!isset($comment->comment_ID) || !is_user_logged_in()) return '';
    
        return $this->render_subscribe_button([
            'object_id'   => $comment->comment_ID,
            'object_type' => 'comment'
        ]);
    }
    

    // === CRUD ===

    public static function subscribe($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_subscriptions';

        $wpdb->replace($table, [
            'user_id'     => $user_id,
            'object_id'   => $object_id,
            'object_type' => sanitize_text_field($object_type),
            'created_at'  => current_time('mysql')
        ]);
    }

    public static function unsubscribe($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_subscriptions';

        $wpdb->delete($table, [
            'user_id'     => $user_id,
            'object_id'   => $object_id,
            'object_type' => sanitize_text_field($object_type),
        ]);
    }

    public static function is_subscribed($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_subscriptions';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND object_id = %d AND object_type = %s",
            $user_id, $object_id, sanitize_text_field($object_type)
        ));
    }

    public static function get_subscribers($object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_subscriptions';

        return $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM $table WHERE object_id = %d AND object_type = %s",
            $object_id, sanitize_text_field($object_type)
        ));
    }

        // === Кнопка подписки (шорткод) ===

        public function render_subscribe_button($atts) {
            if (!is_user_logged_in()) return '';
    
            $atts = shortcode_atts([
                'object_id'   => get_the_ID(),
                'object_type' => 'author',
            ], $atts);
    
            $user_id = get_current_user_id();
            $object_id = intval($atts['object_id']);
            $object_type = sanitize_text_field($atts['object_type']);
    
            $is_subscribed = self::is_subscribed($user_id, $object_id, $object_type);
    
            ob_start(); ?>
            <button class="mis-subscribe-toggle"
                    data-id="<?= esc_attr($object_id); ?>"
                    data-type="<?= esc_attr($object_type); ?>">
                <?= $is_subscribed ? 'Отписаться' : 'Подписаться'; ?>
            </button>
            <?php return ob_get_clean();
        }
    
        // === AJAX ===
    
        public function ajax_toggle_subscription() {
            check_ajax_referer('mis_nonce', 'nonce');
            if (!is_user_logged_in()) wp_send_json_error();
    
            $user_id = get_current_user_id();
            $object_id = intval($_POST['object_id']);
            $object_type = sanitize_text_field($_POST['object_type']);
    
            if (self::is_subscribed($user_id, $object_id, $object_type)) {
                self::unsubscribe($user_id, $object_id, $object_type);
                $status = 'unsubscribed';
            } else {
                self::subscribe($user_id, $object_id, $object_type);
                $status = 'subscribed';
            }
    
            wp_send_json_success([
                'status' => $status,
                'label'  => $status === 'subscribed' ? 'Отписаться' : 'Подписаться',
            ]);
        }
    
        // === Страница управления подписками ===
    
        public function render_manage_page() {
            if (!is_user_logged_in()) return '<p>Войдите, чтобы управлять подписками.</p>';
    
            global $wpdb;
            $user_id = get_current_user_id();
            $table = $wpdb->prefix . 'mis_subscriptions';
    
            $subscriptions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            ));
    
            if (!$subscriptions) return '<p>Вы не подписаны ни на что.</p>';
    
            ob_start(); ?>
            <div class="mis-manage-subscriptions">
                <h3>Мои подписки</h3>
                <ul>
                    <?php foreach ($subscriptions as $sub): ?>
                        <li>
                            <?= esc_html(ucfirst($sub->object_type)) ?>: <?= esc_html($sub->object_id) ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="mis_unsub_object_id" value="<?= $sub->object_id ?>">
                                <input type="hidden" name="mis_unsub_object_type" value="<?= $sub->object_type ?>">
                                <button type="submit" name="mis_unsubscribe_submit">Отписаться</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
    
            if (isset($_POST['mis_unsubscribe_submit'])) {
                $oid = intval($_POST['mis_unsub_object_id']);
                $otype = sanitize_text_field($_POST['mis_unsub_object_type']);
                self::unsubscribe($user_id, $oid, $otype);
                echo "<script>location.reload();</script>";
            }
    
            return ob_get_clean();
        }
    
        // === Автоподписка при комментировании ===
    
        public function add_subscribe_checkbox($field) {
            if (!is_user_logged_in()) return $field;
    
            $field .= '<p class="comment-subscribe-checkbox">
                <label><input type="checkbox" name="mis_subscribe_to_self_comment" value="1" checked />
                Подписаться на ответы на мой комментарий</label></p>';
    
            return $field;
        }
    
        public function handle_comment_subscribe($comment_id, $approved) {
            if (!$approved) return;
    
            $comment = get_comment($comment_id);
            if (!$comment || !$comment->user_id) return;
    
            if (
                isset($_POST['mis_subscribe_to_self_comment']) &&
                $_POST['mis_subscribe_to_self_comment'] == '1' &&
                MIS_Settings::get('auto_subscribe_own_comment')
            ) {
                self::subscribe($comment->user_id, $comment->comment_ID, 'comment');
            }
        }
    }
    