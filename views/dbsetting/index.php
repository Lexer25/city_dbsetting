<div class="container">
    <h2>Настройки базы данных <small>Firebird ODBC</small></h2>
    
    <?php if (Session::instance()->get('flash_message')): ?>
        <?php
        $flash = Session::instance()->get('flash_message');
        $type = Arr::get($flash, 'type', 'info');
        $text = Arr::get($flash, 'text', '');
        $alert_class = 'alert-' . ($type === 'error' ? 'danger' : $type);
        Session::instance()->delete('flash_message');
        ?>
        <div class="alert <?php echo $alert_class; ?>">
            <?php echo HTML::chars($text); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($db_error) && !empty($db_error)): ?>
        <div class="alert alert-warning">
            <h4><i class="glyphicon glyphicon-warning-sign"></i> Ошибка подключения к базе данных</h4>
            <p>Текущее подключение к базе данных не работает с ошибкой: <code><?php echo HTML::chars($db_error); ?></code></p>
            <p>Этот модуль позволяет исправить подключение к базе данных. Пожалуйста, выберите рабочий DSN из списка ниже.</p>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <!-- ODBC Selection -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">ODBC База данных <small>DSN из реестра Windows</small></h3>
                </div>
                <div class="panel-body">
                    <form action="<?php echo URL::site('dbsetting/select_dsn'); ?>" method="post" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_select_dsn; ?>">
                        <div class="form-group">
                            <label>Текущий: <strong><?php echo HTML::chars($current_dsn); ?></strong></label>
                            <small class="text-muted">(сохранено в config/database.php)</small>
                        </div>
                        <div class="form-group" style="margin-left: 20px;">
                            <label for="dsn">Переключиться на:</label>
                            <select name="dsn" id="dsn" class="form-control input-sm">
                                <?php foreach ($odbc_dsns as $name => $dsn): ?>
                                    <option value="<?php echo HTML::chars($name); ?>" <?php echo ($dsn === $current_dsn) ? 'selected' : ''; ?>>
                                        <?php echo HTML::chars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Переключить</button>
                    </form>
                    
                    <div style="margin-top: 20px;">
                        <h5>Все доступные DSN:</h5>
                        <div class="well" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-condensed">
                                <thead><tr><th>Имя</th><th>DSN</th><th>Путь к файлу БД</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($odbc_dsns as $name => $dsn): ?>
                                        <tr class="<?php echo ($dsn === $current_dsn) ? 'success' : ''; ?>">
                                            <td><strong><?php echo HTML::chars($name); ?></strong></td>
                                            <td><code><?php echo HTML::chars($dsn); ?></code></td><td><?php echo isset($odbc_dsn_paths[$name]) ? HTML::chars($odbc_dsn_paths[$name]) : ""; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <!-- Backup -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">Резервное копирование</h3>
                </div>
                <div class="panel-body">
                    <form action="<?php echo URL::site('dbsetting/backup'); ?>" method="post" id="backup-form">
                        <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token_backup) ? $csrf_token_backup : ''; ?>">
                        <input type="hidden" name="database_path" id="backup_database_path" value="<?php echo HTML::chars($database_path); ?>">
                        
                        <div class="form-group">
                            <label>Путь к папке с базой данных:</label>
                            <div class="input-group">
                                <input type="text" name="database_dir" id="database_dir"
                                       class="form-control input-sm"
                                       value="<?php echo HTML::chars($database_dir); ?>"
                                       placeholder="D:\rrr\hl" required>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveDatabaseDir()">
                                        <span class="glyphicon glyphicon-floppy-disk"></span> Сохранить путь
                                    </button>
                                </span>
                            </div>
                            <small class="text-muted">Папка, в которой находится файл базы данных</small>
                        </div>

                        <div class="form-group">
                            <label>Имя файла базы данных:</label>
                            <div class="input-group">
                                <input type="text" name="database_filename" id="database_filename"
                                       class="form-control input-sm"
                                       value="<?php echo HTML::chars($database_filename); ?>"
                                       placeholder="ShieldPro_rest.GDB" required>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-sm" onclick="browseDatabaseFile()">
                                        <span class="glyphicon glyphicon-folder-open"></span> Обзор
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveDatabaseFilename()">
                                        <span class="glyphicon glyphicon-floppy-disk"></span> Сохранить
                                    </button>
                                </span>
                            </div>
                            <small class="text-muted">Имя файла базы данных (выберите через Обзор или введите вручную)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="backup_dir">Папка для сохранения резервной копии:</label>
                            <input type="text" name="backup_dir" id="backup_dir"
                                   class="form-control input-sm"
                                   value="<?php echo HTML::chars($backup_dir); ?>"
                                   placeholder="C:\service_skud\" required>
                            <small class="text-muted">По умолчанию: C:\service_skud\</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Сгенерированное имя файла резервной копии:</label>
                            <div class="well well-sm" style="margin-bottom: 0; font-family: monospace;">
                                <?php
                                $db_filename = pathinfo($database_path, PATHINFO_FILENAME);
                                $timestamp = date('Y-m-d_His');
                                $preview_filename = $db_filename . '_' . $timestamp . '.fbk';
                                echo HTML::chars($preview_filename);
                                ?>
                            </div>
                            <small class="text-muted">Формат: имя_базы_данных_год-месяц-день_время.fbk</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success" onclick="return confirmBackup();">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Создать резервную копию
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <!-- Service Status -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Сервис Firebird</h3>
                </div>
                <div class="panel-body">
                    <?php
                    $status = $service_status;
                    $label_class = ($status === 'running') ? 'label-success' :
                                   (($status === 'stopped') ? 'label-danger' : 'label-default');
                    ?>
                    <div class="form-inline">
                        <div class="form-group">
                            <label>Статус:</label>
                            <span class="label <?php echo $label_class; ?>" style="margin-left: 10px;">
                                <?php
                                $status_text = $status;
                                if ($status === 'running') $status_text = 'запущен';
                                elseif ($status === 'stopped') $status_text = 'остановлен';
                                elseif ($status === 'unknown') $status_text = 'неизвестен';
                                echo HTML::chars($status_text);
                                ?>
                            </span>
                        </div>
                        <div class="form-group" style="margin-left: 20px;">
                            <a href="<?php echo URL::site('dbsetting/start_service?csrf_token=' . (isset($csrf_token_service) ? $csrf_token_service : $csrf_token_path)); ?>" class="btn btn-success btn-sm" onclick="return confirmService('start');">
                                <span class="glyphicon glyphicon-play"></span> Запустить
                            </a>
                            <a href="<?php echo URL::site('dbsetting/stop_service?csrf_token=' . (isset($csrf_token_service) ? $csrf_token_service : $csrf_token_path)); ?>" class="btn btn-danger btn-sm" onclick="return confirmService('stop');">
                                <span class="glyphicon glyphicon-stop"></span> Остановить
                            </a>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Restore -->
                    <h5>Восстановление базы данных</h5>
                    <form action="<?php echo URL::site('dbsetting/restore'); ?>" method="post" class="form-inline" onsubmit="return confirmRestore();">
                        <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token_restore) ? $csrf_token_restore : $csrf_token_path; ?>">
                        <div class="form-group" style="width: 70%;">
                            <?php if (!empty($backup_files)): ?>
                                <select name="backup_file" class="form-control input-sm" required style="width: 100%;">
                                    <option value="">-- выберите файл для восстановления --</option>
                                    <?php foreach ($backup_files as $file): ?>
                                        <?php $full_path = $backup_dir . DIRECTORY_SEPARATOR . $file; ?>
                                        <option value="<?php echo HTML::chars($full_path); ?>">
                                            <?php echo HTML::chars($file); ?>
                                            (<?php echo date('Y-m-d H:i:s', filemtime($full_path)); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Файлы из папки: <?php echo HTML::chars($backup_dir); ?></small>
                            <?php else: ?>
                                <div class="alert alert-warning" style="margin-bottom: 0;">
                                    <p>В папке <code><?php echo HTML::chars($backup_dir); ?></code> нет файлов резервных копий.</p>
                                    <p>Пожалуйста, укажите полный путь к файлу .fbk вручную:</p>
                                    <input type="text" name="backup_file" class="form-control input-sm" placeholder="C:\backup\backup.fbk" required style="width: 100%;">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <span class="glyphicon glyphicon-import"></span> Восстановить
                        </button>
                    </form>
                    <p class="help-block small">Сервис будет остановлен во время восстановления.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <!-- System Information -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Системная информация</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="well well-sm">
                                <h5>PHP</h5>
                                <h4><?php echo PHP_VERSION; ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="well well-sm">
                                <h5>Kohana</h5>
                                <h4><?php echo Kohana::VERSION; ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="well well-sm">
                                <h5>ОС</h5>
                                <h4><?php echo PHP_OS; ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="well well-sm">
                                <h5>Модуль</h5>
                                <h4><?php echo DBSETTING_VERSION; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success" style="margin-top: 20px;">
                        <h4><i class="glyphicon glyphicon-edit"></i> Полный доступ к управлению</h4>
                        <p>Все функции управления базой данных и сервисом Firebird доступны для редактирования.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <strong><i class="glyphicon glyphicon-exclamation-sign"></i> Внимание!</strong> Эти настройки влияют на базу данных и сервис. Изменения должны выполняться только администратором системы.
    </div>
</div>

<style>
.glyphicon.spinning {
    animation: spin 1s infinite linear;
    -webkit-animation: spin2 1s infinite linear;
}
@keyframes spin {
    from { transform: scale(1) rotate(0deg); }
    to { transform: scale(1) rotate(360deg); }
}
@-webkit-keyframes spin2 {
    from { -webkit-transform: rotate(0deg); }
    to { -webkit-transform: rotate(360deg); }
}
</style>

<script>
// CSRF token for AJAX requests
var csrf_token = '<?php echo $csrf_token_path; ?>';
console.log('dbsetting script loaded');

// Confirm backup
function confirmBackup() {
    var backupDir = document.getElementById('backup_dir').value;
    if (!backupDir) {
        alert('Пожалуйста, укажите папку для сохранения резервной копии.');
        return false;
    }
    return confirm('Внимание! Создание резервной копии может занять несколько минут.\n\nПродолжить?');
}

// Confirm restore
function confirmRestore() {
    return confirm('ВНИМАНИЕ! Восстановление базы данных заменит текущую базу данных.\n\n' +
                   'Рекомендуется сначала создать резервную копию.\n\n' +
                   'Сервис Firebird будет временно остановлен.\n\n' +
                   'Вы уверены, что хотите продолжить?');
}

// Confirm service action
function confirmService(action) {
    var actionText = (action === 'start') ? 'запустить' : 'остановить';
    return confirm('Вы уверены, что хотите ' + actionText + ' сервис Firebird?\n\n' +
                   'Это может повлиять на работу приложения.');
}

function browseDatabaseFile() {
    // Create a file input element
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = '.fdb,.FDB,.gdb,.GDB';
    fileInput.style.display = 'none';
    
    // Add change event listener
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            console.log('File selected:', this.files[0]);
            var filePath = '';
            
            // Try to get full path from various properties
            if (this.files[0].path) {
                filePath = this.files[0].path;
            } else if (this.value) {
                filePath = this.value;
                // Remove fakepath prefix if present
                if (filePath.indexOf('C:\\fakepath\\') === 0) {
                    filePath = filePath.substring(12);
                }
            } else {
                filePath = this.files[0].name;
            }
            
            console.log('Selected filePath:', filePath);
            
            // Split into directory and filename
            var lastSeparator = Math.max(filePath.lastIndexOf('\\'), filePath.lastIndexOf('/'));
            if (lastSeparator >= 0) {
                var dir = filePath.substring(0, lastSeparator);
                var filename = filePath.substring(lastSeparator + 1);
                document.getElementById('database_dir').value = dir;
                document.getElementById('database_filename').value = filename;
            } else {
                // No separator, treat as filename only
                document.getElementById('database_filename').value = filePath;
            }
            
            // Check if the path looks like a filename only
            if (filePath && !filePath.includes('\\') && !filePath.includes('/') && !filePath.includes(':')) {
                console.warn('Browser provided only filename, not full path.');
                alert('Внимание: Браузер не предоставляет полный путь к файлу.\n\n' +
                      'Выбран только файл: ' + filePath + '\n\n' +
                      'Пожалуйста, скопируйте полный путь к файлу из проводника Windows и вставьте его в поле вручную.\n\n' +
                      'Или введите путь к папке и имя файла отдельно.');
            }
        }
    });
    
    // Trigger file dialog
    document.body.appendChild(fileInput);
    fileInput.click();
    document.body.removeChild(fileInput);
}

