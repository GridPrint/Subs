<?php

class MIS_Cron {

    public static function init() {
        add_action('mis_cleanup_notifications', [__CLASS__, 'cleanup_old_notifications']);

        if (!wp_next_scheduled('mis_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'mis_cleanup_notifications');
        }
    }

    public static function cleanup_old_notifications() {
        global $wpdb;
        $table = $wpdb->prefix . 'mis_notifications';

        $days = (int) MIS_Settings::get('notification_lifetime_days', 90);
        $date_limit = gmdate('Y-m-d H:i:s', strtotime("-$days days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $date_limit
        ));
    }
}
