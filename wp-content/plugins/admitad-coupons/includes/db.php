<?php
// includes/db.php - создание и управление таблицами

// Функция создания таблиц при активации
function admitad_create_tables() {
    global $wpdb;
    
    // Подключаем файл с dbDelta если его нет
    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Таблица ручного маппинга категорий
    $table_mapping = $wpdb->prefix . 'admitad_category_mapping';
    $sql_mapping = "CREATE TABLE IF NOT EXISTS {$table_mapping} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admitad_category_name VARCHAR(255) NOT NULL,
        site_subcategory_id INT NOT NULL,
        priority INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admitad_category (admitad_category_name),
        INDEX idx_site_subcategory (site_subcategory_id)
    ) {$charset_collate};";
    
    // Таблица ключевых слов подкатегорий
    $table_keywords = $wpdb->prefix . 'subcategory_keywords';
    $sql_keywords = "CREATE TABLE IF NOT EXISTS {$table_keywords} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site_subcategory_id INT NOT NULL,
        keyword VARCHAR(255) NOT NULL,
        weight INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_subcategory (site_subcategory_id),
        INDEX idx_keyword (keyword)
    ) {$charset_collate};";
    
    // Таблица компаний
    $table_companies = $wpdb->prefix . 'admitad_companies_mapping';
    $sql_companies = "CREATE TABLE IF NOT EXISTS {$table_companies} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL,
        site_subcategory_id INT NOT NULL,
        priority INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_company (company_name),
        INDEX idx_subcategory (site_subcategory_id)
    ) {$charset_collate};";
    
    // Выполняем создание таблиц
    dbDelta($sql_mapping);
    dbDelta($sql_keywords);
    dbDelta($sql_companies);
    
    error_log('Admitad tables created/updated successfully');
}

// Функция удаления таблиц при деактивации (опционально)
function admitad_drop_tables() {
    global $wpdb;
    
    $table_mapping = $wpdb->prefix . 'admitad_category_mapping';
    $table_keywords = $wpdb->prefix . 'subcategory_keywords';
    $table_companies = $wpdb->prefix . 'admitad_companies_mapping';
    
    // Раскомментировать если нужно удалять таблицы при деактивации
    // $wpdb->query("DROP TABLE IF EXISTS {$table_mapping}");
    // $wpdb->query("DROP TABLE IF EXISTS {$table_keywords}");
    // $wpdb->query("DROP TABLE IF EXISTS {$table_companies}");
}