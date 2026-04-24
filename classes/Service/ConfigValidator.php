<?php defined('SYSPATH') or die('No direct script access.');

class Service_ConfigValidator {
    
    /**
     * Validate that config directory is writable
     */
    public static function check_writable($path) {
        if (!file_exists($path)) {
            return array('writable' => false, 'error' => 'File not found');
        }
        return array('writable' => is_writable($path), 'error' => null);
    }
    
    /**
     * Validate Firebird configuration
     */
    public static function check_firebird_config($config) {
        $errors = array();
        
        $bin_path = $config->get('firebird_bin');
        $gbak = rtrim($bin_path, '\\/') . DIRECTORY_SEPARATOR . 'gbak.exe';
        
        if (!file_exists($gbak)) {
            $errors[] = 'gbak.exe not found at: ' . $gbak;
        }
        
        $password = $config->get('firebird_password');
        if (empty($password)) {
            $errors[] = 'Firebird password is not configured. Set firebird_password in config.';
        }
        
        return $errors;
    }
}