
jQuery(function ($) {
    $(document).on('click', '.promocodes__view, .top__button, .promocodes__link', function (event) {
        event.preventDefault();

        var $button = $(this);
        var postId = $button.data('post-id');

        if ($button.hasClass('promocodes__link')) {
            window.open($button.attr('href'), '_blank');
        } else if ($button.hasClass('promocodes__view') && typeof window.openPromoModal === 'function') {
            window.openPromoModal(postId);
        }
    });
});



// ТОп промокодо из Telegram

jQuery(document).ready(function ($) {
    var timerInterval = null;
    var nextUpdateTimestamp = null;
    var timeOffset = 0; // Разница между временем сервера и клиента

    // Функция обновления таймера (без AJAX запросов)
    function updateTimer() {
        if (!nextUpdateTimestamp) return;

        // Получаем текущее время клиента + смещение
        var clientTime = Math.floor(Date.now() / 1000);
        var serverTime = clientTime + timeOffset;
        var timeLeft = nextUpdateTimestamp - serverTime;

        if (timeLeft <= 0) {
            // Время вышло - обновляем промокоды
            clearInterval(timerInterval);
            refreshPromocodes();
            return;
        }

        var hours = Math.floor(timeLeft / 3600);
        var minutes = Math.floor((timeLeft % 3600) / 60);
        var seconds = Math.floor(timeLeft % 60);

        $('#topHours').text(String(hours).padStart(2, '0'));
        $('#topMinutes').text(String(minutes).padStart(2, '0'));
        $('#topSeconds').text(String(seconds).padStart(2, '0'));
    }

    // Функция обновления промокодов
    function refreshPromocodes() {
        $('#popular-promocodes-container').addClass('updating');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'refresh_popular_promocodes'
            },
            success: function (response) {
                if (response.success) {
                    // Обновляем контейнер с промокодами
                    $('#popular-promocodes-container').html(response.data.html);

                    // Обновляем время следующего обновления
                    var serverTime = response.data.server_time;
                    nextUpdateTimestamp = response.data.next_update;

                    // Сохраняем в localStorage
                    localStorage.setItem('nextUpdateTimestamp', nextUpdateTimestamp);
                    localStorage.setItem('serverTimeAtLoad', serverTime);
                    localStorage.setItem('clientTimeAtLoad', Math.floor(Date.now() / 1000));

                    // Перезапускаем таймер
                    if (timerInterval) {
                        clearInterval(timerInterval);
                    }
                    updateTimer();
                    timerInterval = setInterval(updateTimer, 1000);

                    // Переинициализируем обработчики
                    initPromocodeHandlers();
                }
                $('#popular-promocodes-container').removeClass('updating');
            },
            error: function () {
                $('#popular-promocodes-container').removeClass('updating');
            }
        });
    }

    // Функция инициализации обработчиков
    function initPromocodeHandlers() {
        // Обработка лайков/дизлайков
        $('.top__up, .top__down').off('click').on('click', function () {
            var $this = $(this);
            var postId = $this.data('post-id');
            var action = $this.data('action');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'handle_promocode_like',
                    post_id: postId,
                    like_action: action
                },
                success: function (response) {
                    if (response.success) {
                        var count = response.data.count;
                        $this.text(count);
                    }
                }
            });
        });

        // Обработка показа промокода
        $('.top__button:not(.top__button_link)').off('click').on('click', function () {
            var $this = $(this);
            var postId = $this.data('post-id');
            var couponCode = $this.data('coupon-code');

            if (couponCode) {
                // Показываем промокод
                $this.text(couponCode).addClass('top__button_show');

                // Увеличиваем счетчик использований
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'increment_promocode_used',
                        post_id: postId
                    }
                });
            }
        });
    }

    // Инициализация таймера (один запрос к серверу)
    function initTimer() {
        // Проверяем, есть ли сохраненные данные в localStorage
        var storedTimestamp = localStorage.getItem('nextUpdateTimestamp');
        var storedServerTime = localStorage.getItem('serverTimeAtLoad');
        var storedClientTime = localStorage.getItem('clientTimeAtLoad');

        if (storedTimestamp && storedServerTime && storedClientTime) {
            nextUpdateTimestamp = parseInt(storedTimestamp);

            // Вычисляем смещение времени
            var clientTimeNow = Math.floor(Date.now() / 1000);
            var elapsed = clientTimeNow - parseInt(storedClientTime);
            var serverTimeNow = parseInt(storedServerTime) + elapsed;

            var timeLeft = nextUpdateTimestamp - serverTimeNow;

            if (timeLeft > 0) {
                // Время еще не истекло, используем сохраненные данные
                timeOffset = parseInt(storedServerTime) - parseInt(storedClientTime);

                // Запускаем таймер
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                updateTimer();
                timerInterval = setInterval(updateTimer, 1000);
                return;
            }
        }

        // Если нет сохраненных данных или время истекло - делаем один запрос к серверу
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_server_time'
            },
            success: function (response) {
                if (response.success) {
                    var serverTime = response.data.server_time;
                    nextUpdateTimestamp = response.data.next_update;
                    var clientTime = Math.floor(Date.now() / 1000);

                    // Вычисляем смещение
                    timeOffset = serverTime - clientTime;

                    // Сохраняем в localStorage
                    localStorage.setItem('nextUpdateTimestamp', nextUpdateTimestamp);
                    localStorage.setItem('serverTimeAtLoad', serverTime);
                    localStorage.setItem('clientTimeAtLoad', clientTime);

                    // Запускаем таймер
                    if (timerInterval) {
                        clearInterval(timerInterval);
                    }
                    updateTimer();
                    timerInterval = setInterval(updateTimer, 1000);
                }
            }
        });
    }

    // Проверяем, нужно ли обновлять при возвращении на вкладку
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            // Пользователь вернулся на вкладку - проверяем время
            var storedTimestamp = localStorage.getItem('nextUpdateTimestamp');
            var storedServerTime = localStorage.getItem('serverTimeAtLoad');
            var storedClientTime = localStorage.getItem('clientTimeAtLoad');

            if (storedTimestamp && storedServerTime && storedClientTime) {
                var clientTimeNow = Math.floor(Date.now() / 1000);
                var elapsed = clientTimeNow - parseInt(storedClientTime);
                var serverTimeNow = parseInt(storedServerTime) + elapsed;
                var timeLeft = parseInt(storedTimestamp) - serverTimeNow;

                if (timeLeft <= 0) {
                    // Время истекло - обновляем
                    refreshPromocodes();
                }
            }
        }
    });

    // Запускаем
    initTimer();
    initPromocodeHandlers();
});
