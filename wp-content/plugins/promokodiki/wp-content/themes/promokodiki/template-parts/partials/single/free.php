<section class="free">
  <div class="container">
    <div class="free__row free__row_single">
      <div class="free__column">
        <div class="free__title">
          <h2>Бесплатно проверьте штрафы</h2>
        </div>
        <div class="tabs" data-tabs="free">
          <ul class="list-reset tabs__nav">
            <li class="tabs__nav-item"><button class="btn-reset tabs__nav-btn" type="button">По номеру
                автомобиля</button></li>
            <li class="tabs__nav-item"><button class="btn-reset tabs__nav-btn" type="button">По номеру СТС</button></li>
          </ul>
          <div class="tabs__content">
            <div class="tabs__panel">
              <div class="hero__input free__input">
                <input type="text" class="js-vehicle-number" placeholder="А 000 АА 123">
                <button class="btn-reset" type="submit">Проверить штрафы</button>
              </div>
              <?php if (!is_search_bot()): ?>
                <span>Нажимая «Проверить штрафы», вы даете согласие на обработку персональных данных на условиях <a href="https://shtraf.com/privacy/" target="_blank"
                    rel="noopener noreferrer">Политики конфиденциальности</a> и принимаете <a href="https://shtraf.com/polzovatelskoe-soglashenie/" target="_blank"
                    rel="noopener noreferrer">Пользовательское соглашение</a></span>
              <?php endif; ?>
            </div>
            <div class="tabs__panel">
              <div class="hero__input free__input">
                <input type="text" class="js-sts-number" placeholder="99 22 157319">
                <button class="btn-reset" type="submit">Проверить штрафы</button>
              </div>
              <?php if (!is_search_bot()): ?>
                <span>Нажимая «Проверить штрафы», вы даете согласие на обработку персональных данных на условиях <a href="https://shtraf.com/privacy/" target="_blank"
                    rel="noopener noreferrer">Политики конфиденциальности</a> и принимаете <a href="https://shtraf.com/polzovatelskoe-soglashenie/" target="_blank"
                    rel="noopener noreferrer">Пользовательское соглашение</a></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="free__img free__img_single">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/img/free-img.png" alt="">
      </div>
    </div>
  </div>
</section>