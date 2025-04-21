<?php

class MIS_REST_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Получение уведомлений пользователя
        register_rest_route('mis/v1', '/notifications', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_notifications'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);

        // Отметить уведомление как прочитанное
        register_rest_route('mis/v1', '/notifications/(?P<id>\d+)/read', [
            'methods'             => 'POST',
            'callback'            => [$this, 'mark_notification_read'],
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ]);
    }

    public function get_notifications($request) {
        $user_id = get_current_user_id();
        $notifications = MIS_Notifications::get_user_notifications($user_id, 50);

        return rest_ensure_response([
            'notifications' => $notifications,
        ]);
    }

    public function mark_notification_read($request) {
        $user_id = get_current_user_id();
        $id = absint($request['id']);

        $result = MIS_Notifications::mark_read($user_id, $id);

        return rest_ensure_response([
            'status'  => $result !== false,
            'message' => $result ? 'Прочитано' : 'Ошибка обновления',
        ]);
    }
}
