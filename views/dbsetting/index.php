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
                                            <td><code><?php echo HTML::chars($dsn); ?></code></td>
                                            <td><?php echo isset($odbc_dsn_paths[$name]) ? HTML::chars($odbc_dsn_paths[$name]) : ""; ?></td>
                                        </tr>
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
                        </div>

                        <div class="form-group">
                            <label>Имя файла базы данных:</label>
                            <div class="input-group">
                                <input type="text" name="database_filename" id="database_filename"
                                    class="form-control input-sm"
                                    value="<?php echo HTML::chars($database_filename); ?>"
                                    placeholder="ShieldPro_rest.GDB" required>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-sm" onclick="browseDatabaseFolder()">
                                        <span class="glyphicon glyphicon-folder-open"></span> Обзор
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveDatabaseFilename()">
                                        <span class="glyphicon glyphicon-floppy-disk"></span> Сохранить
                                    </button>
                                </span>
                            </div>Ы
                            <small class="text-muted">Введите имя файла вручную или выберите папку с БД через "Обзор"</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="backup_dir">Папка для сохранения резервной копии:</label>
                            <div class="input-group">
                                <input type="text" name="backup_dir" id="backup_dir"
                                    class="form-control input-sm"
                                    value="<?php echo HTML::chars($backup_dir); ?>"
                                    placeholder="C:\service_skud\backups\" required>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveBackupDir()">
                                        <span class="glyphicon glyphicon-floppy-disk"></span> Сохранить
                                    </button>
                                </span>
                            </div>
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
                        
                        <button type="button" class="btn btn-success" onclick="startBackup()">
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
                <!-- Форма для запуска сервиса -->
                <form method="post" action="<?php echo URL::site('dbsetting/start_service'); ?>" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token_service) ? $csrf_token_service : ''; ?>">
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirmService('start');">
                        <span class="glyphicon glyphicon-play"></span> Запустить
                    </button>
                </form>
                <!-- Форма для остановки сервиса -->
                <form method="post" action="<?php echo URL::site('dbsetting/stop_service'); ?>" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token_service) ? $csrf_token_service : ''; ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirmService('stop');">
                        <span class="glyphicon glyphicon-stop"></span> Остановить
                    </button>
                </form>
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
<iframe id="explorerIframe" style="display:none;"></iframe>
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
function startBackup() {
    let outputDiv = document.getElementById('backup-output');
    
    if (!outputDiv) {
        outputDiv = document.createElement('div');
        outputDiv.id = 'backup-output';
        outputDiv.style.cssText = `
            background:#1e1e1e; 
            color:#0f0; 
            padding:15px; 
            margin:15px 0; 
            border:1px solid #444; 
            max-height:500px; 
            overflow-y:auto; 
            font-family:Consolas,monospace; 
            white-space:pre-wrap;
            position:relative;
        `;
        
        document.getElementById('backup-form').parentNode.appendChild(outputDiv);
    } else {
        outputDiv.innerHTML = '';
    }

    // Заголовок
    outputDiv.innerHTML += '<strong style="color:yellow">=== ЗАПУСК РЕЗЕРВНОГО КОПИРОВАНИЯ ===</strong><br><br>';

    var formData = new FormData(document.getElementById('backup-form'));
    formData.append('ajax', '1');

    fetch('<?php echo URL::site("dbsetting/backup"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        outputDiv.innerHTML += text.replace(/\n/g, '<br>');
        outputDiv.scrollTop = outputDiv.scrollHeight;
        
        // Кнопка закрыть внизу
        const closeContainer = document.createElement('div');
        closeContainer.style.textAlign = 'center';
        closeContainer.style.marginTop = '15px';
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '✕ Закрыть окно вывода';
        closeBtn.style.cssText = 'background:#c9302c; color:white; border:none; padding:8px 16px; font-size:14px; cursor:pointer;';
        closeBtn.onclick = () => outputDiv.remove();
        closeContainer.appendChild(closeBtn);
        
        outputDiv.appendChild(closeContainer);
    })
    .catch(err => {
        outputDiv.innerHTML += '<br><strong style="color:red">Ошибка соединения: ' + err.message + '</strong>';
    });
}

