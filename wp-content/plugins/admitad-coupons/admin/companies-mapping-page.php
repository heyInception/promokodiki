<?php
// admin/companies-mapping-page.php

add_action('admin_menu', 'add_admitad_companies_page');
function add_admitad_companies_page()
{
    add_submenu_page(
        'edit.php?post_type=promocode',
        'Companies Mapping',
        'Companies Mapping',
        'manage_options',
        'admitad-companies',
        'render_admitad_companies_page'
    );
}

/**
 * Получить список уникальных компаний из существующих промокодов
 * @param bool $exclude_mapped Исключить уже замапленные компании
 */
function get_unique_companies_from_coupons($exclude_mapped = true)
{
    global $wpdb;

    // Получаем уникальные campaign_name из мета-полей
    $companies = $wpdb->get_col(
        "SELECT DISTINCT meta_value 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = 'campaign_name' 
         AND meta_value != '' 
         ORDER BY meta_value ASC"
    );

    // Исключаем уже замапленные компании
    if ($exclude_mapped) {
        $company_mapper = new Admitad_Company_Mapper();
        $mappings = $company_mapper->get_all_mappings();
        $mapped_company_names = array_map(function($m) {
            return $m->company_name;
        }, $mappings);
        
        $companies = array_diff($companies, $mapped_company_names);
    }

    return $companies;
}

/**
 * Получить компанию из API по ID купона
 */
function get_company_from_coupon_by_id($coupon_id)
{
    return get_post_meta($coupon_id, 'campaign_name', true);
}

function render_admitad_companies_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_companies = $wpdb->prefix . 'admitad_companies_mapping';
    $company_mapper = new Admitad_Company_Mapper();

    // Обработка сохранения
    if (isset($_POST['save_company'])) {
        check_admin_referer('save_company_action');

        $company_name = sanitize_text_field($_POST['company_name']);
        $category_id = intval($_POST['site_category_id']);
        $priority = intval($_POST['priority']);

        if ($company_name && $category_id) {
            $company_mapper->save_mapping($company_name, $category_id, $priority);
            echo '<div class="notice notice-success"><p>Company mapping saved!</p></div>';
        }
    }

    // Обработка удаления
    if (isset($_GET['delete_company']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_company_' . $_GET['delete_company'])) {
            $company_mapper->delete_mapping(intval($_GET['delete_company']));
            echo '<div class="notice notice-success"><p>Company mapping deleted!</p></div>';
        }
    }

    // Обработка автозаполнения из купона
    if (isset($_POST['load_from_coupon'])) {
        check_admin_referer('load_from_coupon_action');
        $coupon_id = intval($_POST['coupon_id']);
        if ($coupon_id) {
            $company_name = get_company_from_coupon_by_id($coupon_id);
            if ($company_name) {
                echo '<script>jQuery(document).ready(function($){ $("#company_name").val("' . esc_js($company_name) . '"); });</script>';
                echo '<div class="notice notice-info"><p>Company name loaded: ' . esc_html($company_name) . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>No company found for this coupon.</p></div>';
            }
        }
    }

    // Получаем все маппинги
    $mappings = $company_mapper->get_all_mappings();

    // Получаем все категории
    $categories = get_terms([
        'taxonomy' => 'promocode_category',
        'hide_empty' => false,
    ]);

    // Получаем список уникальных компаний из существующих купонов (исключая уже замапленные)
    $existing_companies = get_unique_companies_from_coupons(true);
    
    // Для статистики - получаем все компании без исключений
    $all_companies = get_unique_companies_from_coupons(false);

