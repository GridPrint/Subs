<?php

class MIS_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_mis_subscribe_toggle', [$this, 'handle_subscribe_toggle']);
        add_action('wp_ajax_mis_toggle_like', [$this, 'handle_like_toggle']);
    }

    public function handle_subscribe_toggle() {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Вы не авторизованы']);

        $user_id = get_current_user_id();
        $object_id = intval($_POST['object_id']);
        $object_type = sanitize_text_field($_POST['object_type']);

        if (MIS_Subscriber::is_subscribed($user_id, $object_id, $object_type)) {
            MIS_Subscriber::unsubscribe($user_id, $object_id, $object_type);
            $status = 'unsubscribed';
        } else {
            MIS_Subscriber::subscribe($user_id, $object_id, $object_type);
            $status = 'subscribed';
        }

        wp_send_json_success([
            'status' => $status,
            'label'  => $status === 'subscribed' ? 'Отписаться' : 'Подписаться',
        ]);
    }

    public function handle_like_toggle() {
        check_ajax_referer('mis_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Вы не авторизованы']);

        $user_id = get_current_user_id();
        $object_id = intval($_POST['object_id']);
        $object_type = sanitize_text_field($_POST['object_type']);

        if (MIS_Likes::is_liked($user_id, $object_id, $object_type)) {
            MIS_Likes::unlike($user_id, $object_id, $object_type);
            $status = 'unliked';
        } else {
            MIS_Likes::like($user_id, $object_id, $object_type);
            $status = 'liked';
        }

        $count = MIS_Likes::count_likes($object_id, $object_type);

        wp_send_json_success([
            'status' => $status,
            'count'  => $count,
            'label'  => $status === 'liked' ? 'Убрать лайк' : 'Лайк'
        ]);
    }
}
