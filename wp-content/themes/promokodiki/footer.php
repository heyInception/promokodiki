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

    <a href="#" class="modal-promocode__link" id="modalPromoLink" target="_blank" rel="nofollow noopener">Перейти в магазин</a>

    <div class="modal-promocode__meta">
      <div class="modal-promocode__used"><span>Применили:</span> <span id="modalPromoUsed">0</span> раз</div>
      <div class="modal-promocode__expiry"><span>Активен до:</span> <span id="modalPromoExpiry">-</span></div>
    </div>
  </div>
</div>
<?php wp_footer(); ?>
</body>

</html>
