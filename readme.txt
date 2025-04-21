=== My Interaction System ===
Contributors: yourname
Tags: подписка, уведомления, лайки, ajax, комментарии
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0
License: MIT

Мощная система взаимодействий для WordPress: подписки, лайки и уведомления с AJAX и REST API.

== Описание ==

Плагин добавляет систему взаимодействия между пользователями:

- Подписки на авторов, категории, комментарии
- Лайки записей и комментариев
- AJAX-комментарии (отправка, редактирование, лайк)
- Уведомления (внутренние и REST)
- Панель управления уведомлениями и подписками

== Шорткоды ==

[mis_subscribe_button object_type="author|category|comment" object_id="123"]  
[mis_like_button object_type="post|comment" object_id="123"]  
[mis_notification_bell]  
[mis_user_notifications]  
[mis_manage_subscriptions]  

== REST API ==

GET /wp-json/mis/v1/notifications  
POST /wp-json/mis/v1/notifications/{id}/read

== Настройки ==

- Включение/отключение уведомлений по типам
- Срок хранения уведомлений
- Автоматическая подписка на комментарии

== WP-Cron ==

Очистка уведомлений старше N дней (настраивается в панели настроек).

== Авторизация ==

Все действия доступны только авторизованным пользователям.

== Лицензия ==

MIT License — свободное использование и модификация.
