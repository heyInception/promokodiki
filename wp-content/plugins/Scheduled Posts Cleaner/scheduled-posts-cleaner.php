<?php
/**
 * Plugin Name: Scheduled Posts Cleaner
 * Plugin URI: https://example.com/
 * Description: Удаляет запланированные посты выбранных типов
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class ScheduledPostsCleaner {
    
    private $options;
    private $cron_hook = 'scheduled_posts_cleaner_cron';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_delete_scheduled_posts', array($this, 'handle_manual_delete'));
        add_action($this->cron_hook, array($this, 'process_cleanup'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
        
        // Загрузка опций
        $this->options = get_option('scheduled_posts_cleaner_options', array());
        
        // Активация/деактивация
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // AJAX для сохранения настроек
        add_action('wp_ajax_save_cleaner_settings', array($this, 'save_settings_ajax'));
    }
    
    public function activate() {
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'daily', $this->cron_hook);
        }
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook($this->cron_hook);
    }
    
    public function add_custom_cron_intervals($schedules) {
        $intervals = array(
            '3_hours' => array(
                'interval' => 3 * HOUR_IN_SECONDS,
                'display' => __('Каждые 3 часа', 'scheduled-posts-cleaner')
            ),
            '6_hours' => array(
                'interval' => 6 * HOUR_IN_SECONDS,
                'display' => __('Каждые 6 часов', 'scheduled-posts-cleaner')
            ),
            '12_hours' => array(
                'interval' => 12 * HOUR_IN_SECONDS,
                'display' => __('Каждые 12 часов', 'scheduled-posts-cleaner')
            )
        );
        
        return array_merge($schedules, $intervals);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Очистка запланированных постов',
            'Очистка постов',
            'manage_options',
            'scheduled-posts-cleaner',
            array($this, 'admin_page'),
            'dashicons-trash',
            30
        );
    }
    
    public function register_settings() {
        register_setting('scheduled_posts_cleaner_group', 'scheduled_posts_cleaner_options');
    }
    
    public function admin_page() {
        $options = get_option('scheduled_posts_cleaner_options', array());
        $post_types = $this->get_post_types();
        ?>
        <div class="wrap">
            <h1>Очистка запланированных постов</h1>
            
            <?php if (isset($_GET['status'])): ?>
                <div class="notice notice-<?php echo $_GET['status'] == 'success' ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="admin-post.php" id="cleaner-settings-form">
                <input type="hidden" name="action" value="delete_scheduled_posts">
                <?php wp_nonce_field('scheduled_posts_cleaner_action', 'scheduled_posts_cleaner_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Типы постов для удаления</th>
                        <td>
                            <?php foreach ($post_types as $post_type): ?>
                                <label>
                                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" 
                                        <?php echo $this->is_option_checked('post_types', $post_type->name) ? 'checked' : ''; ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Интервал очистки (CRON)</th>
                        <td>
                            <label>
                                <input type="radio" name="cron_interval" value="3_hours" 
                                    <?php echo isset($options['cron_interval']) && $options['cron_interval'] == '3_hours' ? 'checked' : ''; ?>>
                                Каждые 3 часа
                            </label><br>
                            <label>
                                <input type="radio" name="cron_interval" value="6_hours" 
                                    <?php echo isset($options['cron_interval']) && $options['cron_interval'] == '6_hours' ? 'checked' : ''; ?>>
                                Каждые 6 часов
                            </label><br>
                            <label>
                                <input type="radio" name="cron_interval" value="12_hours" 
                                    <?php echo isset($options['cron_interval']) && $options['cron_interval'] == '12_hours' ? 'checked' : ''; ?>>
                                Каждые 12 часов
                            </label><br>
                            <label>
                                <input type="radio" name="cron_interval" value="custom" 
                                    <?php echo isset($options['cron_interval']) && $options['cron_interval'] == 'custom' ? 'checked' : ''; ?>>
                                Свой интервал (в часах):
                                <input type="number" name="custom_interval" value="<?php echo isset($options['custom_interval']) ? esc_attr($options['custom_interval']) : '24'; ?>" min="1" max="720">
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Действие при удалении</th>
                        <td>
                            <label>
                                <input type="radio" name="delete_action" value="trash" 
                                    <?php echo isset($options['delete_action']) && $options['delete_action'] == 'trash' ? 'checked' : ''; ?>>
                                В корзину
                            </label><br>
                            <label>
                                <input type="radio" name="delete_action" value="permanent" 
                                    <?php echo isset($options['delete_action']) && $options['delete_action'] == 'permanent' ? 'checked' : ''; ?>>
                                Удалить навсегда
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="save_settings" class="button button-primary">Сохранить настройки</button>
                    <button type="submit" name="delete_now" class="button button-secondary" style="background: #dc3232; color: white; border-color: #dc3232;">Удалить сейчас</button>
                </p>
            </form>
            
            <div id="delete-progress" style="display:none; margin-top: 20px;">
                <h3>Процесс удаления...</h3>
                <div id="progress-bar" style="width: 100%; background: #f0f0f0; height: 20px; border-radius: 5px; overflow: hidden;">
                    <div id="progress-fill" style="width: 0%; height: 100%; background: #4CAF50; transition: width 0.3s;"></div>
                </div>
                <p id="progress-status">Подготовка...</p>
            </div>
        </div>
        <?php
    }
    
    private function get_post_types() {
        $exclude = array('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset');
        return get_post_types(array(
            'public' => true,
            'show_ui' => true
        ), 'objects');
    }
    
    private function is_option_checked($option_name, $value) {
        $options = get_option('scheduled_posts_cleaner_options', array());
        if (isset($options[$option_name]) && is_array($options[$option_name])) {
            return in_array($value, $options[$option_name]);
        }
        return false;
    }
    
    public function handle_manual_delete() {
        // Проверка nonce
        if (!isset($_POST['scheduled_posts_cleaner_nonce']) || 
            !wp_verify_nonce($_POST['scheduled_posts_cleaner_nonce'], 'scheduled_posts_cleaner_action')) {
            wp_die('Security check failed');
        }
        
        // Сохранение настроек
        if (isset($_POST['save_settings'])) {
            $this->save_settings($_POST);
            wp_redirect(add_query_arg(array(
                'page' => 'scheduled-posts-cleaner',
                'status' => 'success',
                'message' => urlencode('Настройки сохранены')
            ), admin_url('admin.php')));
            exit;
        }
        
        // Удаление сейчас
        if (isset($_POST['delete_now'])) {
            $result = $this->delete_scheduled_posts($_POST);
            wp_redirect(add_query_arg(array(
                'page' => 'scheduled-posts-cleaner',
                'status' => $result['success'] ? 'success' : 'error',
                'message' => urlencode($result['message'])
            ), admin_url('admin.php')));
            exit;
        }
    }
    
    private function save_settings($data) {
        $options = array();
        
        // Сохраняем типы постов
        if (isset($data['post_types']) && is_array($data['post_types'])) {
            $options['post_types'] = array_map('sanitize_text_field', $data['post_types']);
        } else {
            $options['post_types'] = array();
        }
        
        // Сохраняем интервал CRON
        if (isset($data['cron_interval'])) {
            $options['cron_interval'] = sanitize_text_field($data['cron_interval']);
            if ($options['cron_interval'] == 'custom' && isset($data['custom_interval'])) {
                $options['custom_interval'] = intval($data['custom_interval']);
            }
            
            // Обновляем расписание CRON
            $this->update_cron_schedule($options);
        }
        
        // Сохраняем действие
        if (isset($data['delete_action'])) {
            $options['delete_action'] = sanitize_text_field($data['delete_action']);
        }
        
        update_option('scheduled_posts_cleaner_options', $options);
        $this->options = $options;
    }
    
    private function update_cron_schedule($options) {
        // Отключаем старую задачу
        wp_clear_scheduled_hook($this->cron_hook);
        
        // Определяем интервал
        if ($options['cron_interval'] == 'custom') {
            $interval = isset($options['custom_interval']) ? $options['custom_interval'] * HOUR_IN_SECONDS : 24 * HOUR_IN_SECONDS;
        } else {
            switch ($options['cron_interval']) {
                case '3_hours':
                    $interval = 3 * HOUR_IN_SECONDS;
                    break;
                case '6_hours':
                    $interval = 6 * HOUR_IN_SECONDS;
                    break;
                case '12_hours':
                    $interval = 12 * HOUR_IN_SECONDS;
                    break;
                default:
                    $interval = 24 * HOUR_IN_SECONDS;
            }
        }
        
        // Добавляем задачу
        wp_schedule_event(time() + $interval, $options['cron_interval'], $this->cron_hook);
    }
    
    public function process_cleanup() {
        $options = get_option('scheduled_posts_cleaner_options', array());
        $this->delete_scheduled_posts($options);
    }
    
    private function delete_scheduled_posts($options) {
        // Получаем типы постов
        $post_types = isset($options['post_types']) ? $options['post_types'] : array();
        if (empty($post_types)) {
            return array(
                'success' => false,
                'message' => 'Не выбраны типы постов для удаления'
            );
        }
        
        // Получаем запланированные посты
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'future',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        $deleted_count = 0;
        
        if (empty($posts)) {
            return array(
                'success' => true,
                'message' => 'Нет запланированных постов для удаления'
            );
        }
        
        // Действие при удалении
        $delete_action = isset($options['delete_action']) ? $options['delete_action'] : 'trash';
        $force_delete = ($delete_action == 'permanent');
        
        foreach ($posts as $post_id) {
            $result = wp_delete_post($post_id, $force_delete);
            if ($result) {
                $deleted_count++;
            }
        }
        
        return array(
            'success' => true,
            'message' => "Удалено {$deleted_count} запланированных постов"
        );
    }
    
    public function save_settings_ajax() {
        // AJAX обработчик для сохранения настроек
        // (Можно добавить при необходимости)
    }
}

// Инициализация плагина
new ScheduledPostsCleaner();
?>