jQuery(document).ready(function ($) {
    // Проверяем голосовал ли уже пользователь
    function hasVoted(postId) {
        const votes = JSON.parse(localStorage.getItem('promocode_votes')) || {};
        return votes.hasOwnProperty(postId);
    }

    // Сохраняем голос в localStorage
    function saveVote(postId, action) {
        const votes = JSON.parse(localStorage.getItem('promocode_votes')) || {};
        votes[postId] = action;
        localStorage.setItem('promocode_votes', JSON.stringify(votes));
    }

    // Обновляем состояние кнопок при загрузке страницы
    function updateButtonsState() {
        const votes = JSON.parse(localStorage.getItem('promocode_votes')) || {};

        $('.promocodes__like').each(function () {
            const postId = $(this).data('post-id');
            if (votes[postId]) {
                $(this).addClass('disabled');
            }
        });
    }

    // Инициализация состояния кнопок
    updateButtonsState();

    // Обработчик клика
    $('.promocodes__like').on('click', function (e) {
        e.preventDefault();

        const $this = $(this);
        const postId = $this.data('post-id');
        const action = $this.data('action');

        if (hasVoted(postId)) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: promocodes_ajax.ajaxurl,
            data: {
                action: 'handle_promocode_feedback',
                post_id: postId,
                feedback_action: action,
                security: promocodes_ajax.nonce
            },
            beforeSend: function () {
                $this.addClass('loading');
            },
            success: function (response) {
                if (response.success) {
                    // Обновляем счетчик
                    $this.find('span').text(response.data.count);
                    // Сохраняем голос
                    saveVote(postId, action);
                    // Блокируем обе кнопки для этого промокода
                    $(`.promocodes__like[data-post-id="${postId}"]`).addClass('disabled');
                } else {
                    alert(response.data.message);
                }
            },
            complete: function () {
                $this.removeClass('loading');
            },
            error: function () {
                alert('Произошла ошибка. Пожалуйста, попробуйте позже.');
            }
        });
    });
});