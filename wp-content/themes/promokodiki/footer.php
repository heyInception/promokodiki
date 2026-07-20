<?php

/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package promokodiki
 */

?>

<footer class="footer">
  <div class="container">
    <div class="footer__wrapper">
      <div class="footer__row">
        <div class="footer__column">
          <div class="footer__logo">
            <img src="<?php echo get_template_directory_uri(); ?>/img/footer-logo.png" alt="">
          </div>
          <div class="footer__description">
            <p>👋 Приветствуем вас в самом большом шопинг-сообществе России!</p>
            <p>Более <b>500 тысяч участников</b>, как и вы, стали частью нашего сообщества и поделились свыше <b>300
                тысячами
                скидок</b>. В
              обсуждениях, собравших более <b>15,86 миллионов комментариев</b>, люди делятся своими советами, лайфхаками
              и
              экспертными мнениями.</p>
          </div>
          <div class="footer__copyright">
            © 2017-2026 TEST.ru. Все права защищены.
          </div>
        </div>
        <div class="footer__column footer__column_nav">
          <div class="footer__items">
            <div class="footer__item">
              <div class="footer__item-title">О компании</div>
              <ul>
                <li><a href="/about/">О нас</a></li>
                <li><a href="/teams/">Наша команда</a></li>
                <li><a href="/contacts/">Контакты</a></li>
              </ul>
            </div>
            <div class="footer__item">
              <div class="footer__item-title">Сообщество</div>
              <ul>
                <li><a href="/faq/">FAQ: Часто задаваемые вопросы</a></li>
                <li><a href="/blog/">Блог</a></li>
                <li><a href="">Присоединяйтесь к нам в Telegram</a></li>
              </ul>
            </div>
            <div class="footer__item">
              <a class="footer__item-title">Промокоды</a>
            </div>
            <div class="footer__item">
              <a class="footer__item-title">Скидки</a>
            </div>
            <div class="footer__item">
              <a class="footer__item-title">Магазины</a>
            </div>
          </div>
        </div>
        <div class="footer__column footer__column_button">
          <button class="footer__button footer__button_add btn-reset">Добавить</button>
          <button class="footer__button footer__button_up btn-reset">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g clip-path="url(#clip0_2009_263)">
                <path
                  d="M9.8 20.8999C9.8 21.5626 10.3373 22.0999 11 22.0999C11.6627 22.0999 12.2 21.5626 12.2 20.8999L9.8 20.8999ZM11.8485 0.251375C11.3799 -0.217254 10.6201 -0.217254 10.1515 0.251375L2.51472 7.88813C2.04609 8.35676 2.04609 9.11656 2.51472 9.58518C2.98335 10.0538 3.74315 10.0538 4.21177 9.58518L11 2.79696L17.7882 9.58518C18.2569 10.0538 19.0167 10.0538 19.4853 9.58518C19.9539 9.11655 19.9539 8.35676 19.4853 7.88813L11.8485 0.251375ZM11 20.8999L12.2 20.8999L12.2 1.0999L11 1.0999L9.8 1.0999L9.8 20.8999L11 20.8999Z"
                  fill="white" />
              </g>
              <defs>
                <clipPath id="clip0_2009_263">
                  <rect width="21.6" height="21.6" fill="white" transform="translate(0.199951 21.7998) rotate(-90)" />
                </clipPath>
              </defs>
            </svg>
          </button>
        </div>
      </div>
      <div class="footer__menu">
        <nav class="nav" title="">
          <ul class="list-reset nav__list">
            <li><a href="/privacy-policy/">Политика конфиденциальности</a></li>
            <li><a href="">Обработка данных</a></li>
            <li><a href="">Правила использования сайта</a></li>
            <li><a href="">Правообладателям</a></li>
            <li><a href="">Карта сайта</a></li>
          </ul>
        </nav>
      </div>
      <div class="footer__content">Вся информация публикуемая на сайте test.ru не является публичной офертой и носит
        справочный характер, на основании статьи 437 Гражданского кодекса РФ определяемой ее положениями.</div>
      <div class="footer__copyright footer__copyright_m"> © 2017-2025 TEST.ru. Все права защищены.</div>
    </div>
  </div>
