jQuery(document).ready(function ($) {

    // === AJAX-отправка комментария ===
    $('#commentform').on('submit', function (e) {
        e.preventDefault();

        const form = $(this);
        const formData = form.serialize() + '&action=mis_submit_comment&nonce=' + mis_comments_ajax.nonce;

        $.post(mis_comments_ajax.ajax_url, formData, function (res) {
            if (res.success) {
                $('#comments').append(res.data.comment_html);
                form[0].reset();
            } else {
                alert(res.data.message || 'Ошибка отправки комментария');
            }
        });
    });

    // === Редактирование комментария (только свои) ===
    $(document).on('click', '.mis-edit-comment-btn', function () {
        const comment = $(this).closest('.comment');
        const id = $(this).data('id');
        const content = comment.find('.comment-content').text().trim();

        const textarea = $(`<textarea class="mis-edit-text">${content}</textarea>`);
        const saveBtn = $(`<button class="mis-save-comment-btn" data-id="${id}">Сохранить</button>`);

        comment.find('.comment-content').hide();
        comment.append(textarea).append(saveBtn);
    });

    $(document).on('click', '.mis-save-comment-btn', function () {
        const id = $(this).data('id');
        const newContent = $(this).siblings('.mis-edit-text').val();
        const comment = $(this).closest('.comment');

        $.post(mis_comments_ajax.ajax_url, {
            action: 'mis_edit_comment',
            nonce: mis_comments_ajax.nonce,
            comment_id: id,
            content: newContent
        }, function (res) {
            if (res.success) {
                comment.find('.comment-content').text(newContent).show();
                comment.find('.mis-edit-text, .mis-save-comment-btn').remove();
            } else {
                alert(res.data.message || 'Ошибка сохранения');
            }
        });
    });

    // === Лайк комментария ===
    $(document).on('click', '.mis-comment-like-btn', function () {
        const id = $(this).data('comment-id');
        const btn = $(this);

        $.post(mis_comments_ajax.ajax_url, {
            action: 'mis_like_comment',
            nonce: mis_comments_ajax.nonce,
            comment_id: id
        }, function (res) {
            if (res.success) {
                btn.text(`Лайк (${res.data.likes})`);
                btn.prop('disabled', true);
            } else {
                alert(res.data.message || 'Ошибка лайка');
            }
        });
    });

    // === Подписка на комментарий ===
    $(document).on('click', '.mis-comment-subscribe-btn', function () {
        const id = $(this).data('id');
        const btn = $(this);

        $.post(mis_comments_ajax.ajax_url, {
            action: 'mis_subscribe_toggle',
            nonce: mis_comments_ajax.nonce,
            object_id: id,
            object_type: 'comment'
        }, function (res) {
            if (res.success) {
                btn.text(res.data.label);
            } else {
                alert(res.data.message || 'Ошибка подписки');
            }
        });
    });
});