// Функция для выбора папки с базами данных
function browseDatabaseFolder() {
    // Создаем скрытый input для выбора директории
    // Используем nwworkaround для выбора папки
    var folderInput = document.createElement('input');
    folderInput.type = 'file';
    folderInput.webkitdirectory = true;
    folderInput.directory = true;
    folderInput.style.display = 'none';
    
    folderInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            // Получаем путь к первому выбранному файлу
            var filePath = this.files[0].webkitRelativePath;
            var folderPath = '';
            
            // Пытаемся получить полный путь
            if (this.files[0].path) {
                // В некоторых браузерах (Electron, старые Chrome) есть path
                folderPath = this.files[0].path;
                var lastSeparator = folderPath.lastIndexOf('\\');
                if (lastSeparator > 0) {
                    folderPath = folderPath.substring(0, lastSeparator);
                }
            } else if (this.value) {
                // Стандартный способ - берем из значения
                folderPath = this.value;
                // Убираем имя файла, оставляем только папку
                var lastSeparator = Math.max(folderPath.lastIndexOf('\\'), folderPath.lastIndexOf('/'));
                if (lastSeparator > 0) {
                    folderPath = folderPath.substring(0, lastSeparator);
                }
            }
            
            if (folderPath) {
                document.getElementById('database_dir').value = folderPath;
                // Сохраняем выбранный путь для следующего раза
                saveBrowsePath(folderPath);
                updateBackupDatabasePath();
                alert('Выбрана папка: ' + folderPath + '\n\nТеперь укажите имя файла базы данных в поле выше.');
            } else {
                alert('Не удалось определить путь к папке. Пожалуйста, укажите путь вручную.');
            }
        }
    });
    
    document.body.appendChild(folderInput);
    folderInput.click();
    document.body.removeChild(folderInput);
}

// Функция для сохранения пути обзора
function saveBrowsePath(path) {
    var formData = new FormData();
    formData.append('browse_path', path);
    formData.append('csrf_token', csrf_token);
    
    fetch('<?php echo URL::site("dbsetting/save_browse_path"); ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).catch(error => console.log('Failed to save browse path:', error));
}

// Функция для сохранения папки резервных копий
function saveBackupDir() {
    var backupDir = document.getElementById('backup_dir').value;
    if (!backupDir) {
        alert('Пожалуйста, укажите папку для сохранения резервных копий.');
        return;
    }
    
    var saveBtn = event.target;
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="glyphicon glyphicon-refresh spinning"></span>';
    saveBtn.disabled = true;
    
    var formData = new FormData();
    formData.append('backup_dir', backupDir);
    formData.append('csrf_token', csrf_token);
    
    fetch('<?php echo URL::site("dbsetting/save_backup_dir"); ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        
        if (data.success) {
            alert('Папка для резервных копий сохранена: ' + backupDir);
        } else {
            alert('Ошибка: ' + data.message);
        }
    })
    .catch(error => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
        alert('Ошибка: ' + error.message);
    });
}