</footer>
</div><!-- #page -->
<div class="modal-promocode" id="promocodeModal" style="display: none;">
  <div class="modal-promocode__overlay"></div>
  <div class="modal-promocode__content">
    <button class="modal-promocode__close" aria-label="Закрыть модальное окно">×</button>

    <div class="modal-promocode__logo">
      <img src="" alt="" id="modalPromoLogo">
    </div>

    <div class="modal-promocode__title" id="modalPromoTitle"></div>
    <p class="modal-promocode__description" id="modalPromoDesc"></p>

    <div class="modal-promocode__code-wrapper">
      <input type="text" class="modal-promocode__code" id="modalPromoCode" readonly>
      <button class="modal-promocode__copy" id="copyPromoBtn">СКОПИРОВАТЬ</button>
    </div>

    <a href="#" class="modal-promocode__link" id="modalPromoLink" target="_blank" rel="nofollow">Перейти в магазин</a>

    <div class="modal-promocode__meta">
      <div class="modal-promocode__used"><span>Применили:</span> <span id="modalPromoUsed">0</span> раз</div>
      <div class="modal-promocode__expiry"><span>Активен до:</span> <span id="modalPromoExpiry">-</span></div>
    </div>
  </div>
</div>
<?php wp_footer(); ?>
<script>
  jQuery(document).ready(function() {
    // При наведении на пункт меню с подменю
    jQuery('.menu-item-has-children').hover(
      function() {
        // Показываем sub-menu с display: flex
        jQuery(this).find('.sub-menu').css('display', 'flex');
      },
      function() {
        // Скрываем sub-menu при уходе курсора
        jQuery(this).find('.sub-menu').css('display', 'none');
      }
    );
  });
  <?php if (!is_page('discounts') && !is_search()): ?>
    document.addEventListener('DOMContentLoaded', function() {
      let isLoading = false;
      let page = 1;
      const container = document.querySelector('.promocodes__items');
      if (!container) {
        return;
      }
      const noMorePosts = document.createElement('p');
      noMorePosts.className = 'no-more-promocodes';
      noMorePosts.textContent = 'Больше промокодов нет';
      noMorePosts.style.display = 'none';

      // Добавляем noMorePosts после контейнера
      container.insertAdjacentElement('afterend', noMorePosts);

      // Определяем текущую категорию и тип поста
      let currentCategory = '';
      let currentPostType = '';

      // Проверяем URL для определения категории
      const pathParts = window.location.pathname.split('/');
      if (pathParts.includes('shops_category')) {
        currentPostType = 'shops';
        currentCategory = pathParts[pathParts.indexOf('shops_category') + 1];
      } else if (pathParts.includes('promocode_category')) {
        currentPostType = 'promocode';
        currentCategory = pathParts[pathParts.indexOf('promocode_category') + 1];
      }
      // Альтернативный способ для страниц архивов
      else if (document.body.classList.contains('tax-shops_category')) {
        currentPostType = 'shops';
        const categorySlug = window.location.pathname.split('/').filter(Boolean).pop();
        currentCategory = categorySlug;
      } else if (document.body.classList.contains('tax-promocode_category')) {
        currentPostType = 'promocode';
        const categorySlug = window.location.pathname.split('/').filter(Boolean).pop();
        currentCategory = categorySlug;
      }

      function loadMorePosts() {
        if (isLoading) return;
        isLoading = true;
        page++;

        const loader = document.createElement('div');
        loader.className = 'promocodes-loader';
        loader.innerHTML = '<div class="spinner"></div>';
        container.parentNode.insertBefore(loader, container.nextSibling);

        const data = new FormData();
        data.append('action', 'load_more_promocodes');
        data.append('nonce', promokodikiAjaxNonce);
        data.append('page', page);
        data.append('category', currentCategory);
        data.append('post_type', currentPostType);

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
          })
          .then(response => response.text())
          .then(html => {
            loader.remove();

            if (html.trim() !== '') {
              container.insertAdjacentHTML('beforeend', html);
              isLoading = false;

              // Проверяем, пришли ли посты
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = html;
              if (tempDiv.querySelector('.promocodes__item') === null) {
                noMorePosts.style.display = 'block';
                isLoading = true;
              }
            } else {
              noMorePosts.style.display = 'block';
              isLoading = true;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            loader.remove();
            isLoading = false;
            page--;
          });
      }

      window.addEventListener('scroll', function() {
        if (isLoading || noMorePosts.style.display === 'block') return;

        const scrollPosition = window.scrollY;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;

        if (documentHeight - (scrollPosition + windowHeight) < 300) {
          loadMorePosts();
        }
      });

      // Первоначальная проверка, есть ли посты
      if (!container.querySelector('.promocodes__item')) {
        noMorePosts.style.display = 'block';
      }
    });
  <?php endif; ?>
