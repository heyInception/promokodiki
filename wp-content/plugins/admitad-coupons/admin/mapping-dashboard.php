<?php
// admin/mapping-dashboard.php

// Добавляем страницу для управления таблицами
add_action('admin_menu', 'add_admitad_tools_page');
function add_admitad_tools_page()
{
    add_submenu_page(
        'edit.php?post_type=promocode',
        'Admitad Tools',
        'Admitad Tools',
        'manage_options',
        'admitad-tools',
        'render_admitad_tools_page'
    );
}

function render_admitad_tools_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Принудительное создание таблиц
    if (isset($_POST['create_tables']) && check_admin_referer('admitad_tools_action')) {
        admitad_force_create_tables();
    }

    // Проверка существования таблиц
    global $wpdb;
    $table_mapping = $wpdb->prefix . 'admitad_category_mapping';
    $table_keywords = $wpdb->prefix . 'subcategory_keywords';

    $mapping_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_mapping}'") == $table_mapping;
    $keywords_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_keywords}'") == $table_keywords;

?>
    <div class="wrap">
        <h1>Admitad Tools</h1>

        <div class="card" style="max-width: 600px;">
            <h2>Database Tables Status</h2>
            <table class="widefat striped">
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td><code><?php echo $table_mapping; ?></code></td>
                    <td><?php echo $mapping_exists ? '✅ Exists' : '❌ Missing'; ?></td>
                </tr>
                <tr>
                    <td><code><?php echo $table_keywords; ?></code></td>
                    <td><?php echo $keywords_exists ? '✅ Exists' : '❌ Missing'; ?></td>
                </tr>
            </table>

            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('admitad_tools_action'); ?>
                <input type="submit" name="create_tables" class="button button-primary" value="Create Missing Tables">
            </form>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Quick Test</h2>
            <p>After creating tables, go to <strong>Category Mapping → Subcategory Keywords</strong> to add keywords.</p>
        </div>
    </div>
<?php
}

// Добавляем страницу в админку
add_action('admin_menu', 'add_admitad_mapping_page');
function add_admitad_mapping_page()
{
    add_submenu_page(
        'edit.php?post_type=promocode',
        'Category Mapping',
        'Category Mapping',
        'manage_options',
        'admitad-mapping',
        'render_admitad_mapping_page'
    );
}

function render_admitad_mapping_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'manual';

?>
    <div class="wrap">
        <h1>Category Mapping - Admitad to Site Categories</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?post_type=promocode&page=admitad-mapping&tab=manual" class="nav-tab <?php echo $active_tab == 'manual' ? 'nav-tab-active' : ''; ?>">Manual Mapping</a>
            <a href="?post_type=promocode&page=admitad-mapping&tab=keywords" class="nav-tab <?php echo $active_tab == 'keywords' ? 'nav-tab-active' : ''; ?>">Subcategory Keywords</a>
            <a href="?post_type=promocode&page=admitad-mapping&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Unmatched Logs</a>
            <a href="?post_type=promocode&page=admitad-mapping&tab=test" class="nav-tab <?php echo $active_tab == 'test' ? 'nav-tab-active' : ''; ?>">Test Matcher</a>
        </h2>

        <?php
        switch ($active_tab) {
            case 'manual':
                render_manual_mapping_tab();
                break;
            case 'keywords':
                render_keywords_tab();
                break;
            case 'logs':
                render_logs_tab();
                break;
            case 'test':
                render_test_tab();
                break;
            default:
                render_manual_mapping_tab();
        }
        echo '<div style="margin: 20px 0;">';
        echo '<a href="' . wp_nonce_url(admin_url('?force_update_categories=1'), 'force_update_categories') . '" 
       class="button button-secondary" 
       onclick="return confirm(\'Обновить категории для всех существующих промокодов? Это может занять время.\')">
       Принудительно обновить категории всех промокодов
      </a>';
        echo '</div>';
        ?>
    </div>
<?php
}

