<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'backup_dir' => 'C:\Tes1111t',
    // Path to Firebird bin directory (gbak, isql, etc.)
    // Для Firebird 1.5.6 обычно используется именно этот путь
    'firebird_bin' => 'C:\\Program Files (x86)\\Firebird\\Firebird_1_5_6\\bin',
    
    // Firebird database password for SYSDBA user
    'firebird_password' => 'temp',  // Установите ваш реальный пароль!
    
    // Default database file path (used for backup/restore)
    'database_path' => 'C:\Program Files (x86)\Cardsoft\DuoSE\Access\ShieldPro_rest.gdb',
    
    // Restore directory (where restored database files will be placed)
    'restore_path' => 'D:\\rrr\\hl\\restore',
    
    // Backup directory
    'backup_dir' => 'C:\Tes1111t',
    
    // Firebird service name (для Firebird 1.5.6)
    'service_name' => 'FirebirdGuardianDefaultInstance',
    
    // Path to database.php config file
    'database_config_path' => APPPATH . 'config/database.php',
    
    // Web server port (default: 8080 for Apache)
    'web_server_port' => 8080,
);