</script>
<?php if (is_search()): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (!document.body.classList.contains('search-results')) {
        return;
      }

      let isLoading = false;
      let page = 1;
      const container = document.querySelector('.promocodes__items');

      if (!container) {
        console.warn('Container .promocodes__items not found');
        return;
      }

      const noMorePosts = document.createElement('p');
      noMorePosts.className = 'no-more-promocodes';
      noMorePosts.textContent = 'Больше результатов нет';
      noMorePosts.style.display = 'none';
      container.insertAdjacentElement('afterend', noMorePosts);

      // Получаем только поисковый запрос
      const searchQuery = new URLSearchParams(window.location.search).get('s') || '';

      function loadMoreSearchResults() {
        if (isLoading) return;
        isLoading = true;
        page++;

        const loader = document.createElement('div');
        loader.className = 'promocodes-loader';
        loader.innerHTML = '<div class="spinner"></div>';
        container.parentNode.insertBefore(loader, container.nextSibling);

        const data = new FormData();
        data.append('action', 'load_more_search_results');
        data.append('page', page);
        data.append('search_query', searchQuery);

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
          })
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
          })
          .then(html => {
            loader.remove();

            if (html.trim() !== '') {
              container.insertAdjacentHTML('beforeend', html);
              isLoading = false;

              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = html;
              if (tempDiv.querySelector('.promocodes__item') === null &&
                tempDiv.querySelector('.shops-category__item') === null) {
                noMorePosts.style.display = 'block';
                isLoading = true;
              }
            } else {
              noMorePosts.style.display = 'block';
              isLoading = true;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            loader.remove();
            isLoading = false;
            page--;
          });
      }

      let scrollTimeout;
      window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
          if (isLoading || noMorePosts.style.display === 'block') return;

          const scrollPosition = window.scrollY;
          const windowHeight = window.innerHeight;
          const documentHeight = document.documentElement.scrollHeight;

          if (documentHeight - (scrollPosition + windowHeight) < 300) {
            loadMoreSearchResults();
          }
        }, 200);
      });

      if (!container.querySelector('.promocodes__item') &&
        !container.querySelector('.shops-category__item')) {
        noMorePosts.style.display = 'block';
      }
    });
  </script>