// Функция для запуска бэкапа с прогрессом
function startBackupWithProgress(event) {
    event.preventDefault();
    
    var backupDir = document.getElementById('backup_dir').value;
    var databasePath = document.getElementById('backup_database_path').value;
    
    if (!backupDir) {
        alert('Пожалуйста, укажите папку для сохранения резервной копии.');
        return false;
    }
    
    if (!databasePath) {
        alert('Пожалуйста, укажите путь к базе данных.');
        return false;
    }
    
    // Показываем модальное окно с прогрессом
    $('#backupProgressModal').modal({
        backdrop: 'static',
        keyboard: false
    });
    
    var outputArea = document.getElementById('backupOutput');
    outputArea.innerHTML = 'Подключение к серверу...\n';
    
    var formData = new FormData();
    formData.append('database_path', databasePath);
    formData.append('backup_dir', backupDir);
    formData.append('csrf_token', csrf_token);
    
    fetch('<?php echo URL::site("dbsetting/backup"); ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        
        function readStream() {
            reader.read().then(({done, value}) => {
                if (done) {
                    outputArea.innerHTML += '\n\n--- Резервное копирование завершено ---\n';
                    document.getElementById('closeBackupModal').disabled = false;
                    return;
                }
                
                const chunk = decoder.decode(value, {stream: true});
                outputArea.innerHTML += chunk;
                outputArea.scrollTop = outputArea.scrollHeight;
                readStream();
            }).catch(error => {
                outputArea.innerHTML += '\n\nОшибка: ' + error.message + '\n';
                document.getElementById('closeBackupModal').disabled = false;
            });
        }
        
        readStream();
    })
    .catch(error => {
        outputArea.innerHTML += '\n\nОшибка подключения: ' + error.message + '\n';
        document.getElementById('closeBackupModal').disabled = false;
    });
    
    return false;
}

// Обновляем форму бэкапа при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    var backupForm = document.getElementById('backup-form');
    if (backupForm) {
        backupForm.onsubmit = startBackupWithProgress;
    }
});
// // Monitor changes to directory and filename fields
// document.addEventListener('DOMContentLoaded', function() {
//     var dirField = document.getElementById('database_dir');
//     var filenameField = document.getElementById('database_filename');
    
//     if (dirField) {
//         dirField.addEventListener('change', updateBackupDatabasePath);
//         dirField.addEventListener('keyup', updateBackupDatabasePath);
//     }
//     if (filenameField) {
//         filenameField.addEventListener('change', updateBackupDatabasePath);
//         filenameField.addEventListener('keyup', updateBackupDatabasePath);
//     }
    
//     // Initial update
//     updateBackupDatabasePath();
    
//     // Auto-refresh preview filename every second for timestamp
//     function updatePreviewFilename() {
//         var dbFilename = document.getElementById('database_filename').value;
//         if (dbFilename) {
//             var baseName = dbFilename.replace(/\.[^/.]+$/, '');
//             var timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '').replace('T', '_');
//             var previewSpan = document.querySelector('.well.well-sm');
//             if (previewSpan) {
//                 previewSpan.textContent = baseName + '_' + timestamp + '.fbk';
//             }
//         }
//     }
    
//     // Update preview every second (for timestamp)
//     setInterval(updatePreviewFilename, 1000);
// });
// Открыть папку с базой данных в проводнике
function openDatabaseFolder() {
    var dir = document.getElementById('database_dir').value.trim();
    if (!dir) {
        alert('Сначала сохраните путь к папке с базой данных!');
        return;
    }
    
    // Способ 1: через создание ссылки
    var link = document.createElement('a');
    link.href = 'file:///' + dir.replace(/\\/g, '/');
    link.click();
    
    // Способ 2: через iframe (запасной)
    var iframe = document.getElementById('explorerIframe');
    if (iframe) {
        iframe.src = 'file:///' + dir.replace(/\\/g, '/');
    }
    
    alert('Проводник должен открыться.\nЕсли не открылся, скопируйте путь:\n' + dir);
}

// Открыть папку с бэкапами в проводнике
function openBackupFolder() {
    var dir = document.getElementById('backup_dir').value.trim();
    if (!dir) {
        alert('Сначала сохраните путь к папке резервного копирования!');
        return;
    }
    
    var link = document.createElement('a');
    link.href = 'file:///' + dir.replace(/\\/g, '/');
    link.click();
    
    var iframe = document.getElementById('explorerIframe');
    if (iframe) {
        iframe.src = 'file:///' + dir.replace(/\\/g, '/');
    }
    
    alert('Проводник должен открыться.\nЕсли не открылся, скопируйте путь:\n' + dir);
}
</script>