// Таб 1: Ручной маппинг
function render_manual_mapping_tab()
{
    global $wpdb;
    $table_mapping = $wpdb->prefix . 'admitad_category_mapping';

    // Обработка добавления/редактирования
    if (isset($_POST['save_mapping'])) {
        check_admin_referer('save_mapping_action');

        $admitad_category = sanitize_text_field($_POST['admitad_category']);
        $site_category_id = intval($_POST['site_category_id']);
        $priority = intval($_POST['priority']);

        if ($admitad_category && $site_category_id) {
            $wpdb->replace(
                $table_mapping,
                [
                    'admitad_category_name' => $admitad_category,
                    'site_subcategory_id' => $site_category_id,
                    'priority' => $priority
                ],
                ['%s', '%d', '%d']
            );
            echo '<div class="notice notice-success"><p>Mapping saved successfully!</p></div>';
        }
    }

    // Обработка удаления
    if (isset($_GET['delete_mapping']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_mapping_' . $_GET['delete_mapping'])) {
            $wpdb->delete($table_mapping, ['id' => intval($_GET['delete_mapping'])], ['%d']);
            echo '<div class="notice notice-success"><p>Mapping deleted!</p></div>';
        }
    }

    // Получаем существующие маппинги
    $mappings = $wpdb->get_results("SELECT * FROM {$table_mapping} ORDER BY priority ASC, admitad_category_name ASC");

    // Получаем все подкатегории
    $categories = get_terms([
        'taxonomy' => 'promocode_category',
        'hide_empty' => false,
    ]);

?>
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h3>Add New Manual Mapping</h3>
        <form method="post">
            <?php wp_nonce_field('save_mapping_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="admitad_category">Admitad Category Name</label></th>
                    <td><input type="text" name="admitad_category" id="admitad_category" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="site_category_id">Site Subcategory</label></th>
                    <td>
                        <select name="site_category_id" id="site_category_id" required>
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->term_id; ?>">
                                    <?php echo str_repeat('—', $cat->depth) . ' ' . esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="priority">Priority (lower = higher)</label></th>
                    <td><input type="number" name="priority" id="priority" value="0" step="1"></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="save_mapping" class="button button-primary" value="Save Mapping"></p>
        </form>
    </div>

    <div class="card" style="margin-top: 30px;">
        <h3>Existing Manual Mappings</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admitad Category</th>
                    <th>Site Category</th>
                    <th>Priority</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($mappings): ?>
                    <?php foreach ($mappings as $mapping): ?>
                        <?php $site_cat = get_term($mapping->site_subcategory_id, 'promocode_category'); ?>
                        <tr>
                            <td><?php echo $mapping->id; ?></td>
                            <td><?php echo esc_html($mapping->admitad_category_name); ?></td>
                            <td><?php echo $site_cat ? esc_html($site_cat->name) : 'Deleted'; ?></td>
                            <td><?php echo $mapping->priority; ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['delete_mapping' => $mapping->id]), 'delete_mapping_' . $mapping->id); ?>"
                                    class="button button-small"
                                    onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No manual mappings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