<?php endif; ?>
<?php if (is_page('discounts')): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (!document.body.classList.contains('page-discounts')) return;

      let isLoading = false;
      let page = {
        top: 1,
        new: 1,
        discussed: 1
      }; // страницы по табам
      let activeTab = 'top';

      const tabs = document.querySelectorAll('.tabs__nav-btn');
      const panels = document.querySelectorAll('.tabs__panel');

      // переключение табов
      tabs.forEach(tab => {
        tab.addEventListener('click', function() {
          // убираем активные
          tabs.forEach(t => t.classList.remove('tabs__nav-btn--active'));
          panels.forEach(p => p.classList.remove('tabs__panel--active'));

          // ставим активные классы
          this.classList.add('tabs__nav-btn--active');
          const tabName = this.dataset.tab;
          activeTab = tabName;
          document.querySelector(`.tabs__panel[data-tab="${tabName}"]`).classList.add('tabs__panel--active');

          // если контейнер пустой — загружаем
          const container = document.querySelector(`.tabs__panel[data-tab="${tabName}"] .promocodes__items`);
          if (container && container.children.length === 0) {
            loadMorePosts();
          }
        });
      });

      function loadMorePosts() {
        if (isLoading) return;
        isLoading = true;
        page[activeTab]++;

        const panel = document.querySelector(`.tabs__panel[data-tab="${activeTab}"]`);
        const container = panel.querySelector('.promocodes__items');

        const loader = document.createElement('div');
        loader.className = 'promocodes-loader';
        loader.innerHTML = '<div class="spinner"></div>';
        panel.appendChild(loader);

        const data = new FormData();
        data.append('action', 'load_more_promocodes');
        data.append('nonce', promokodikiAjaxNonce);
        data.append('page', page[activeTab]);
        data.append('post_type', 'promocode');
        data.append('tab', activeTab);

        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
          })
          .then(response => response.text())
          .then(html => {
            loader.remove();

            if (html.trim() !== '') {
              container.insertAdjacentHTML('beforeend', html);
              isLoading = false;
            } else {
              // постов больше нет
              isLoading = true;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            loader.remove();
            isLoading = false;
            page[activeTab]--;
          });
      }

      // infinite scroll
      window.addEventListener('scroll', function() {
        if (isLoading) return;

        const panel = document.querySelector(`.tabs__panel.tabs__panel--active`);
        if (!panel) return;

        const container = panel.querySelector('.promocodes__items');
        if (!container) return;

        const scrollPosition = window.scrollY;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;

        if (documentHeight - (scrollPosition + windowHeight) < 300) {
          loadMorePosts();
        }
      });
    });
  </script>
