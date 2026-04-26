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
    
    // Allowed base paths for security
    protected $allowed_base_paths = array();
    
    public function before()
    {
        try {
            parent::before();
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'unavailable database') !== false ||
                strpos($e->getMessage(), 'SQLConnect') !== false ||
                strpos($e->getMessage(), 'Database_Exception') !== false) {
                Log::instance()->add(Log::WARNING, 'Database connection failed in dbsetting module: ' . $e->getMessage());
                $this->db_error = $e->getMessage();
            } else {
                throw $e;
            }
        }
    
        // Load module configuration
        $this->config = Kohana::$config->load('dbsetting');
        
        // Define allowed paths for security
        $this->allowed_base_paths = array(
            'C:\\Program Files\\Firebird\\',
            'C:\\Program Files (x86)\\Firebird\\',
            'D:\\rrr\\',
            'C:\\service_skud\\'
        );
        
        // Get ODBC DSNs from Windows Registry
        $this->odbc_dsns = $this->get_odbc_dsns_from_registry();
        // Get database paths for each DSN
        $this->odbc_dsn_paths = array(); foreach ($this->odbc_dsns as $name => $dsn) { $this->odbc_dsn_paths[$name] = $this->get_database_path_for_dsn($name); }
        
        // Get current DSN from session or read from database.php
        $this->current_dsn = Session::instance()->get('current_dsn', $this->get_current_dsn_from_config());
     
        // Set template variables
        $this->template->title = __('Database Settings');
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
        
        // Get real path (resolves .. and .)
        $real_path = realpath($path);
        
        if ($real_path === false) {
            if ($check_exists) {
                throw new Exception('Path does not exist: ' . $path);
            }
            // For paths that don't exist yet, validate the parent directory
            $dir = dirname($path);
            $real_dir = realpath($dir);
            if ($real_dir === false) {
                throw new Exception('Parent directory does not exist: ' . $dir);
            }
            $real_path = $real_dir . DIRECTORY_SEPARATOR . basename($path);
        }
        
        // Check if path is within allowed base directories
        $is_allowed = false;
        foreach ($this->allowed_base_paths as $allowed) {
            $allowed_real = realpath($allowed);
            if ($allowed_real !== false && strpos($real_path, $allowed_real) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            throw new Exception('Access denied: Path outside allowed directories');
        }
        
        return $real_path;
    }
    
    /**
     * Validate CSRF token
     * @param string $action Action identifier for token
     * @return bool
     */
    protected function validate_csrf($action) {
        $posted_token = $this->request->post('csrf_token');
        $expected_token = md5(session_id() . 'dbsetting_' . $action);
        
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
        return md5(session_id() . 'dbsetting_' . $action);
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
                    if (in_array($ext, array('fbk', 'bak', 'backup', 'gdb'))) {
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
                Session::instance()->set('flash_message', array(
                    'type' => 'error',
                    'text' => __('Security token validation failed. Please refresh the page and try again.')
                ));
                $this->redirect('dbsetting');
                return;
            }
            
            $selected = $this->request->post('dsn');
            
            if (array_key_exists($selected, $this->odbc_dsns)) {
                $dsn_value = $this->odbc_dsns[$selected];
                
                Session::instance()->set('current_dsn', $dsn_value);
                
                if ($this->update_database_config($dsn_value)) {
                    Session::instance()->set('flash_message', array(
                        'type' => 'success',
                        'text' => __('Database DSN changed to ') . $selected . ' and saved to config file'
                    ));
                } else {
                    Session::instance()->set('flash_message', array(
                        'type' => 'error',
                        'text' => __('Failed to update database configuration file. Check logs for details.')
                    ));
                }
            } else {
                Session::instance()->set('flash_message', array(
                    'type' => 'error',
                    'text' => __('Invalid DSN selected.')
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
                Session::instance()->set('flash_message', array(
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
     * Create database backup
     */
    public function action_backup()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate CSRF
        if (!$this->validate_csrf('backup')) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $firebird_bin = $this->config->get('firebird_bin');
        $firebird_password = $this->config->get('firebird_password', '');
        
        // Validate password is set
        if (empty($firebird_password)) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Firebird password not configured. Please set firebird_password in config.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $database_path = Arr::get($_POST, 'database_path');
        $backup_dir = Arr::get($_POST, 'backup_dir');
        
        try {
            // Validate paths
            $database_path = $this->validate_path($database_path, true);
            $backup_dir = $this->validate_path($backup_dir, false);
        } catch (Exception $e) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Path validation failed: ') . $e->getMessage()
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Ensure backup directory exists
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0777, true)) {
                Session::instance()->set('flash_message', array(
                    'type' => 'error',
                    'text' => __('Failed to create backup directory: ') . HTML::chars($backup_dir)
                ));
                $this->redirect('dbsetting');
                return;
            }
        }
        
        // Generate backup filename
        $db_filename = pathinfo($database_path, PATHINFO_FILENAME);
        $timestamp = date('Y-m-d_His');
        $backup_file = $backup_dir . DIRECTORY_SEPARATOR . $db_filename . '_' . $timestamp . '.fbk';
        
        // Validate gbak.exe exists
        $gbak_path = rtrim($firebird_bin, '\\/') . DIRECTORY_SEPARATOR . 'gbak.exe';
        if (!file_exists($gbak_path)) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('gbak.exe not found at: ') . HTML::chars($gbak_path)
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $gbak = escapeshellarg($gbak_path);
        $db = '127.0.0.1:' . escapeshellarg($database_path);
        $backup = escapeshellarg($backup_file);
        
        // Use password from config, never hardcoded
        $command = $gbak . ' -b -v -ig -g -user SYSDBA -password ' . escapeshellarg($firebird_password) . ' ' . $db . ' ' . $backup;
        
        Log::instance()->add(Log::DEBUG, 'Executing backup command (password hidden)');
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            Session::instance()->set('flash_message', array(
                'type' => 'success',
                'text' => __('Backup created successfully: ') . $backup_file
            ));
            Log::instance()->add(Log::INFO, 'Backup created: ' . $backup_file);
        } else {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Backup failed. Error code: ') . $return_var . '. Check logs.'
            ));
            Log::instance()->add(Log::ERROR, 'Backup failed. Return code: ' . $return_var . ', Output: ' . implode("\n", $output));
        }
        
        $this->redirect('dbsetting');
    }
    
    /**
     * Restore database from backup
     */
    public function action_restore()
    {
        if ($this->request->method() !== 'POST') {
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate CSRF
        if (!$this->validate_csrf('restore')) {
            Session::instance()->set('flash_message', array(
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
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Firebird password not configured.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        try {
            $backup_file = $this->validate_path($backup_file, true);
            $restore_dir = $this->validate_path($restore_dir, false);
        } catch (Exception $e) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Path validation failed: ') . $e->getMessage()
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Determine extension from database_path (if it's a file) or default to GDB
        $extension = 'GDB';
        if (!empty($database_path)) {
            $db_info = pathinfo($database_path);
            if (isset($db_info['extension']) && !empty($db_info['extension'])) {
                $extension = $db_info['extension'];
            }
        }
        
        // Generate new restore path based on backup filename
        $backup_info = pathinfo($backup_file);
        $new_filename = $backup_info['filename'] . '.' . $extension;
        $new_restore_path = rtrim($restore_dir, '\\/') . DIRECTORY_SEPARATOR . $new_filename;
        
        // Stop service before restore
        //$this->stop_service();
        
        $gbak = escapeshellarg(rtrim($firebird_bin, '\\/') . DIRECTORY_SEPARATOR . 'gbak.exe');
        $backup = escapeshellarg($backup_file);
        $restore = '127.0.0.1:' . escapeshellarg($new_restore_path);
        
        $command = $gbak . ' -c -o -v -r -user SYSDBA -password ' . escapeshellarg($firebird_password) . ' ' . $backup . ' ' . $restore;
        
        exec($command, $output, $return_var);
        
        // Start service after restore
        //$this->start_service();
        
        if ($return_var === 0) {
            // Update configuration to new database path
            $this->update_module_database_path($new_restore_path);
            
            $backup_basename = basename($backup_file);
            $restored_basename = basename($new_restore_path);
            
            Session::instance()->set('flash_message', array(
                'type' => 'success',
                'text' => __('Database restored successfully from ') . $backup_basename . __(' to ') . $restored_basename
            ));
        } else {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Restore failed. Error code: ') . $return_var
            ));
            Log::instance()->add(Log::ERROR, 'Restore failed. Command: ' . $command);
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
            'FirebirdServerDefaultInstance',
            'FirebirdServerDefault',
            'FirebirdServer',
            'Firebird'
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
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $service = $this->find_firebird_service();
        
        if (!$service) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Firebird service not found.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $command = 'net stop ' . escapeshellarg($service);
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            Session::instance()->set('flash_message', array(
                'type' => 'success',
                'text' => __('Firebird service stopped.')
            ));
        } else {
            Session::instance()->set('flash_message', array(
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
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Security token validation failed.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $service = $this->find_firebird_service();
        
        if (!$service) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => __('Firebird service not found.')
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $command = 'net start ' . escapeshellarg($service);
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            Session::instance()->set('flash_message', array(
                'type' => 'success',
                'text' => __('Firebird service started.')
            ));
        } else {
            Session::instance()->set('flash_message', array(
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
                                return $path;
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
        
        // Check if file is writable
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
            Log::instance()->add(Log::ERROR, 'Empty database path provided');
            return false;
        }
        
        $escaped_path = str_replace("'", "\\'", $database_path);
        
        $new_content = preg_replace(
            "/^(?!\\s*\\/\\/)(\\s*'database_path'\\s*=>\\s*')[^']*(')/m",
            "\$1$escaped_path\$2",
            $content
        );
        
        if ($new_content === $content) {
            $new_content = preg_replace(
                '/^(?!\\s*\\/\\/)(\\s*"database_path"\\s*=>\\s*")[^"]*(")/m',
                "\$1$database_path\$2",
                $content
            );
        }
        
        if ($new_content === $content) {
            Log::instance()->add(Log::ERROR, 'Failed to find database_path in config');
            return false;
        }
        
        // Create backup
        $backup_path = $module_config_path . '.backup_' . date('Y-m-d_His');
        @copy($module_config_path, $backup_path);
        
        $result = file_put_contents($module_config_path, $new_content, LOCK_EX);
        if ($result === false) {
            Log::instance()->add(Log::ERROR, 'Failed to write module config file');
            return false;
        }
        
        // Clear config cache
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
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => 'Ошибка проверки токена безопасности. Пожалуйста, попробуйте снова.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        $config_content = $this->request->post('config_content');
        $module_config_path = MODPATH . 'dbsetting/config/dbsetting.php';
        
        if (empty($config_content) || !file_exists($module_config_path)) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => 'Неверная конфигурация или файл не найден.'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Validate PHP syntax before saving
        if (strpos($config_content, '<?php') === false) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => 'Конфигурация должна начинаться с PHP открывающего тега &lt;?php'
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check PHP syntax by evaluating in a temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'cfg_');
        file_put_contents($temp_file, $config_content);
        $syntax_check = shell_exec('php -l ' . escapeshellarg($temp_file) . ' 2>&1');
        unlink($temp_file);
        
        if (strpos($syntax_check, 'No syntax errors') === false) {
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => 'Синтаксическая ошибка PHP в конфигурации: ' . nl2br(HTML::chars($syntax_check))
            ));
            $this->redirect('dbsetting');
            return;
        }
        
        // Check if writable
        if (!is_writable($module_config_path)) {
            Session::instance()->set('flash_message', array(
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
            Session::instance()->set('flash_message', array(
                'type' => 'error',
                'text' => 'Не удалось сохранить файл конфигурации.'
            ));
            Log::instance()->add(Log::ERROR, 'Failed to write module config file: ' . $module_config_path);
        } else {
            Session::instance()->set('flash_message', array(
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