?>
    <div class="wrap">
        <h1>Companies to Category Mapping</h1>
        <p>Приоритет компаний выше, чем маппинг по заголовку и описанию.</p>

        <style>
            .company-suggestions {
                margin-top: 10px;
                padding: 10px;
                background: #f5f5f5;
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
            }

            .company-suggestions h4 {
                margin-top: 0;
                margin-bottom: 10px;
            }

            .company-tag {
                display: inline-block;
                background: #e0e0e0;
                padding: 4px 8px;
                margin: 4px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }

            .company-tag:hover {
                background: #0073aa;
                color: white;
            }

            .company-tag.selected {
                background: #0073aa;
                color: white;
            }
            
            .info-message {
                padding: 8px;
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                margin: 10px 0;
                font-size: 13px;
            }
        </style>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h3>Добавить отображение компании</h3>

            <!-- Загрузка из существующего купона -->
            <div style="margin-bottom: 20px; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                <h4>Загрузка с существующего купона:</h4>
                <form method="post" style="display: flex; gap: 10px; align-items: center;">
                    <?php wp_nonce_field('load_from_coupon_action'); ?>
                    <input type="number" name="coupon_id" placeholder="Coupon ID" style="width: 150px;">
                    <input type="submit" name="load_from_coupon" class="button button-secondary" value="Load Company Name">
                </form>
            </div>

            <form method="post" id="company_mapping_form">
                <?php wp_nonce_field('save_company_action'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="company_name">Название компаний (из Admitad)</label></th>
                        <td>
                            <input type="text" name="company_name" id="company_name" class="regular-text"
                                placeholder="e.g., Ozon, Wildberries, Samsung"
                                style="width: 100%;" autocomplete="off">

                            <!-- Список существующих компаний для выбора (только незамапленные) -->
                            <?php if (!empty($existing_companies)): ?>
                                <div class="company-suggestions">
                                    <h4>📋 Доступные компании из купонов (еще не отображены):</h4>
                                    <div id="company_suggestions_list">
                                        <?php foreach ($existing_companies as $company): ?>
                                            <span class="company-tag" onclick="selectCompany('<?php echo esc_js($company); ?>')">
                                                <?php echo esc_html($company); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="info-message">
                                    <?php 
                                    $unmapped_count = count($existing_companies);
                                    if ($unmapped_count === 0 && count($all_companies) > 0) {
                                        echo '✅ All companies from coupons have been mapped!';
                                    } else {
                                        echo 'No unmapped companies found in existing coupons. Run coupon import first or all companies are already mapped.';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>

                            <script>
                                function selectCompany(companyName) {
                                    document.getElementById('company_name').value = companyName;
                                    // Подсветка выбранного
                                    const tags = document.querySelectorAll('.company-tag');
                                    tags.forEach(tag => {
                                        if (tag.textContent === companyName) {
                                            tag.classList.add('selected');
                                        } else {
                                            tag.classList.remove('selected');
                                        }
                                    });
                                }

                                // Фильтр компаний при вводе
                                document.getElementById('company_name').addEventListener('input', function(e) {
                                    const searchTerm = e.target.value.toLowerCase();
                                    const tags = document.querySelectorAll('.company-tag');
                                    tags.forEach(tag => {
                                        if (tag.textContent.toLowerCase().includes(searchTerm) || searchTerm === '') {
                                            tag.style.display = 'inline-block';
                                        } else {
                                            tag.style.display = 'none';
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="site_category_id">Категории</label></th>
                        <td>
                            <select name="site_category_id" id="site_category_id" required style="width: 100%; max-width: 400px;">
                                <option value="">Выбирите категорию...</option>
                                <?php
                                // Функция для рекурсивного вывода категорий
                                function display_category_options($categories, $parent_id = 0, $level = 0)
                                {
                                    foreach ($categories as $cat) {
                                        if ($cat->parent == $parent_id) {
                                            $indent = str_repeat('—', $level) . ' ';
                                            echo '<option value="' . $cat->term_id . '">' . $indent . esc_html($cat->name) . ' (ID: ' . $cat->term_id . ')</option>';
                                            display_category_options($categories, $cat->term_id, $level + 1);
                                        }
                                    }
                                }
                                display_category_options($categories);
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="priority">Приоритет</label></th>
                        <td>
                            <input type="number" name="priority" id="priority" value="0" step="1" style="width: 100px;">
                            <p class="description">Компании с меньшим приоритетом будут проверяться первыми.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="save_company" class="button button-primary" value="Сохранить"></p>
            </form>
        </div>

        <div class="card" style="margin-top: 30px;">
            <h3>Существующие сопоставления компаний</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Название компании</th>
                        <th>Категория сайта</th>
                        <th width="80">Приоритет</th>
                        <th width="100">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mappings): ?>
                        <?php foreach ($mappings as $mapping): ?>
                            <?php $site_cat = get_term($mapping->site_subcategory_id, 'promocode_category'); ?>
                            <tr>
                                <td><?php echo $mapping->id; ?></td>
                                <td><strong><?php echo esc_html($mapping->company_name); ?></strong></td>
                                <td>
                                    <?php
                                    if ($site_cat && !is_wp_error($site_cat)) {
                                        // Показываем полный путь категории
                                        $ancestors = get_ancestors($site_cat->term_id, 'promocode_category');
                                        $path = array();
                                        foreach (array_reverse($ancestors) as $anc_id) {
                                            $anc_term = get_term($anc_id, 'promocode_category');
                                            if ($anc_term) {
                                                $path[] = $anc_term->name;
                                            }
                                        }
                                        $path[] = $site_cat->name;
                                        echo implode(' → ', array_map('esc_html', $path));
                                        echo ' (ID: ' . $mapping->site_subcategory_id . ')';
                                    } else {
                                        echo 'Category not found (ID: ' . $mapping->site_subcategory_id . ')';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $mapping->priority; ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(add_query_arg(['delete_company' => $mapping->id]), 'delete_company_' . $mapping->id); ?>"
                                        class="button button-small"
                                        onclick="return confirm('Delete this mapping?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No company mappings found. Add your first mapping above.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top: 30px;">
            <h3>📊 Statistics</h3>
            <ul>
                <li><strong>Total company mappings:</strong> <?php echo count($mappings); ?></li>
                <li><strong>Unique companies in coupons:</strong> <?php echo count($all_companies); ?></li>
                <li><strong>Companies without mapping:</strong>
                    <?php
                    $mapped_company_names = array_map(function ($m) {
                        return $m->company_name;
                    }, $mappings);
                    $unmapped = array_diff($all_companies, $mapped_company_names);
                    echo count($unmapped);
                    if (!empty($unmapped)) {
                        echo '<details style="margin-top: 10px;"><summary>Show unmapped companies</summary><ul style="margin-top: 5px;">';
                        foreach ($unmapped as $company) {
                            echo '<li>' . esc_html($company) . '</li>';
                        }
                        echo '</ul></details>';
                    }
                    ?>
                </li>
            </ul>
        </div>

        <div class="card" style="margin-top: 30px;">
            <h3>⚙️ How it works</h3>
            <ol>
                <li><strong>Приоритет 1 (высший):</strong> Проверка компании из поля <code>campaign_name</code> в таблице Companies Mapping</li>
                <li><strong>Приоритет 2:</strong> Ручной маппинг по фразам в заголовке</li>
                <li><strong>Приоритет 3:</strong> Ручной маппинг по фразам в описании</li>
                <li><strong>Приоритет 4:</strong> Автоматический подбор по заголовку (ключевые слова)</li>
                <li><strong>Приоритет 5:</strong> Автоматический подбор по описанию (с пониженным весом)</li>
                <li><strong>Приоритет 6 (низший):</strong> Категория "Прочее"</li>
            </ol>
        </div>
    </div>
    <div style="margin: 20px 0;">
        <a href="<?php echo wp_nonce_url(admin_url('?force_reassign_categories=1'), 'force_reassign_categories'); ?>"
            class="button button-secondary"
            style="background: #dc3232; color: white; border-color: #dc3232;"
            onclick="return confirm('ВНИМАНИЕ! Это действие переприсвоит категории для ВСЕХ существующих промокодов на основе текущих правил маппинга. Продолжить?')">
            🔄 Принудительно переприсвоить категории для всех промокодов
        </a>
        <span style="margin-left: 10px; color: #666;">Используйте после добавления новых правил маппинга компаний</span>
    </div>
    <script>
        // Добавляем поиск по компаниям
        jQuery(document).ready(function($) {
            const searchInput = $('#company_name');
            const suggestionsList = $('#company_suggestions_list');

            if (searchInput.length && suggestionsList.length) {
                // Оригинальные теги
                const originalTags = suggestionsList.html();

                // Функция фильтрации
                searchInput.on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();

                    if (searchTerm === '') {
                        suggestionsList.html(originalTags);
                        return;
                    }

                    const filtered = $(originalTags).filter(function() {
                        return $(this).text().toLowerCase().includes(searchTerm);
                    });

                    if (filtered.length > 0) {
                        suggestionsList.html(filtered);
                    } else {
                        suggestionsList.html('<div style="padding: 8px; color: #666;">No matching companies found. You can add a new one.</div>');
                    }
                });
            }
        });
    </script>
<?php
}
// AJAX обработчик для поиска компаний
add_action('wp_ajax_search_admitad_companies', 'ajax_search_admitad_companies');
function ajax_search_admitad_companies()
{
    check_ajax_referer('search_companies_nonce', 'nonce');

    $search = sanitize_text_field($_POST['search']);
    $limit = intval($_POST['limit']) ?: 20;

    global $wpdb;

    $companies = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = 'campaign_name' 
         AND meta_value != '' 
         AND meta_value LIKE %s
         ORDER BY meta_value ASC 
         LIMIT %d",
        '%' . $wpdb->esc_like($search) . '%',
        $limit
    ));

    wp_send_json_success($companies);
}