<?php endif; ?>
<?php if (is_page('discounts')): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const DEBUG = true;

      // если нет глобального ajaxurl (иногда на фронте не локализуют)
      if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '/wp-admin/admin-ajax.php';
        DEBUG && console.warn('[discounts] ajaxurl не был определён, использую /wp-admin/admin-ajax.php');
      }

      // найдём активный таб из DOM (на случай, если твой tabs-скрипт сам уже проставил классы)
      const getActiveTabFromDOM = () => {
        const activePanel = document.querySelector('.tabs__panel.tabs__panel--active') || document.querySelector('.tabs__panel');
        const tabName = activePanel ? activePanel.dataset.tab : 'top';
        DEBUG && console.log('[discounts] активная вкладка из DOM:', tabName);
        return tabName || 'top';
      };

      // состояние по табам
      const page = {
        top: 1,
        new: 1,
        discussed: 1
      };
      const isLoading = {
        top: false,
        new: false,
        discussed: false
      };
      const noMore = {
        top: false,
        new: false,
        discussed: false
      };

      let activeTab = getActiveTabFromDOM();
      const panels = document.querySelectorAll('.tabs__panel');
      const tabsBtns = document.querySelectorAll('.tabs__nav-btn');

      // создаём «стражей» (sentinel) для каждого таба, чтобы ловить доскролл именно его панели
      panels.forEach(p => {
        if (!p.querySelector('.scroll-sentinel')) {
          const s = document.createElement('div');
          s.className = 'scroll-sentinel';
          s.style.height = '1px';
          s.style.marginTop = '1px';
          p.appendChild(s);
        }
      });

      // observer для подгрузки
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const panel = entry.target.closest('.tabs__panel');
          const tabName = panel?.dataset.tab;
          const isActive = panel?.classList.contains('tabs__panel--active');

          if (!tabName) return;

          DEBUG && console.log('[discounts][IO] наблюдение:', {
            tabName,
            isActive,
            intersecting: entry.isIntersecting,
            ratio: entry.intersectionRatio
          });

          if (isActive && entry.isIntersecting) {
            loadMore(tabName);
          }
        });
      }, {
        root: null,
        rootMargin: '200px 0px 200px 0px',
        threshold: 0.01
      });

      // наблюдаем только за активной панелью (и переставляем при переключении)
      const observeActive = () => {
        panels.forEach(p => io.unobserve(p.querySelector('.scroll-sentinel')));
        const activePanel = document.querySelector(`.tabs__panel[data-tab="${activeTab}"]`);
        if (activePanel) {
          const sentinel = activePanel.querySelector('.scroll-sentinel');
          io.observe(sentinel);
          DEBUG && console.log('[discounts] наблюдаю за табом:', activeTab);
        } else {
          DEBUG && console.warn('[discounts] не нашёл панель таба:', activeTab);
        }
      };
      observeActive();

      // обработка кликов по табам
      tabsBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          const newTab = btn.dataset.tab;
          if (!newTab) return;

          // классы активностей — оставляю как в твоём проекте
          tabsBtns.forEach(b => b.classList.remove('tabs__nav-btn--active'));
          panels.forEach(p => p.classList.remove('tabs__panel--active'));

          btn.classList.add('tabs__nav-btn--active');
          const panel = document.querySelector(`.tabs__panel[data-tab="${newTab}"]`);
          panel && panel.classList.add('tabs__panel--active');

          activeTab = newTab;
          DEBUG && console.log('[discounts] переключение вкладки ->', activeTab);

          // пере-наблюдаем новый активный
          observeActive();

          // если контейнер пустой — подгружаем стартовую порцию
          const container = panel?.querySelector('.promocodes__items');
          if (container && container.children.length === 0 && !isLoading[activeTab] && !noMore[activeTab]) {
            DEBUG && console.log('[discounts] контейнер пуст — запрашиваю первую подгрузку для', activeTab);
            loadMore(activeTab);
          }
        });
      });

      function loadMore(tabName) {
        if (noMore[tabName]) {
          DEBUG && console.log('[discounts] noMore=true, остановка подгрузки для', tabName);
          return;
        }
        if (isLoading[tabName]) {
          DEBUG && console.log('[discounts] уже грузится для', tabName);
          return;
        }

        isLoading[tabName] = true;
        page[tabName] += 1;

        const panel = document.querySelector(`.tabs__panel[data-tab="${tabName}"]`);
        const container = panel?.querySelector('.promocodes__items');

        if (!panel || !container) {
          DEBUG && console.error('[discounts] не нашёл panel/container для', tabName);
          isLoading[tabName] = false;
          return;
        }

        // лоадер
        let loader = panel.querySelector('.promocodes-loader');
        if (!loader) {
          loader = document.createElement('div');
          loader.className = 'promocodes-loader';
          loader.innerHTML = '<div class="spinner"></div>';
          panel.appendChild(loader);
        }

        const data = new FormData();
        data.append('action', 'load_more_promocodes');
        data.append('nonce', promokodikiAjaxNonce);
        data.append('page', page[tabName]);
        data.append('post_type', 'promocode');
        data.append('tab', tabName);
        if (DEBUG) data.append('debug', '1');

        DEBUG && console.group(`[discounts][fetch] ${tabName}`);
        DEBUG && console.log('POST', Object.fromEntries(data.entries()));

        fetch(window.ajaxurl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
          })
          .then(r => r.text())
          .then(html => {
            DEBUG && console.log('HTML length:', html.length);
            DEBUG && console.log('HTML preview:', html.slice(0, 200).replace(/\n/g, '⏎') + (html.length > 200 ? '…' : ''));
            loader.remove();

            const trimmed = html.trim();

            // иногда сервер шлёт WP Notices — попробуем вытащить реальные карточки
            const tmp = document.createElement('div');
            tmp.innerHTML = trimmed;

            const items = tmp.querySelectorAll('.promocodes__item');
            if (items.length > 0) {
              items.forEach(el => container.appendChild(el));
              isLoading[tabName] = false;
              DEBUG && console.log(`[discounts] добавлено карточек: ${items.length}, page=${page[tabName]}`);
            } else {
              // если карточек нет — значит, всё, больше нет
              noMore[tabName] = true;
              isLoading[tabName] = false;
              DEBUG && console.warn('[discounts] карточек не пришло — noMore=true для', tabName);
            }
          })
          .catch(err => {
            loader.remove();
            isLoading[tabName] = false;
            page[tabName] -= 1; // откат
            console.error('[discounts][fetch][error]', err);
          })
          .finally(() => {
            DEBUG && console.groupEnd();
          });
      }
    });
  </script>
<?php endif; ?>

