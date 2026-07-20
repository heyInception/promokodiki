<?php
// includes/class-company-mapper.php

if (!class_exists('Admitad_Company_Mapper')) {
    
    class Admitad_Company_Mapper {
        
        private $table_companies;
        private $taxonomy = 'promocode_category';
        
        public function __construct() {
            global $wpdb;
            $this->table_companies = $wpdb->prefix . 'admitad_companies_mapping';
        }
        
        /**
         * Проверяем, есть ли компания в ручном маппинге
         * @param string $campaign_name - название компании/кампании из API
         * @return int|null - ID категории или null
         */
        public function get_category_by_company($campaign_name) {
            global $wpdb;
            
            if (empty($campaign_name)) {
                return null;
            }
            
            $campaign_name = trim($campaign_name);
            
            // Ищем точное совпадение
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT site_subcategory_id FROM {$this->table_companies} 
                 WHERE company_name = %s 
                 ORDER BY priority ASC LIMIT 1",
                $campaign_name
            ));
            
            if ($mapping) {
                error_log(sprintf('Company mapping found: "%s" -> category ID %d', 
                    $campaign_name, $mapping->site_subcategory_id));
                return (int) $mapping->site_subcategory_id;
            }
            
            // Поиск по частичному совпадению (если не найдено точное)
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT site_subcategory_id, company_name FROM {$this->table_companies} 
                 WHERE %s LIKE CONCAT('%%', company_name, '%%')
                 ORDER BY priority ASC, LENGTH(company_name) DESC LIMIT 1",
                $campaign_name
            ));
            
            if ($mapping) {
                error_log(sprintf('Partial company mapping found: "%s" matches "%s" -> category ID %d',
                    $campaign_name, $mapping->company_name, $mapping->site_subcategory_id));
                return (int) $mapping->site_subcategory_id;
            }
            
            return null;
        }
        
        /**
         * Получить все маппинги компаний
         */
        public function get_all_mappings() {
            global $wpdb;
            return $wpdb->get_results(
                "SELECT * FROM {$this->table_companies} ORDER BY priority ASC, company_name ASC"
            );
        }
        
        /**
         * Добавить или обновить маппинг компании
         */
        public function save_mapping($company_name, $subcategory_id, $priority = 0) {
            global $wpdb;
            
            return $wpdb->replace(
                $this->table_companies,
                [
                    'company_name' => trim($company_name),
                    'site_subcategory_id' => (int) $subcategory_id,
                    'priority' => (int) $priority
                ],
                ['%s', '%d', '%d']
            );
        }
        
        /**
         * Удалить маппинг компании
         */
        public function delete_mapping($id) {
            global $wpdb;
            return $wpdb->delete($this->table_companies, ['id' => (int) $id], ['%d']);
        }
    }
}