// Таб 2: Ключевые слова подкатегорий
function render_keywords_tab()
{
    global $wpdb;
    $table_keywords = $wpdb->prefix . 'subcategory_keywords';

    // Обработка добавления ключевого слова
    if (isset($_POST['add_keyword'])) {
        check_admin_referer('add_keyword_action');

        $subcategory_id = intval($_POST['subcategory_id']);
        $keyword = sanitize_text_field($_POST['keyword']);
        $weight = intval($_POST['weight']);

        if ($subcategory_id && $keyword) {
            $wpdb->insert(
                $table_keywords,
                [
                    'site_subcategory_id' => $subcategory_id,
                    'keyword' => $keyword,
                    'weight' => $weight
                ],
                ['%d', '%s', '%d']
            );
            echo '<div class="notice notice-success"><p>Keyword added!</p></div>';
        }
    }

    // Обработка удаления
    if (isset($_GET['delete_keyword']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_keyword_' . $_GET['delete_keyword'])) {
            $wpdb->delete($table_keywords, ['id' => intval($_GET['delete_keyword'])], ['%d']);
            echo '<div class="notice notice-success"><p>Keyword deleted!</p></div>';
        }
    }

    // Получаем все подкатегории
    $categories = get_terms([
        'taxonomy' => 'promocode_category',
        'hide_empty' => false,
    ]);

    // Получаем все ключевые слова
    $keywords = $wpdb->get_results("
        SELECT kw.*, t.name as category_name 
        FROM {$table_keywords} kw
        LEFT JOIN {$wpdb->terms} t ON kw.site_subcategory_id = t.term_id
        ORDER BY t.name ASC, kw.weight DESC
    ");

?>
    <div class="card" style="max-width: 600px; margin-top: 20px;">
        <h3>Add Keyword for Subcategory</h3>
        <form method="post">
            <?php wp_nonce_field('add_keyword_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="subcategory_id">Subcategory</label></th>
                    <td>
                        <select name="subcategory_id" id="subcategory_id" required>
                            <option value="">Select subcategory...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat->term_id; ?>">
                                    <?php echo str_repeat('—', $cat->depth) . ' ' . esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="keyword">Keyword</label></th>
                    <td><input type="text" name="keyword" id="keyword" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="weight">Weight (higher = stronger match)</label></th>
                    <td><input type="number" name="weight" id="weight" value="1" step="1"></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="add_keyword" class="button button-primary" value="Add Keyword"></p>
        </form>
    </div>

    <div class="card" style="margin-top: 30px;">
        <h3>Existing Keywords</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subcategory</th>
                    <th>Keyword</th>
                    <th>Weight</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($keywords): ?>
                    <?php foreach ($keywords as $kw): ?>
                        <tr>
                            <td><?php echo $kw->id; ?></td>
                            <td><?php echo esc_html($kw->category_name); ?></td>
                            <td><?php echo esc_html($kw->keyword); ?></td>
                            <td><?php echo $kw->weight; ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['delete_keyword' => $kw->id]), 'delete_keyword_' . $kw->id); ?>"
                                    class="button button-small"
                                    onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No keywords found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

// Таб 3: Логи несопоставленных купонов
function render_logs_tab()
{
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/admitad-logs/unmatched-categories.json';

    // Очистка логов
    if (isset($_POST['clear_logs'])) {
        check_admin_referer('clear_logs_action');
        file_put_contents($log_file, json_encode([]));
        echo '<div class="notice notice-success"><p>Logs cleared!</p></div>';
    }

    $logs = [];
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        $logs = json_decode($content, true) ?: [];
    }

?>
    <div style="margin-top: 20px;">
        <form method="post" style="margin-bottom: 20px;">
            <?php wp_nonce_field('clear_logs_action'); ?>
            <input type="submit" name="clear_logs" class="button button-secondary" value="Clear All Logs" onclick="return confirm('Are you sure?')">
        </form>

        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p>No unmatched coupons logged.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Coupon ID</th>
                        <th>Admitad Categories</th>
                        <th>Assigned Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['timestamp']); ?></td>
                            <td><?php echo $log['coupon_id'] ? esc_html($log['coupon_id']) : 'N/A'; ?></td>
                            <td>
                                <?php
                                if (!empty($log['admitad_categories'])) {
                                    $cat_names = array_column($log['admitad_categories'], 'name');
                                    echo esc_html(implode(', ', $cat_names));
                                } else {
                                    echo 'Empty';
                                }
                                ?>
                            </td>
                            <td><?php
                                $term = get_term($log['assigned_category_id'], 'promocode_category');
                                echo $term ? esc_html($term->name) : 'Category ' . $log['assigned_category_id'];
                                ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
}

// Таб 4: Тестирование маппера
function render_test_tab()
{
?>
    <div class="card" style="max-width: 600px; margin-top: 20px;">
        <h3>Test Category Matcher</h3>
        <form method="post">
            <?php wp_nonce_field('test_mapper_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="test_category">Admitad Category Name</label></th>
                    <td>
                        <input type="text" name="test_category" id="test_category" class="regular-text" required>
                        <p class="description">Enter an Admitad category name to see which site subcategory it would match to.</p>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="run_test" class="button button-primary" value="Test Match"></p>
        </form>

        <?php
        if (isset($_POST['run_test']) && isset($_POST['test_category'])) {
            check_admin_referer('test_mapper_action');

            $test_category = sanitize_text_field($_POST['test_category']);
            $mapper = new Admitad_Category_Mapper();

            // Создаем фейковый массив категорий для теста
            $fake_categories = [['name' => $test_category]];
            $result = $mapper->get_site_subcategory($fake_categories, null);

            $term = get_term($result, 'promocode_category');

            echo '<div class="notice notice-info" style="margin-top: 20px;">';
            echo '<h4>Test Result:</h4>';
            echo '<p><strong>Input:</strong> ' . esc_html($test_category) . '</p>';
            echo '<p><strong>Matched to:</strong> ' . ($term ? esc_html($term->name) : 'Category ID: ' . $result) . '</p>';
            echo '<p><strong>Category ID:</strong> ' . $result . '</p>';
            echo '</div>';
        }
        ?>
    </div>
<?php
}
