<?php
// includes/class-category-mapper.php

if (!class_exists('Admitad_Category_Mapper')) {
    
    class Admitad_Category_Mapper {
        
        private $table_mapping;
        private $table_keywords;
        private $log_file;
        private $default_category_slug = 'other';
        private $taxonomy = 'promocode_category';
        
        public function __construct() {
            global $wpdb;
            $this->table_mapping = $wpdb->prefix . 'admitad_category_mapping';
            $this->table_keywords = $wpdb->prefix . 'subcategory_keywords';
            
            // Создаем директорию для логов
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/admitad-logs/';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            $this->log_file = $log_dir . 'unmatched-categories.json';
        }
        
        /**
         * ОСНОВНОЙ МЕТОД - с приоритетом заголовка над описанием и компаниями
         */
        public function get_site_subcategory_by_text($coupon_title, $coupon_description, $campaign_name = '', $coupon_id = null) {
            
            $title = mb_strtolower(trim($coupon_title));
            $description = mb_strtolower(trim($coupon_description));
            
            error_log(sprintf(
                'Mapping coupon ID %s. Company: %s | Title: %s',
                $coupon_id,
                $campaign_name,
                mb_substr($title, 0, 100)
            ));
            
            // ПРИОРИТЕТ 1: Проверяем компанию (высший приоритет)
            if (!empty($campaign_name)) {
                require_once ADMITAD_PLUGIN_DIR . 'includes/class-company-mapper.php';
                $company_mapper = new Admitad_Company_Mapper();
                $company_category_id = $company_mapper->get_category_by_company($campaign_name);
                
                if ($company_category_id) {
                    error_log(sprintf('COMPANY PRIORITY: Coupon %d assigned to category %d via company "%s"', 
                        $coupon_id, $company_category_id, $campaign_name));
                    $this->log_match($coupon_id, "Company: {$campaign_name}", $company_category_id, 'company_priority');
                    return $company_category_id;
                }
            }
            
            // ПРИОРИТЕТ 2: Проверяем ручной маппинг в заголовке
            $mapped_id = $this->check_manual_mapping_by_text($title);
            if ($mapped_id) {
                error_log(sprintf('Manual mapping found in TITLE for coupon %d: category %d', $coupon_id, $mapped_id));
                $this->log_match($coupon_id, $title, $mapped_id, 'manual_title');
                return $mapped_id;
            }
            
            // ПРИОРИТЕТ 3: Проверяем ручной маппинг в описании
            if (!empty($description)) {
                $mapped_id = $this->check_manual_mapping_by_text($description);
                if ($mapped_id) {
                    error_log(sprintf('Manual mapping found in DESCRIPTION for coupon %d: category %d', $coupon_id, $mapped_id));
                    $this->log_match($coupon_id, $description, $mapped_id, 'manual_description');
                    return $mapped_id;
                }
            }
            
            // ПРИОРИТЕТ 4: Автоматический подбор по заголовку
            $best_match = $this->auto_match_by_text($title, 'title');
            if ($best_match && $best_match['score'] > 0) {
                error_log(sprintf('Auto mapping found in TITLE for coupon %d: category %d with score %d', 
                    $coupon_id, $best_match['id'], $best_match['score']));
                $this->log_match($coupon_id, $title, $best_match['id'], 'auto_title', $best_match['score']);
                return $best_match['id'];
            }
            
            // ПРИОРИТЕТ 5: Автоматический подбор по описанию (с меньшим весом)
            if (!empty($description)) {
                $best_match = $this->auto_match_by_text($description, 'description');
                if ($best_match && $best_match['score'] > 0) {
                    // Уменьшаем вес для описания на 30%
                    $best_match['score'] = round($best_match['score'] * 0.7);
                    error_log(sprintf('Auto mapping found in DESCRIPTION for coupon %d: category %d with score %d (reduced by 30%%)', 
                        $coupon_id, $best_match['id'], $best_match['score']));
                    $this->log_match($coupon_id, $description, $best_match['id'], 'auto_description', $best_match['score']);
                    return $best_match['id'];
                }
            }
            
            // ПРИОРИТЕТ 6: Fallback - категория "Прочее"
            $default_id = $this->get_default_category_id();
            error_log(sprintf('No mapping found for coupon %d, using default category %d', $coupon_id, $default_id));
            $this->log_unmatched($coupon_id, $title . ' | ' . $description, $default_id);
            return $default_id;
        }
        
        /**
         * Проверка ручного маппинга по тексту
         */
        private function check_manual_mapping_by_text($search_text) {
            global $wpdb;
            
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT site_subcategory_id FROM {$this->table_mapping} 
                 WHERE %s LIKE CONCAT('%%', admitad_category_name, '%%')
                 ORDER BY priority ASC LIMIT 1",
                $search_text
            ));
            
            return $mapping ? (int) $mapping->site_subcategory_id : null;
        }
        
        /**
         * Автоматический подбор на основе ключевых слов
         */
        private function auto_match_by_text($search_text, $source = 'unknown') {
            global $wpdb;
            
            // Получаем все ключевые слова из базы
            $all_keywords = $wpdb->get_results(
                "SELECT site_subcategory_id, keyword, weight 
                 FROM {$this->table_keywords}
                 ORDER BY LENGTH(keyword) DESC"
            );
            
            if (empty($all_keywords)) {
                error_log('No keywords found in database');
                return null;
            }
            
            $scores = [];
            $matched_keywords = [];
            
            foreach ($all_keywords as $kw) {
                $keyword = mb_strtolower(trim($kw->keyword));
                $weight = (int) $kw->weight;
                $subcat_id = (int) $kw->site_subcategory_id;
                
                // Проверяем существование подкатегории
                $term = get_term($subcat_id, $this->taxonomy);
                if (!$term || is_wp_error($term)) {
                    continue;
                }
                
                // Поиск с учетом границ слов
                $pattern = '/\b' . preg_quote($keyword, '/') . '\b/u';
                if (preg_match($pattern, $search_text)) {
                    if (!isset($scores[$subcat_id])) {
                        $scores[$subcat_id] = 0;
                    }
                    $scores[$subcat_id] += $weight;
                    $matched_keywords[$subcat_id][] = $keyword;
                    
                    error_log(sprintf('Keyword match in %s: "%s" (weight %d) -> subcategory "%s" (ID: %d), score now %d',
                        $source, $keyword, $weight, $term->name, $subcat_id, $scores[$subcat_id]));
                }
            }
            
            // Бонус за множественные совпадения
            foreach ($scores as $subcat_id => $score) {
                $match_count = count($matched_keywords[$subcat_id] ?? []);
                if ($match_count > 1) {
                    $bonus = $match_count * 5;
                    $scores[$subcat_id] += $bonus;
                    error_log(sprintf('Multiple matches bonus in %s: %d keywords -> +%d points', 
                        $source, $match_count, $bonus));
                }
            }
            
            if (empty($scores)) {
                return null;
            }
            
            arsort($scores);
            $best_id = key($scores);
            $best_score = $scores[$best_id];
            
            return [
                'id' => $best_id,
                'score' => $best_score
            ];
        }
        
        /**
         * Получение всех подкатегорий
         */
        private function get_all_subcategories() {
            $terms = get_terms([
                'taxonomy' => $this->taxonomy,
                'hide_empty' => false,
            ]);
            
            if (is_wp_error($terms)) {
                error_log('Error getting terms: ' . $terms->get_error_message());
                return [];
            }
            
            return $terms;
        }
        
        /**
         * Получение ID дефолтной категории "Прочее"
         */
        private function get_default_category_id() {
            $term = term_exists($this->default_category_slug, $this->taxonomy);
            
            if (!$term) {
                error_log('Creating default category "Прочее" in taxonomy ' . $this->taxonomy);
                
                $term = wp_insert_term(
                    'Прочее',
                    $this->taxonomy,
                    [
                        'slug' => $this->default_category_slug,
                        'description' => 'Категория для купонов без автоматического сопоставления'
                    ]
                );
                
                if (is_wp_error($term)) {
                    error_log('Failed to create default category: ' . $term->get_error_message());
                    return 0;
                }
                
                $term_id = is_array($term) ? $term['term_id'] : $term;
                error_log('Default category created with ID: ' . $term_id);
            } else {
                $term_id = is_array($term) ? $term['term_id'] : $term;
            }
            
            return (int) $term_id;
        }
        
        /**
         * Логирование успешного сопоставления
         */
        private function log_match($coupon_id, $search_text, $site_category_id, $method, $score = null) {
            error_log(sprintf(
                'MATCH: Coupon %d -> Category %d | Method: %s | Score: %s | Text: %s',
                $coupon_id,
                $site_category_id,
                $method,
                $score ?? 'N/A',
                mb_substr($search_text, 0, 100)
            ));
        }
        
        /**
         * Логирование несопоставленных купонов
         */
        private function log_unmatched($coupon_id, $search_text, $assigned_category_id) {
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'coupon_id' => $coupon_id,
                'search_text' => mb_substr($search_text, 0, 200),
                'assigned_category_id' => $assigned_category_id,
                'status' => 'auto_assigned_to_other'
            ];
            
            $logs = [];
            if (file_exists($this->log_file)) {
                $content = file_get_contents($this->log_file);
                $logs = json_decode($content, true) ?: [];
            }
            
            array_unshift($logs, $log_entry);
            $logs = array_slice($logs, 0, 1000);
            
            file_put_contents($this->log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        /**
         * Для обратной совместимости
         */
        public function get_site_subcategory($admitad_categories, $coupon_id = null) {
            if ($coupon_id) {
                $title = get_post_field('post_title', $coupon_id);
                $content = get_post_field('post_content', $coupon_id);
                $campaign_name = get_post_meta($coupon_id, 'campaign_name', true);
                return $this->get_site_subcategory_by_text($title, $content, $campaign_name, $coupon_id);
            }
            return $this->get_default_category_id();
        }
    }
}