function saveDatabaseDir() {
    var dbDir = document.getElementById('database_dir').value;
    if (!dbDir) {
        alert('Пожалуйста, укажите путь к папке базы данных.');
        return;
    }
    
    // Validate directory path format
    if (!dbDir.match(/^[a-zA-Z]:\\/)) {
        if (!confirm('Путь "' + dbDir + '" не похож на полный путь к папке Windows (например, D:\\rrr\\hl).\n\nПродолжить?')) {
            return;
        }
    }
    
    // Show loading indicator
    var saveBtn = document.querySelector('button[onclick="saveDatabaseDir()"]');
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="glyphicon glyphicon-refresh spinning"></span> Сохранение...';
    saveBtn.disabled = true;
    
    // Create form data
    var formData = new FormData();
    formData.append('database_dir', dbDir);
    formData.append('csrf_token', csrf_token);
    
    // Send POST request
    fetch('<?php echo URL::site("dbsetting/save_database_dir"); ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json().then(data => ({ ok: true, data }));
        } else {
            return response.text().then(text => ({ ok: false, text: text.substring(0, 500) }));
        }
    })
    .then(result => {
        // Restore button
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        if (result.ok && result.data) {
            var data = result.data;
            if (data.success) {
                var message = 'Путь к папке успешно сохранен в конфигурации.';
                if (data.file_exists === false) {
                    message += '\n\nВнимание: Файл базы данных не найден по новому пути.\n' +
                               'Пожалуйста, проверьте правильность пути и имени файла.';
                }
                alert(message);
                // Update backup form hidden field
                if (data.new_full_path) {
                    document.getElementById('backup_database_path').value = data.new_full_path;
                }
            } else {
                alert('Ошибка: ' + (data.message || 'Не удалось сохранить путь.'));
            }
        } else {
            console.error('Non-JSON response:', result.text);
            alert('Сервер вернул некорректный ответ. Проверьте консоль для деталей.\n\n' +
                  'Возможно, проблема с правами доступа или синтаксисом PHP.');
        }
    })
    .catch(error => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Ошибка сети: ' + error.message);
        console.error('Fetch error:', error);
    });
}

