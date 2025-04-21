<?php

class MIS_Likes
{

    public static function activate()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_likes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(20) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_like (user_id, object_id, object_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function __construct()
    {
        add_shortcode('mis_auto_like_button', [$this, 'render_auto_like_button']);
        add_shortcode('mis_comment_like_button', [$this, 'render_comment_like_auto']);

        add_shortcode('mis_like_button', [$this, 'render_like_button']);
        add_action('wp_ajax_mis_toggle_like', [$this, 'ajax_toggle_like']);
    }

    public function render_auto_like_button()
    {
        global $post;
        $ctx = mis_get_context_object();

        if (!$ctx && in_the_loop() && is_main_query()) {
            $ctx = [
                'object_id' => get_the_ID(),
                'object_type' => 'post'
            ];
        }

        if (!$ctx || !is_user_logged_in())
            return '';

        return $this->render_like_button([
            'object_id' => $ctx['object_id'],
            'object_type' => $ctx['object_type'],
        ]);
    }

    public function render_comment_like_auto()
    {
        global $comment;
        if (!isset($comment->comment_ID) || !is_user_logged_in())
            return '';

        return $this->render_like_button([
            'object_id' => $comment->comment_ID,
            'object_type' => 'comment'
        ]);
    }


    public static function like($user_id, $object_id, $object_type)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_likes';

        $wpdb->replace($table, [
            'user_id' => $user_id,
            'object_id' => $object_id,
            'object_type' => sanitize_text_field($object_type),
            'created_at' => current_time('mysql')
        ]);

        if ($object_type === 'post') {
            $post = get_post($object_id);
            if ($post && $post->post_author != $user_id && MIS_Settings::get('notify_on_likes')) {
                MIS_Notifications::add_notification($post->post_author, 'like_post', [
                    'message' => 'Вашу запись лайкнули: ' . get_the_title($post->ID),
                    'post_id' => $post->ID
                ]);
            }
        }

        if ($object_type === 'comment') {
            $comment = get_comment($object_id);
            if ($comment && $comment->user_id != $user_id && MIS_Settings::get('notify_on_likes')) {
                MIS_Notifications::add_notification($comment->user_id, 'like_comment', [
                    'message' => 'Ваш комментарий лайкнули: ' . wp_trim_words($comment->comment_content, 10),
                    'comment_id' => $comment->comment_ID
                ]);
            }
        }
    }

    public static function unlike($user_id, $object_id, $object_type)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_likes';

        $wpdb->delete($table, [
            'user_id' => $user_id,
            'object_id' => $object_id,
            'object_type' => sanitize_text_field($object_type),
        ]);
    }

    public static function is_liked($user_id, $object_id, $object_type)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_likes';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND object_id = %d AND object_type = %s",
            $user_id,
            $object_id,
            sanitize_text_field($object_type)
        ));
    }

    public static function count_likes($object_id, $object_type)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_likes';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE object_id = %d AND object_type = %s",
            $object_id,
            sanitize_text_field($object_type)
        ));
    }

    public function render_like_button($atts)
    {
        if (!is_user_logged_in())
            return '';

        $atts = shortcode_atts([
            'object_id' => get_the_ID(), // Используем get_the_ID() вместо глобального $post
            'object_type' => 'post',
        ], $atts);

        $user_id = get_current_user_id();
        $object_id = intval($atts['object_id']);
        $object_type = sanitize_text_field($atts['object_type']);

        if (!$object_id)
            return '';

        $is_liked = self::is_liked($user_id, $object_id, $object_type);
        $like_count = self::count_likes($object_id, $object_type);

        ob_start(); ?>
        <button class="mis-like-toggle" data-id="<?= esc_attr($object_id); ?>" data-type="<?= esc_attr($object_type); ?>"
            id="mis-like-<?= esc_attr($object_type . '-' . $object_id) ?>">
            <?= $is_liked ? 'Убрать лайк' : 'Лайк'; ?> (<?= $like_count ?>)
        </button>
        <?php
        return ob_get_clean();
    }


    public function ajax_toggle_like()
    {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in())
            wp_send_json_error();

        $user_id = get_current_user_id();
        $object_id = intval($_POST['object_id']);
        $object_type = sanitize_text_field($_POST['object_type']);

        if (self::is_liked($user_id, $object_id, $object_type)) {
            self::unlike($user_id, $object_id, $object_type);
            $status = 'unliked';
        } else {
            self::like($user_id, $object_id, $object_type);
            $status = 'liked';
        }

        $count = self::count_likes($object_id, $object_type);

        wp_send_json_success([
            'status' => $status,
            'count' => $count,
            'label' => $status === 'liked' ? 'Убрать лайк' : 'Лайк'
        ]);
    }
}