<?php
// Определяем функции глобально (вне условий)
function addPromoModalScripts()
{
  if (!is_single()):
?>
    <script>
      // Определяем функции глобально, чтобы они были доступны всегда
      window.openPromoModal = function(postId) {
        // Получаем данные промокода
        const postElement = document.querySelector(`.promocodes__item[data-post-id="${postId}"]`);

        if (!postElement) return;

        // Заполняем модальное окно данными
        document.getElementById('modalPromoTitle').textContent = postElement.querySelector('.promocodes__title').textContent;

        // Получаем код промокода (если есть)
        const promoCode = postElement.querySelector('input[name="_promocode_code"]')?.value || '';
        document.getElementById('modalPromoCode').value = promoCode;

        // Получаем ссылку
        const promoLink = postElement.querySelector('input[name="_promocode_link"]')?.value ||
          postElement.querySelector('.promocodes__link')?.getAttribute('href') || '#';

        // Устанавливаем ссылку и текст кнопки
        const promoLinkElement = document.getElementById('modalPromoLink');
        promoLinkElement.setAttribute('href', promoLink);
        promoLinkElement.textContent = promoCode ? 'Перейти с промокодом' : 'Перейти в магазин';

        // Получаем количество использований
        setTimeout(() => {
          const usedCount = postElement.querySelector('.promocodes__used')?.textContent || '0';
          document.getElementById('modalPromoUsed').textContent = usedCount.replace('Применено', '').trim();
        }, 600);

        // Получаем дату окончания
        const expiryDate = postElement.querySelector('.promocodes__date')?.textContent || '';
        document.getElementById('modalPromoExpiry').textContent = expiryDate.replace('до', '').trim();

        // Получаем логотип
        const logoImg = postElement.querySelector('.promocodes__imgs img') ||
          postElement.querySelector('.promocodes__author img');
        if (logoImg) {
          document.getElementById('modalPromoLogo').src = logoImg.src;
          document.getElementById('modalPromoLogo').alt = logoImg.alt;
        }

        // Показываем модальное окно
        const modal = document.getElementById('promocodeModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        setTimeout(() => {
          modal.classList.add('show');
        }, 10);
      };

      window.closePromoModal = function() {
        const modal = document.getElementById('promocodeModal');
        modal.classList.remove('show');

        setTimeout(() => {
          modal.style.display = 'none';
          document.body.style.overflow = '';
        }, 300);
      };

      // Инициализация обработчиков событий
      document.addEventListener('DOMContentLoaded', function() {
        // Обработчик для кнопки "Перейти в магазин"
        document.querySelectorAll('.promocodes__link').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.preventDefault();

            const postId = this.closest('.promocodes__item').getAttribute('data-post-id');
            const link = this.getAttribute('href') || this.closest('a').getAttribute('href');

            const newWindow = window.open('', '_blank');

            // Используем глобальную функцию
            window.openPromoModal(postId);

            setTimeout(() => {
              newWindow.location.href = link;
            }, 100);
          });
        });

        // Обработчик для кнопки "Посмотреть код"
        document.querySelectorAll('.promocodes__view').forEach(btn => {
          btn.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            window.openPromoModal(postId);
          });
        });

        // Обработчик для ссылки в модальном окне
        document.getElementById('modalPromoLink').addEventListener('click', function(e) {
          e.preventDefault();
          const link = this.getAttribute('href');
          if (link && link !== '#') {
            window.open(link, '_blank');
          }
        });

        // Закрытие модального окна
        document.querySelector('.modal-promocode__close').addEventListener('click', window.closePromoModal);
        document.querySelector('.modal-promocode__overlay').addEventListener('click', window.closePromoModal);

        // Копирование промокода
        document.getElementById('copyPromoBtn').addEventListener('click', function() {
          const codeInput = document.getElementById('modalPromoCode');
          codeInput.select();
          document.execCommand('copy');

          this.textContent = 'СКОПИРОВАНО!';
          setTimeout(() => {
            this.textContent = 'СКОПИРОВАТЬ';
          }, 2000);
        });
      });
    </script>
<?php
  endif;
}

addPromoModalScripts();
?>
</body>

</html>