function saveDatabaseFilename() {
    console.log('saveDatabaseFilename called');
    var dbFilename = document.getElementById('database_filename').value;
    console.log('dbFilename:', dbFilename);
    
    if (!dbFilename) {
        alert('Пожалуйста, укажите имя файла базы данных.');
        return;
    }
    
    // Validate filename (no path separators or special characters)
    if (dbFilename.match(/[\\\/\:\*\?\"\<\>\|]/)) {
        alert('Имя файла содержит недопустимые символы.\n\n' +
              'Разрешенные символы: буквы, цифры, пробелы, точка, дефис, подчеркивание.\n' +
              'Запрещены: \\ / : * ? " < > |');
        return;
    }
    
    // Show loading indicator
    var saveBtn = document.querySelector('button[onclick="saveDatabaseFilename()"]');
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="glyphicon glyphicon-refresh spinning"></span> Сохранение...';
    saveBtn.disabled = true;
    
    // Create form data
    var formData = new FormData();
    formData.append('database_filename', dbFilename);
    formData.append('csrf_token', csrf_token);
    
    // Send POST request
    fetch('<?php echo URL::site("dbsetting/save_database_filename"); ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json().then(data => ({ ok: true, data }));
        } else {
            return response.text().then(text => ({ ok: false, text: text.substring(0, 500) }));
        }
    })
    .then(result => {
        // Restore button
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        if (result.ok && result.data) {
            var data = result.data;
            if (data.success) {
                var message = 'Имя файла базы данных успешно сохранено в конфигурации.';
                if (data.file_exists === false) {
                    message += '\n\nВнимание: Файл базы данных не найден по новому пути.\n' +
                               'Пожалуйста, проверьте правильность пути и имени файла.';
                }
                alert(message);
                // Update backup form hidden field
                if (data.new_full_path) {
                    document.getElementById('backup_database_path').value = data.new_full_path;
                }
            } else {
                alert('Ошибка: ' + (data.message || 'Не удалось сохранить имя файла.'));
            }
        } else {
            console.error('Non-JSON response:', result.text);
            alert('Сервер вернул некорректный ответ. Проверьте консоль для деталей.\n\n' +
                  'Возможно, проблема с правами доступа или синтаксисом PHP.');
        }
    })
    .catch(error => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Ошибка сети: ' + error.message);
        console.error('Fetch error:', error);
    });
}

