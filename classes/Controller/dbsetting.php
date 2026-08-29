<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Dbsetting
 * Database management module for Firebird ODBC.
 */
class Controller_Dbsetting extends Controller_Template {
    public $template = 'template';
    
    // Module configuration
    protected $config;
    
    // Available ODBC DSNs from Windows Registry
    protected $odbc_dsns;
    
    // Database file paths for each DSN
    protected $odbc_dsn_paths;
    
    // Current selected DSN (from session)
    protected $current_dsn;
    
    // Database error message if connection fails
    protected $db_error = null;
    
    // Allowed base paths for security (reserved for future use)
    protected $allowed_base_paths = array();
    
    // Maximum backup file size in MB
    protected $max_backup_size_mb = 2048;
    
    public function before()
    {
        try {
            parent::before();
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'unavailable database') !== false ||
                strpos($e->getMessage(), 'SQLConnect') !== false ||
                strpos($e->getMessage(), 'Database_Exception') !== false) {
                Log::instance()->add(Log::WARNING, 'Database connection failed: ' . $e->getMessage());
                $this->db_error = $e->getMessage();
            } else {
                throw $e;
            }
        }
    
        // Load module configuration
        $this->config = Kohana::$config->load('dbsetting');
        
        // Get max backup size from config or use default
        $this->max_backup_size_mb = $this->config->get('max_backup_size_mb', 2048);
        
        // Get ODBC DSNs from Windows Registry
        $this->odbc_dsns = $this->get_odbc_dsns_from_registry();
        // Get database paths for each DSN
        $this->odbc_dsn_paths = array(); 
        foreach ($this->odbc_dsns as $name => $dsn) { 
            $this->odbc_dsn_paths[$name] = $this->get_database_path_for_dsn($name); 
        }
        
        // Get current DSN from session or read from database.php
        $this->current_dsn = Session::instance()->get('current_dsn', $this->get_current_dsn_from_config());
     
        // Set template variables
        $this->template->title = __('Database Settings');
    }
    
    /**
     * Convert text from Windows-1251 to UTF-8
     * @param string $text Text to convert
     * @return string Converted text
     */
    protected function convert_to_utf8($text) {
        if (empty($text)) {
            return $text;
        }
        
        // Проверяем, не в UTF-8 ли уже
        if (function_exists('mb_detect_encoding') && mb_detect_encoding($text, 'UTF-8', true) === 'UTF-8') {
            return $text;
        }
        
        // Пробуем конвертировать
        if (function_exists('iconv')) {
            $result = iconv('CP1251', 'UTF-8//IGNORE', $text);
            if ($result !== false) {
                return $result;
            }
        }
        
        if (function_exists('mb_convert_encoding')) {
            $result = mb_convert_encoding($text, 'UTF-8', 'CP1251');
            if ($result !== false) {
                return $result;
            }
        }
        
        // Если ничего не помогло - возвращаем как есть
        return $text;
    }
    
    /**
     * Save log file with UTF-8 encoding
     * @param string $file_path Path to log file
     * @param string $content Log content
     * @return bool|int
     */
    protected function save_log_file($file_path, $content) {
        // Добавляем BOM для UTF-8
        $bom = "\xEF\xBB\xBF";
        return file_put_contents($file_path, $bom . $content, LOCK_EX);
    }
    
    /**
     * Validate and sanitize file path for security
     * @param string $path Path to validate
     * @param bool $check_exists Check if file/directory exists
     * @return string Validated path or throws exception
     * @throws Exception
     */
    protected function validate_path($path, $check_exists = false) {
        // Remove null bytes and dangerous characters
        $path = str_replace(chr(0), '', $path);
        
        // Decode URL encoding
        $path = rawurldecode($path);
        
        // Normalize directory separators
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        
        // Expand Windows environment variables
        $path = $this->expand_path_variables($path);
        
        // Protection from path traversal (..)
        $real_path = realpath($path);
        
        if ($real_path === false) {
            if ($check_exists) {
                throw new Exception('Path does not exist: ' . $path);
            }
            // Path doesn't exist, check parent directory
            $dir = dirname($path);
            $real_dir = realpath($dir);
            if ($real_dir === false) {
                throw new Exception('Parent directory does not exist: ' . $dir);
            }
            $real_path = $real_dir . DIRECTORY_SEPARATOR . basename($path);
        }
        
        // Only check for path traversal (..) and dangerous characters
        // Without restriction on allowed directories
        if (strpos($real_path, '..') !== false) {
            throw new Exception('Path traversal detected');
        }
        
        return $real_path;
    }
    
    /**
     * Validate backup file extension
     * @param string $file_path Path to backup file
     * @return string Validated path
     * @throws Exception
     */
    protected function validate_backup_file($file_path) {
        $allowed_extensions = array('fbk', 'bak', 'backup', 'gdb');
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_extensions)) {
            throw new Exception('Invalid backup file extension. Allowed: ' . implode(', ', $allowed_extensions));
        }
        
        return $this->validate_path($file_path, true);
    }
    
    /**
     * Validate DSN exists in registry
     * @param string $dsn_name DSN name
     * @return string DSN value
     * @throws Exception
     */
    protected function validate_dsn($dsn_name) {
        $dsns = $this->get_odbc_dsns_from_registry();
        if (!isset($dsns[$dsn_name])) {
            throw new Exception('DSN "' . $dsn_name . '" not found in registry');
        }
        return $dsns[$dsn_name];
    }
    
    /**
     * Expand Windows environment variables in path
     * @param string $path Path with possible %VAR% variables
     * @return string Path with expanded variables
     */
    protected function expand_path_variables($path) {
        if (strpos($path, '%') !== false) {
            // Replace Windows environment variables
            preg_match_all('/%([^%]+)%/', $path, $matches);
            foreach ($matches[1] as $var) {
                $value = getenv($var);
                if ($value !== false) {
                    $path = str_replace('%' . $var . '%', $value, $path);
                }
            }
        }
        return $path;
    }
    
    /**
     * Check available disk space
     * @param string $path Directory path
     * @param int $required_mb Required space in MB
     * @return bool
     */
    protected function check_disk_space($path, $required_mb = 50) {
        // Проверяем, существует ли директория
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
            if (!is_dir($path)) {
                return true; // Не можем проверить - пропускаем
            }
        }
    
        // Получаем корень диска
        $root_path = $path;
        if (DIRECTORY_SEPARATOR === '\\') {
            if (preg_match('/^[A-Za-z]:/', $path, $matches)) {
                $root_path = $matches[0] . DIRECTORY_SEPARATOR;
            } else {
                return true; // UNC путь - пропускаем проверку
            }
        }
    
        $free_space = @disk_free_space($root_path);
        if ($free_space === false) {
            return true; // Не можем проверить - пропускаем
        }
    
        $free_mb = $free_space / 1024 / 1024;
        return $free_mb >= $required_mb;
    }
    
    /**
     * Check database file size
     * @param string $file_path Database file path
     * @return bool
     */
    protected function check_database_size($file_path) {
        if (!file_exists($file_path)) {
            return true; // File doesn't exist, skip check
        }
        $size_mb = filesize($file_path) / 1024 / 1024;
        return $size_mb <= $this->max_backup_size_mb;
    }
    
    /**
     * Rotate old backups (keep only N newest)
     * @param string $backup_dir Backup directory
     * @param int $keep_count Number of backups to keep
     */
    protected function rotate_backups($backup_dir, $keep_count = 10) {
        if (!is_dir($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . DIRECTORY_SEPARATOR . '*.{fbk,bak,backup,gdb}', GLOB_BRACE);
        if (empty($files) || count($files) <= $keep_count) {
            return;
        }
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest backups
        $to_delete = array_slice($files, 0, count($files) - $keep_count);
        foreach ($to_delete as $file) {
            if (@unlink($file)) {
                Log::instance()->add(Log::INFO, 'Removed old backup: ' . basename($file));
            } else {
                Log::instance()->add(Log::WARNING, 'Failed to remove old backup: ' . basename($file));
            }
        }
    }
    
    /**
     * Validate CSRF token
     * @param string $action Action identifier for token
     * @return bool
     */
    protected function validate_csrf($action) {
        $posted_token = $this->request->post('csrf_token');
        $expected_token = $this->get_csrf_token($action);
        
        if ($posted_token !== $expected_token) {
            Log::instance()->add(Log::WARNING, 'CSRF validation failed for action: ' . $action);
            return false;
        }
        return true;
    }
    
    /**
     * Generate CSRF token
     * @param string $action Action identifier for token
     * @return string
     */
    protected function get_csrf_token($action) {
        // Add salt and time-based component for stronger security
        $salt = 'dbsetting_secure_salt_' . $action;
        $time = floor(time() / 3600); // Changes every hour
        return md5(session_id() . $salt . $time . Kohana::$config->load('dbsetting')->get('secret_key', ''));
    }
    
    /**
     * Main page with controls
     */
    public function action_index()
    {
        try {
            $service_status = $this->get_service_status();
        } catch (Exception $e) {
            $service_status = 'unknown';
            Log::instance()->add(Log::ERROR, 'Failed to get service status: ' . $e->getMessage());
        }
        
        $database_path = $this->config->get('database_path');
        $database_dir = '';
        $database_filename = '';
        if (!empty($database_path)) {
            $database_dir = dirname($database_path);
            $database_filename = basename($database_path);
        }
        
        $backup_dir = $this->config->get('backup_dir');
        
        // Get list of backup files
        $backup_files = array();
        if (!empty($backup_dir) && is_dir($backup_dir)) {
            $files = scandir($backup_dir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, array('fbk', 'gbk'))) {
                        $backup_files[] = $file;
                    }
                }
                // Sort by modification time (newest first)
                usort($backup_files, function($a, $b) use ($backup_dir) {
                    $timeA = filemtime($backup_dir . DIRECTORY_SEPARATOR . $a);
                    $timeB = filemtime($backup_dir . DIRECTORY_SEPARATOR . $b);
                    return $timeB - $timeA;
                });
            }
        }
        
        $content = View::factory('dbsetting/index')
            ->set('odbc_dsns', $this->odbc_dsns)
            ->set('odbc_dsn_paths', $this->odbc_dsn_paths)
            ->set('current_dsn', $this->current_dsn)
            ->set('service_status', $service_status)
            ->set('backup_dir', $backup_dir)
            ->set('database_path', $database_path)
            ->set('database_dir', $database_dir)
            ->set('database_filename', $database_filename)
            ->set('db_error', $this->db_error)
            ->set('backup_files', $backup_files)
            ->set('csrf_token_path', $this->get_csrf_token('save_path'))
            ->set('csrf_token_config', $this->get_csrf_token('config_edit'))
            ->set('csrf_token_select_dsn', $this->get_csrf_token('select_dsn'))
            ->set('csrf_token_backup', $this->get_csrf_token('backup'))
            ->set('csrf_token_restore', $this->get_csrf_token('restore'))
            ->set('csrf_token_service', $this->get_csrf_token('service'));
        
        $this->template->content = $content;
    }
    
    /**
     * Action to select ODBC DSN
     */
    public function action_select_dsn()
    {
        if ($this->request->method() === 'POST') {
            // Validate CSRF
            if (!$this->validate_csrf('select_dsn')) {
                Session::instance()->set('flash_message_dbsetting', array(
                    'type' => 'error',
                    'text' => __('Security token validation failed. Please refresh the page and try again.')
                ));
                $this->redirect('dbsetting');
                return;
            }
            
            $selected = $this->request->post('dsn');
            
            try {
                // Validate DSN exists
                $dsn_value = $this->validate_dsn($selected);
                
                Session::instance()->set('current_dsn', $dsn_value);
                
                if ($this->update_database_config($dsn_value)) {
                    Session::instance()->set('flash_message_dbsetting', array(
                        'type' => 'success',
                        'text' => __('Database DSN changed to ') . htmlspecialchars($selected) . ' and saved to config file'
                    ));
                } else {
                    Session::instance()->set('flash_message_dbsetting', array(
                        'type' => 'error',
                        'text' => __('Failed to update database configuration file. Check logs for details.')
                    ));
                }
            } catch (Exception $e) {
                Session::instance()->set('flash_message_dbsetting', array(
                    'type' => 'error',
                    'text' => $e->getMessage()
                ));
            }
        }
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Save selected database path to module configuration
     */
    public function action_save_database_path()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        $this->auto_render = false;
        
        // Validate CSRF
        if (!$this->validate_csrf('save_path')) {
            $error = __('Security token validation failed. Please refresh the page and try again.');
            if ($this->request->is_ajax()) {
                $this->response->headers('Content-Type', 'application/json');
                $this->response->body(json_encode(array(
                    'success' => false,
                    'message' => $error
                )));
                return;
            } else {
                Session::instance()->set('flash_message_dbsetting', array(
                    'type' => 'error',
                    'text' => $error
                ));
                $this->redirect('dbsetting');
                return;
            }
        }
        
        $database_path = $this->request->post('database_path');
        
        if (empty($database_path)) {
            $error = __('Database path cannot be empty.');
            $this->send_json_response(false, $error);
            return;
        }
        
        try {
            // Validate path
            $database_path = $this->validate_path($database_path, false);
            $file_exists = file_exists($database_path);
            
            // Check database size
            if ($file_exists && !$this->check_database_size($database_path)) {
                $this->send_json_response(false, 
                    __('Database file is too large (>') . $this->max_backup_size_mb . __(' MB)')
                );
                return;
            }
            
            // Update module configuration
            $success = $this->update_module_database_path($database_path);
            
            if ($success) {
                $this->send_json_response(true, 
                    __('Database path saved: ') . HTML::chars($database_path),
                    array('file_exists' => $file_exists)
                );
            } else {
                $this->send_json_response(false, 
                    __('Failed to save database path. Check logs.')
                );
            }
        } catch (Exception $e) {
            Log::instance()->add(Log::ERROR, 'Exception in save_database_path: ' . $e->getMessage());
            $this->send_json_response(false, __('Error: ') . $e->getMessage());
        }
    }
    
    /**
     * Save database directory (folder) only
     */
    public function action_save_database_dir()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        $this->auto_render = false;
        
        // Validate CSRF
        if (!$this->validate_csrf('save_path')) {
            $this->send_json_response(false, __('Security token validation failed.'));
            return;
        }
        
        $database_dir = $this->request->post('database_dir');
        
        if (empty($database_dir)) {
            $this->send_json_response(false, __('Database directory cannot be empty.'));
            return;
        }
        
        try {
            // Validate directory path
            $database_dir = $this->validate_path($database_dir, true);
            
            // Get current database filename from config
            $current_path = $this->config->get('database_path');
            $database_filename = !empty($current_path) ? basename($current_path) : 'database.fdb';
            
            // Build new full path
            $new_database_path = rtrim($database_dir, '\\/') . DIRECTORY_SEPARATOR . $database_filename;
            
            $file_exists = file_exists($new_database_path);
            
            // Check database size
            if ($file_exists && !$this->check_database_size($new_database_path)) {
                $this->send_json_response(false, 
                    __('Database file is too large (>') . $this->max_backup_size_mb . __(' MB)')
                );
                return;
            }
            
            // Update module configuration
            $success = $this->update_module_database_path($new_database_path);
            
            if ($success) {
                $this->send_json_response(true,
                    __('Database directory saved: ') . HTML::chars($database_dir),
                    array('file_exists' => $file_exists, 'new_full_path' => $new_database_path)
                );
            } else {
                $this->send_json_response(false, __('Failed to save database directory.'));
            }
        } catch (Exception $e) {
            Log::instance()->add(Log::ERROR, 'Exception in save_database_dir: ' . $e->getMessage());
            $this->send_json_response(false, __('Error: ') . $e->getMessage());
        }
    }
    
    /**
     * Save database filename only
     */
    public function action_save_database_filename()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        $this->auto_render = false;
        
        // Validate CSRF
        if (!$this->validate_csrf('save_path')) {
            $this->send_json_response(false, __('Security token validation failed.'));
            return;
        }
        
        $database_filename = $this->request->post('database_filename');
        
        if (empty($database_filename)) {
            $this->send_json_response(false, __('Database filename cannot be empty.'));
            return;
        }
        
        // Validate filename (no path separators, only safe characters)
        if (preg_match('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', $database_filename)) {
            $this->send_json_response(false, __('Invalid filename contains illegal characters.'));
            return;
        }
        
        try {
            $database_filename = rawurldecode($database_filename);
            
            // Get current database directory from config
            $current_path = $this->config->get('database_path');
            $database_dir = !empty($current_path) ? dirname($current_path) : 'C:\\';
            
            // Validate directory exists
            $real_dir = realpath($database_dir);
            if ($real_dir === false) {
                throw new Exception('Database directory does not exist: ' . $database_dir);
            }
            
            // Build new full path
            $new_database_path = rtrim($real_dir, '\\/') . DIRECTORY_SEPARATOR . $database_filename;
            
            $file_exists = file_exists($new_database_path);
            
            // Check database size
            if ($file_exists && !$this->check_database_size($new_database_path)) {
                $this->send_json_response(false, 
                    __('Database file is too large (>') . $this->max_backup_size_mb . __(' MB)')
                );
                return;
            }
            
            // Update module configuration
            $success = $this->update_module_database_path($new_database_path);
            
            if ($success) {
                $this->send_json_response(true,
                    __('Database filename saved: ') . HTML::chars($database_filename),
                    array('file_exists' => $file_exists, 'new_full_path' => $new_database_path)
                );
            } else {
                $this->send_json_response(false, __('Failed to save database filename.'));
            }
        } catch (Exception $e) {
            Log::instance()->add(Log::ERROR, 'Exception in save_database_filename: ' . $e->getMessage());
            $this->send_json_response(false, __('Error: ') . $e->getMessage());
        }
    }
    
    /**
     * Send JSON response helper
     */
    protected function send_json_response($success, $message, $extra = array()) {
        $this->response->headers('Content-Type', 'application/json');
        $response = array_merge(array(
            'success' => $success,
            'message' => $message
        ), $extra);
        $this->response->body(json_encode($response));
    }
    
    /**
     * Create database backup with live output and log file
     */
    public function action_backup()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }

        if (!$this->validate_csrf('backup')) {
            $this->send_json_response(false, 'CSRF validation failed');
            return;
        }

        $firebird_bin = $this->config->get('firebird_bin');
        $firebird_password = $this->config->get('firebird_password', '');

        if (empty($firebird_password)) {
            $this->send_json_response(false, 'Firebird password not configured');
            return;
        }

        $database_path = Arr::get($_POST, 'database_path');
        $backup_dir = Arr::get($_POST, 'backup_dir');

        try {
            $database_path = $this->validate_path($database_path, true);
            $backup_dir = $this->validate_path($backup_dir, false);
        } catch (Exception $e) {
            $this->send_json_response(false, 'Path validation failed: ' . $e->getMessage());
            return;
        }

        // Check database size before backup
        if (!file_exists($database_path)) {
            $this->send_json_response(false, 'Database file does not exist: ' . $database_path);
            return;
        }
        
        if (!$this->check_database_size($database_path)) {
            $this->send_json_response(false, 
                'Database file is too large (>' . $this->max_backup_size_mb . ' MB). Backup not recommended.'
            );
            return;
        }

        // Check disk space
        if (!$this->check_disk_space($backup_dir, 100)) {
            $this->send_json_response(false, 
                'Not enough disk space (minimum 100 MB required)'
            );
            return;
        }

        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0777, true)) {
                $this->send_json_response(false, 'Failed to create backup directory');
                return;
            }
        }

        // Rotate old backups (keep last 10)
        $keep_count = $this->config->get('max_backup_files', 10);
        $this->rotate_backups($backup_dir, $keep_count);

        $db_filename = pathinfo($database_path, PATHINFO_FILENAME);
        $timestamp = date('Y-m-d_His');
        $backup_file = $backup_dir . DIRECTORY_SEPARATOR . $db_filename . '_' . $timestamp . '.fbk';
        $log_file = $backup_dir . DIRECTORY_SEPARATOR . $db_filename . '_' . $timestamp . '.log';

        $gbak_path = rtrim($firebird_bin, '\\/') . DIRECTORY_SEPARATOR . 'gbak.exe';
        if (!file_exists($gbak_path)) {
            $this->send_json_response(false, 'gbak.exe not found at: ' . $gbak_path);
            return;
        }

        $gbak = escapeshellarg($gbak_path);
        $db = '127.0.0.1:' . escapeshellarg($database_path);
        $backup = escapeshellarg($backup_file);

        $command = $gbak . ' -b -v -ig -g -user SYSDBA -password ' .
                escapeshellarg($firebird_password) . ' ' . $db . ' ' . $backup;

        Log::instance()->add(Log::INFO, 'Backup started: ' . $backup_file);
        Log::instance()->add(Log::INFO, 'Backup log file: ' . $log_file);

        if ($this->request->is_ajax() || $this->request->post('ajax') == 1) {
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Accel-Buffering: no');
            ob_end_clean();
            flush();

            echo "=== ЗАПУСК РЕЗЕРВНОГО КОПИРОВАНИЯ ===\n";
            echo "База данных: " . htmlspecialchars($database_path) . "\n";
            echo "Сохраняем в: " . htmlspecialchars($backup_file) . "\n";
            echo "Лог будет сохранен: " . htmlspecialchars($log_file) . "\n";
            echo "Размер БД: " . round(filesize($database_path) / 1024 / 1024, 2) . " MB\n\n";
            flush();

            $output = array();
            $return_var = null;
            exec($command . ' 2>&1', $output, $return_var);

            // Сохраняем лог в файл с конвертацией в UTF-8
            $output_text = implode("\n", $output);
            $output_text = $this->convert_to_utf8($output_text);

            $log_content = "=== РЕЗЕРВНОЕ КОПИРОВАНИЕ ===\n";
            $log_content .= "Дата: " . date('Y-m-d H:i:s') . "\n";
            $log_content .= "База данных: " . $database_path . "\n";
            $log_content .= "Резервная копия: " . $backup_file . "\n";
            $log_content .= "Команда: " . $command . "\n";
            $log_content .= "Код возврата: " . $return_var . "\n";
            $log_content .= "=== ВЫВОД GBAK ===\n";
            $log_content .= $output_text . "\n";
            $log_content .= "=== ЗАВЕРШЕНО ===\n";
            
            $this->save_log_file($log_file, $log_content);

            foreach ($output as $line) {
                echo htmlspecialchars($line) . "\n";
                flush();
            }

            echo "\n=== ЗАВЕРШЕНО ===\n";
            echo "Код возврата: " . $return_var . "\n";
            echo "Лог сохранен: " . htmlspecialchars(basename($log_file)) . "\n";
            
            if ($return_var === 0) {
                $backup_size = round(filesize($backup_file) / 1024 / 1024, 2);
                echo "✅ Резервная копия успешно создана!\n";
                echo "Размер: " . $backup_size . " MB\n";
                echo "Файл: " . htmlspecialchars(basename($backup_file)) . "\n";
            } else {
                echo "❌ Ошибка! Код: $return_var\n";
                echo "Проверьте лог файл для деталей: " . htmlspecialchars(basename($log_file)) . "\n";
                Log::instance()->add(Log::ERROR, 'Backup failed with code ' . $return_var . '. Log saved to: ' . $log_file);
            }
            exit;
        }

        // Fallback (if not AJAX)
        exec($command, $output, $return_var);
        
        // Сохраняем лог и для fallback с конвертацией
        $output_text = implode("\n", $output);
        $output_text = $this->convert_to_utf8($output_text);

        $log_content = "=== РЕЗЕРВНОЕ КОПИРОВАНИЕ (FALLBACK) ===\n";
        $log_content .= "Дата: " . date('Y-m-d H:i:s') . "\n";
        $log_content .= "База данных: " . $database_path . "\n";
        $log_content .= "Резервная копия: " . $backup_file . "\n";
        $log_content .= "Код возврата: " . $return_var . "\n";
        $log_content .= "=== ВЫВОД GBAK ===\n";
        $log_content .= $output_text . "\n";
        
        $this->save_log_file($log_file, $log_content);
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Save backup directory
     */
    public function action_save_backup_dir()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }

        $this->auto_render = false;

        if (!$this->validate_csrf('save_path')) {
            $this->send_json_response(false, __('Security token validation failed.'));
            return;
        }

        $backup_dir = $this->request->post('backup_dir');

        if (empty($backup_dir)) {
            $this->send_json_response(false, __('Backup directory cannot be empty.'));
            return;
        }

        try {
            $backup_dir = $this->validate_path($backup_dir, false);

            // Check disk space for backup directory
            if (!$this->check_disk_space($backup_dir, 500)) {
                $this->send_json_response(false, 
                    __('Warning: Low disk space on backup drive (less than 500 MB)')
                );
                return;
            }

            $module_config_path = MODPATH . 'dbsetting/config/dbsetting.php';
            $content = file_get_contents($module_config_path);

            $escaped = str_replace("'", "\\'", $backup_dir);
            $new_content = preg_replace(
                "/('backup_dir'\\s*=>\\s*')[^']*(')/",
                "\$1$escaped\$2",
                $content
            );

            if ($new_content === $content) {
                $new_content = preg_replace(
                    '/("backup_dir"\\s*=>\\s*")[^"]*(")/',
                    "\$1$backup_dir\$2",
                    $content
                );
            }

            file_put_contents($module_config_path, $new_content, LOCK_EX);
            Kohana::$config->load('dbsetting', true);

            $this->send_json_response(true, __('Backup directory saved: ') . HTML::chars($backup_dir));
        } catch (Exception $e) {
            $this->send_json_response(false, __('Error: ') . $e->getMessage());
        }
    }
    
    /**
     * Restore database from backup
     * Восстанавливает БД в restore_path, НЕ меняет database_path
     */
    public function action_restore()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate CSRF
        if (!$this->validate_csrf('restore')) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $backup_file = $this->request->post('backup_file');
        $firebird_bin = $this->config->get('firebird_bin');
        $firebird_password = $this->config->get('firebird_password', '');
        $restore_dir = $this->config->get('restore_path');
        $database_path = $this->config->get('database_path');
        
        // Validate password
        if (empty($firebird_password)) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Firebird password not configured.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        try {
            // Validate backup file with extension check
            $backup_file = $this->validate_backup_file($backup_file);
            $restore_dir = $this->validate_path($restore_dir, false);
        } catch (Exception $e) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Path validation failed: ') . $e->getMessage()
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check backup file size
        $backup_size_mb = filesize($backup_file) / 1024 / 1024;
        if ($backup_size_mb > $this->max_backup_size_mb) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Backup file is too large (') . round($backup_size_mb, 2) . __(' MB). Maximum: ') . $this->max_backup_size_mb . __(' MB')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check disk space for restore
        if (!$this->check_disk_space($restore_dir, ($backup_size_mb * 2) + 100)) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Not enough disk space for restore (need at least ') . round(($backup_size_mb * 2) + 100, 0) . __(' MB)')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Формируем имя для восстановленной БД
        $backup_info = pathinfo($backup_file);
        $timestamp = date('Y-m-d_His');
        $new_filename = $backup_info['filename'] . '_restored_' . $timestamp . '.gdb';
        $new_restore_path = rtrim($restore_dir, '\\/') . DIRECTORY_SEPARATOR . $new_filename;
        
        // Создаем лог файл рядом с резервной копией
        $log_file = dirname($backup_file) . DIRECTORY_SEPARATOR . 
                    $backup_info['filename'] . '_restore_' . $timestamp . '.log';
        
        $gbak = escapeshellarg(rtrim($firebird_bin, '\\/') . DIRECTORY_SEPARATOR . 'gbak.exe');
        $backup = escapeshellarg($backup_file);
        $restore = '127.0.0.1:' . escapeshellarg($new_restore_path);
        
        $command = $gbak . ' -c -o -v -r -user SYSDBA -password ' . 
                   escapeshellarg($firebird_password) . ' ' . $backup . ' ' . $restore;
        
        // Выполняем команду и получаем полный вывод
        exec($command . ' 2>&1', $output, $return_var);

        // Полный вывод gbak для лога с конвертацией в UTF-8
        $full_output = implode("\n", $output);
        $full_output = $this->convert_to_utf8($full_output);

        // Сохраняем лог восстановления с BOM
        $log_content = "=== ВОССТАНОВЛЕНИЕ БАЗЫ ДАННЫХ ===\n";
        $log_content .= "Дата: " . date('Y-m-d H:i:s') . "\n";
        $log_content .= "Файл бэкапа: " . $backup_file . "\n";
        $log_content .= "Восстановлен в: " . $new_restore_path . "\n";
        $log_content .= "Команда: " . $command . "\n";
        $log_content .= "Код возврата: " . $return_var . "\n";
        $log_content .= "=== ВЫВОД GBAK ===\n";
        $log_content .= $full_output . "\n";
        $log_content .= "=== ЗАВЕРШЕНО ===\n";

        $this->save_log_file($log_file, $log_content);
        
        // Логируем в системный лог
        Log::instance()->add(Log::INFO, 'Restore completed. Return code: ' . $return_var);
        Log::instance()->add(Log::INFO, 'Restore log saved to: ' . $log_file);
        
        if ($return_var === 0) {
            // ============================================================
            // ВАЖНО: НЕ МЕНЯЕМ database_path В КОНФИГЕ!
            // Пользователь САМ заменит файл в Program Files вручную
            // ============================================================
            
            $backup_basename = basename($backup_file);
            $restored_basename = basename($new_restore_path);
            $log_basename = basename($log_file);
            $target_dir = dirname($database_path);
            $target_file = basename($database_path);
            
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'success',
                'text' => 
                    '✅ БАЗА ДАННЫХ ВОССТАНОВЛЕНА!<br><br>' .
                    '📁 Восстановленный файл: ' . $new_restore_path . '<br>' .
                    '📄 Лог: ' . $log_basename . '<br><br>' .
                    '⚠️ ДАЛЕЕ НЕОБХОДИМО ВРУЧНУЮ ЗАМЕНИТЬ ФАЙЛ:<br>' .
                    '1. Остановить Firebird сервис<br>' .
                    '2. Скопировать ' . $restored_basename . ' в папку<br>' .
                    '&nbsp;&nbsp;' . $target_dir . '<br>' .
                    '3. Переименовать в ' . $target_file . '<br>' .
                    '4. Запустить Firebird сервис<br><br>'
            ));
            
            // Сохраняем путь к восстановленной БД в сессию для возможности автоматической замены
            Session::instance()->set('restored_db_file', $new_restore_path);
            Session::instance()->set('restored_db_log', $log_file);
            
        } else {
            // Извлекаем ошибку из вывода
            $error_lines = array_filter($output, function($line) {
                return stripos($line, 'error') !== false || 
                       stripos($line, 'fail') !== false || 
                       stripos($line, 'unable') !== false ||
                       stripos($line, 'cannot') !== false;
            });
            
            $error_msg = !empty($error_lines) ? implode('; ', $error_lines) : 'Unknown error (check log file for details)';
            
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => '❌ Ошибка восстановления! Код: ' . $return_var . 
                          '. Ошибка: ' . htmlspecialchars($error_msg) . 
                          '. Подробности в логе: ' . htmlspecialchars(basename($log_file))
            ));
            Log::instance()->add(Log::ERROR, 'Restore failed. Return code: ' . $return_var);
            Log::instance()->add(Log::ERROR, 'Restore log: ' . $log_file);
            Log::instance()->add(Log::ERROR, 'Restore error details: ' . $error_msg);
        }
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Find the correct Firebird service name
     * @return string|null The service name if found, null otherwise
     */
    protected function find_firebird_service()
    {
        $possible_services = array(
            $this->config->get('service_name', 'FirebirdServerDefault'),
            'Firebird Guardian - DefaultInstance',
            'Firebird Guardian - Default',
            'FirebirdGuardianDefaultInstance'
        );
        
        $possible_services = array_unique($possible_services);
        
        foreach ($possible_services as $service) {
            $command = 'sc query ' . escapeshellarg($service) . ' 2>nul';
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                return $service;
            }
        }
        
        return null;
    }
    
    /**
     * Get Firebird service status
     */
    protected function get_service_status()
    {
        $service = $this->find_firebird_service();
     
        if ($service) {
            $command = 'sc query ' . escapeshellarg($service) . ' 2>nul';
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (strpos($line, 'RUNNING') !== false) {
                        return 'running';
                    }
                    if (strpos($line, 'STOPPED') !== false) {
                        return 'stopped';
                    }
                }
            }
        }
        
        // Alternative check via process
        $command = 'tasklist /FI "IMAGENAME eq fbserver.exe" /FI "STATUS eq running" 2>nul | find "fbserver.exe"';
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            return 'running';
        }
        
        return 'unknown';
    }
    
    /**
     * Stop Firebird service
     */
    public function action_stop_service()
    {
        // Validate CSRF
        if (!$this->validate_csrf('service')) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $service = $this->find_firebird_service();
        
        if (!$service) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Firebird service not found.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $command = 'net stop ' . escapeshellarg($service);
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'success',
                'text' => __('Firebird service stopped.')
            ));
        } else {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Failed to stop service.')
            ));
        }
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Start Firebird service
     */
    public function action_start_service()
    {
        // Validate CSRF
        if (!$this->validate_csrf('service')) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $service = $this->find_firebird_service();
        
        if (!$service) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Firebird service not found.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $command = 'net start ' . escapeshellarg($service);
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'success',
                'text' => __('Firebird service started.')
            ));
        } else {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => __('Failed to start service.')
            ));
        }
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Stop service helper
     */
    protected function stop_service()
    {
        $service = $this->find_firebird_service();
        if ($service) {
            exec('net stop ' . escapeshellarg($service) . ' 2>nul >nul');
        }
    }
    
    /**
     * Start service helper
     */
    protected function start_service()
    {
        $service = $this->find_firebird_service();
        if ($service) {
            exec('net start ' . escapeshellarg($service) . ' 2>nul >nul');
        }
    }
    
    /**
     * Get ODBC DSNs from Windows Registry
     */
    protected function get_odbc_dsns_from_registry()
    {
        $dsns = array();
        
        $registry_paths = array(
            'HKEY_CURRENT_USER\Software\ODBC\ODBC.INI\ODBC Data Sources',
            'HKEY_LOCAL_MACHINE\SOFTWARE\ODBC\ODBC.INI\ODBC Data Sources',
            'HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\ODBC\ODBC.INI\ODBC Data Sources'
        );
        
        foreach ($registry_paths as $registry_path) {
            $command = 'reg query "' . $registry_path . '" 2>nul';
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (preg_match('/^\s*([^\s].*?)\s+REG_SZ\s+(.*)$/', $line, $matches)) {
                        $dsn_name = trim($matches[1]);
                        if (!empty($dsn_name) && $dsn_name !== '(Default)') {
                            $dsns[$dsn_name] = 'odbc:' . $dsn_name;
                        }
                    }
                }
            }
        }
        
        // Default fallback if no DSNs found
        if (empty($dsns)) {
            $dsns = array(
                'SDUO' => 'odbc:SDUO',
                'Kalibr' => 'odbc:Kalibr',
                'Kalibr_25' => 'odbc:Kalibr_25',
                'HL' => 'odbc:HL',
            );
        }
        
        ksort($dsns);
        
        return $dsns;
    }

    /**
     * Get database file path for a given DSN from Windows Registry
     *
     * @param string $dsn_name DSN name
     * @return string Path to database file or empty string if not found
     */
    protected function get_database_path_for_dsn($dsn_name)
    {
        $registry_paths = array(
            'HKEY_CURRENT_USER\Software\ODBC\ODBC.INI\\' . $dsn_name,
            'HKEY_LOCAL_MACHINE\SOFTWARE\ODBC\ODBC.INI\\' . $dsn_name,
            'HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\ODBC\ODBC.INI\\' . $dsn_name
        );
        
        $possible_keys = array('Database', 'Server', 'Dbname', 'DataSource', 'DBQ', 'Data Source', 'DBNAME');
        
        foreach ($registry_paths as $registry_path) {
            foreach ($possible_keys as $key) {
                $command = 'reg query "' . $registry_path . '" /v "' . $key . '" 2>nul';
                exec($command, $output, $return_var);
                
                if ($return_var === 0 && !empty($output)) {
                    foreach ($output as $line) {
                        if (preg_match('/REG_SZ\s+(.*)/', $line, $matches)) {
                            $path = trim($matches[1]);
                            if (!empty($path)) {
                                // Expand environment variables in path
                                return $this->expand_path_variables($path);
                            }
                        }
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Get current DSN from database.php config file
     */
    protected function get_current_dsn_from_config()
    {
        $config_path = $this->config->get('database_config_path', APPPATH . 'config/database.php');
        
        if (file_exists($config_path)) {
            $content = file_get_contents($config_path);
            if (preg_match("/'dsn'\s*=>\s*'([^']*)'/", $content, $matches)) {
                return $matches[1];
            }
        }
        
        return 'odbc:HL';
    }
    
    /**
     * Update database.php config file
     */
    protected function update_database_config($dsn)
    {
        $config_path = $this->config->get('database_config_path', APPPATH . 'config/database.php');
        
        if (!file_exists($config_path)) {
            Log::instance()->add(Log::ERROR, 'Database config file not found: ' . $config_path);
            return false;
        }
        
        // Validate DSN format
        if (!preg_match('/^odbc:[a-zA-Z0-9_\-\.\s]+$/', $dsn)) {
            Log::instance()->add(Log::ERROR, 'Invalid DSN format: ' . $dsn);
            return false;
        }
        
        $content = file_get_contents($config_path);
        if ($content === false) {
            Log::instance()->add(Log::ERROR, 'Failed to read database config file');
            return false;
        }
        
        $escaped_dsn = str_replace("'", "\\'", $dsn);
        
        // Check if file is writable
        if (!is_writable($config_path)) {
            Log::instance()->add(Log::ERROR, 'Database config file is not writable: ' . $config_path);
            return false;
        }
        
        $new_content = preg_replace(
            "/('dsn'\\s*=>\\s*')[^']*(')/",
            "\$1$escaped_dsn\$2",
            $content
        );
        
        if ($new_content === $content) {
            $new_content = preg_replace(
                '/("dsn"\\s*=>\\s*")[^"]*(")/',
                "\$1$dsn\$2",
                $content
            );
        }
        
        if ($new_content === $content) {
            Log::instance()->add(Log::ERROR, 'Failed to find dsn configuration in config file');
            return false;
        }
        
        // Create backup
        $backup_path = $config_path . '.backup_' . date('Y-m-d_His');
        @copy($config_path, $backup_path);
        
        $result = file_put_contents($config_path, $new_content, LOCK_EX);
        if ($result === false) {
            Log::instance()->add(Log::ERROR, 'Failed to write database config file');
            return false;
        }
        
        return true;
    }

    /**
     * Update module configuration file (dbsetting.php) with new database path
     * @param string $database_path New database file path
     * @return bool Success
     */
    protected function update_module_database_path($database_path)
    {
        $module_config_path = MODPATH . 'dbsetting/config/dbsetting.php';
        
        if (!file_exists($module_config_path)) {
            Log::instance()->add(Log::ERROR, 'Module config file not found: ' . $module_config_path);
            return false;
        }
        
        if (!is_writable($module_config_path)) {
            Log::instance()->add(Log::ERROR, 'Module config file is not writable: ' . $module_config_path);
            return false;
        }
        
        $content = file_get_contents($module_config_path);
        if ($content === false) {
            Log::instance()->add(Log::ERROR, 'Failed to read module config file');
            return false;
        }
        
        if (empty($database_path)) {
            return false;
        }
        
        $escaped_path = str_replace("'", "\\'", $database_path);
        
        // Improved replacement - works even if value hasn't changed
        $pattern = "/^(?!\\s*\\/\\/)(\\s*['\"]database_path['\"]\\s*=>\\s*['\"])[^'\"]*(['\"])/m";
        $replacement = "\$1$escaped_path\$2";
        
        $new_content = preg_replace($pattern, $replacement, $content);
        
        // If nothing changed - still consider it success (to avoid errors)
        if ($new_content === $content) {
            // Check if value is already correct
            if (strpos($content, "'database_path' => '{$escaped_path}'") !== false ||
                strpos($content, '"database_path" => "' . $database_path . '"') !== false) {
                Kohana::$config->load('dbsetting', true);
                return true;
            }
            Log::instance()->add(Log::WARNING, 'Could not update database_path in config, but value may already be correct');
        }
        
        // Create backup before writing
        $backup_path = $module_config_path . '.backup_' . date('Y-m-d_His');
        @copy($module_config_path, $backup_path);
        
        $result = file_put_contents($module_config_path, $new_content, LOCK_EX);
        
        if ($result === false) {
            Log::instance()->add(Log::ERROR, 'Failed to write module config file');
            return false;
        }
        
        Kohana::$config->load('dbsetting', true);
        return true;
    }

    /**
     * Display configuration editor modal
     */
    public function action_edit_config()
    {
        $module_config_path = MODPATH . 'dbsetting/config/dbsetting.php';
        
        $config_content = '';
        if (file_exists($module_config_path) && is_readable($module_config_path)) {
            $config_content = file_get_contents($module_config_path);
        }
        
        $content = View::factory('dbsetting/config_editor')
            ->set('config_content', $config_content)
            ->set('config_path', $module_config_path)
            ->set('csrf_token', $this->get_csrf_token('config_edit'));
        
        $this->template->title = 'Редактирование конфигурации';
        $this->template->content = $content;
    }
    
    /**
     * Save configuration changes
     */
    public function action_save_config()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate CSRF
        if (!$this->validate_csrf('config_edit')) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Ошибка проверки токена безопасности. Пожалуйста, попробуйте снова.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $config_content = $this->request->post('config_content');
        $module_config_path = MODPATH . 'dbsetting/config/dbsetting.php';
        
        if (empty($config_content) || !file_exists($module_config_path)) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Неверная конфигурация или файл не найден.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate PHP syntax before saving
        if (strpos($config_content, '<?php') === false) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Конфигурация должна начинаться с PHP открывающего тега &lt;?php'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check PHP syntax by evaluating in a temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'cfg_');
        if ($temp_file === false) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Не удалось создать временный файл для проверки синтаксиса.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        file_put_contents($temp_file, $config_content);
        $syntax_check = shell_exec('php -l ' . escapeshellarg($temp_file) . ' 2>&1');
        unlink($temp_file);
        
        if (strpos($syntax_check, 'No syntax errors') === false) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Синтаксическая ошибка PHP в конфигурации: ' . nl2br(HTML::chars($syntax_check))
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check if writable
        if (!is_writable($module_config_path)) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Файл конфигурации недоступен для записи. Проверьте права доступа.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Create backup
        $backup_path = $module_config_path . '.backup_' . date('Y-m-d_His');
        @copy($module_config_path, $backup_path);
        
        // Write new content with file locking
        $result = file_put_contents($module_config_path, $config_content, LOCK_EX);
        
        if ($result === false) {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'error',
                'text' => 'Не удалось сохранить файл конфигурации.'
            ));
            Log::instance()->add(Log::ERROR, 'Failed to write module config file: ' . $module_config_path);
        } else {
            Session::instance()->set('flash_message_dbsetting', array(
                'type' => 'success',
                'text' => 'Конфигурация успешно сохранена. Резервная копия: ' . basename($backup_path)
            ));
            Log::instance()->add(Log::INFO, 'Module configuration updated');
            
            // Clear config cache
            Kohana::$config->load('dbsetting', true);
        }
        
        $this->redirect('dbsetting');
    }
}