// Update backup database path when directory or filename changes
function updateBackupDatabasePath() {
    var dir = document.getElementById('database_dir').value;
    var filename = document.getElementById('database_filename').value;
    if (dir && filename) {
        var fullPath = dir.replace(/[\\\/]$/, '') + '\\' + filename;
        document.getElementById('backup_database_path').value = fullPath;
    }
}

// Monitor changes to directory and filename fields
document.addEventListener('DOMContentLoaded', function() {
    var dirField = document.getElementById('database_dir');
    var filenameField = document.getElementById('database_filename');
    
    if (dirField) {
        dirField.addEventListener('change', updateBackupDatabasePath);
        dirField.addEventListener('keyup', updateBackupDatabasePath);
    }
    if (filenameField) {
        filenameField.addEventListener('change', updateBackupDatabasePath);
        filenameField.addEventListener('keyup', updateBackupDatabasePath);
    }
    
    // Initial update
    updateBackupDatabasePath();
    
    // Auto-refresh preview filename every second for timestamp
    function updatePreviewFilename() {
        var dbFilename = document.getElementById('database_filename').value;
        if (dbFilename) {
            var baseName = dbFilename.replace(/\.[^/.]+$/, '');
            var timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '').replace('T', '_');
            var previewSpan = document.querySelector('.well.well-sm');
            if (previewSpan) {
                previewSpan.textContent = baseName + '_' + timestamp + '.fbk';
            }
        }
    }
    
    // Update preview every second (for timestamp)
    setInterval(updatePreviewFilename, 1000);
});
</script>