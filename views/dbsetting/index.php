<?php
echo defined('DBSETTING_VERSION') ? DBSETTING_VERSION : '';
?>

<div class="container">
    <h2>Настройки базы данных <small>Firebird ODBC</small></h2>
    <div class="alert alert-success" style="margin-top: 20px;">
        <h4><i class="glyphicon glyphicon-edit"></i> Полный доступ к управлению</h4>
        <p>Все функции управления базой данных и сервисом Firebird доступны для редактирования.</p>
    </div>

    <?php if (Session::instance()->get('flash_message_dbsetting')): ?>
        <?php
        $flash = Session::instance()->get('flash_message_dbsetting');
        $type = Arr::get($flash, 'type', 'info');
        $text = Arr::get($flash, 'text', '');
        $alert_class = 'alert-' . ($type === 'error' ? 'danger' : $type);
        Session::instance()->delete('flash_message_dbsetting');
        ?>
        <div class="alert <?php echo $alert_class; ?>">
            <?php echo $text; ?>
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
                            </div>
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

                        <button type="button" class="btn btn-success" onclick="startBackup()" id="backupBtn">
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
                    <form action="<?php echo URL::site('dbsetting/restore'); ?>" method="post" id="restore-form">
                        <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token_restore) ? $csrf_token_restore : $csrf_token_path; ?>">
                        <div class="form-group" style="width: 100%;">
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
                        <button type="button" class="btn btn-warning btn-sm" onclick="startRestore()" id="restoreBtn">
                            <span class="glyphicon glyphicon-import"></span> Восстановить
                        </button>
                    </form>
                    <p class="help-block small">Восстановление выполняется через работающий Firebird сервис. Файл создается в папке restore_path.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- Кнопка редактирования конфигурации -->
    <!-- ============================================ -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Конфигурация модуля</h3>
        </div>
        <div class="panel-body text-center">
            <a href="<?php echo URL::site('dbsetting/edit_config'); ?>" class="btn btn-warning btn-md">
                <span class="glyphicon glyphicon-edit"></span> Редактировать конфигурацию
            </a>
            <p class="help-block" style="margin-top: 10px;">
                <small>Прямое редактирование файла <code>dbsetting/config/dbsetting.php</code><br>
                (Пароль Firebird, пути к папкам и др.)</small>
            </p>
            <div class="alert alert-warning">
                <strong><i class="glyphicon glyphicon-exclamation-sign"></i> Внимание!</strong> Эти настройки влияют на базу данных и сервис. Изменения должны выполняться только администратором системы.
            </div>
        </div>
    </div>
    <!-- ============================================ -->

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
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- Модальное окно для бэкапа и восстановления -->
    <!-- ============================================ -->
    <div class="modal fade" id="processModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-body text-center" style="padding: 40px 30px;">
                    <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                        <span class="sr-only">Загрузка...</span>
                    </div>
                    <h4 style="margin-top: 20px;" id="processModalTitle">Выполняется операция...</h4>
                    <p class="text-muted" id="processModalText">Пожалуйста, подождите. Это может занять несколько минут.</p>
                    <div id="processModalStatus" style="margin-top: 15px; font-size: 13px; color: #666;"></div>
                </div>
                <div class="modal-footer" style="text-align: center; border-top: none; padding-top: 0;">
                    <button type="button" class="btn btn-default" id="processModalClose" disabled style="display:none;">
                        <span class="glyphicon glyphicon-ok"></span> Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ============================================ -->

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
.spinner-border {
    display: inline-block;
    width: 4rem;
    height: 4rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border .75s linear infinite;
}
@keyframes spinner-border {
    to { transform: rotate(360deg); }
}
.text-primary { color: #007bff; }
</style>
<script>
// Показать модальное окно
function showProcessModal(title, text, status) {
    document.getElementById('processModalTitle').textContent = title;
    document.getElementById('processModalText').textContent = text;
    document.getElementById('processModalStatus').innerHTML = status || '';
    document.getElementById('processModalClose').style.display = 'none';
    document.getElementById('processModalClose').disabled = true;
    $('#processModal').modal({
        backdrop: 'static',
        keyboard: false
    });
}


// Обновить статус в модальном окне
function updateProcessModal(title, text, finished) {
    if (title) {
        document.getElementById('processModalTitle').textContent = title;
    }
    if (text) {
        document.getElementById('processModalText').innerHTML = text;
    }
    if (finished) {
        document.getElementById('processModalClose').style.display = 'inline-block';
        document.getElementById('processModalClose').disabled = false;
        document.getElementById('processModalClose').innerHTML = '<span class="glyphicon glyphicon-ok"></span> Закрыть';
        document.getElementById('processModalClose').onclick = function() {
            try {
                // Пробуем через Bootstrap jQuery
                if (typeof $ !== 'undefined') {
                    $('#processModal').modal('hide');
                }
                // Если не сработало - просто закрываем через DOM
                var modal = document.getElementById('processModal');
                if (modal) {
                    modal.classList.remove('in');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
                location.reload();
            } catch(e) {
                location.reload();
            }
        };
        
        // АВТОМАТИЧЕСКОЕ ЗАКРЫТИЕ ЧЕРЕЗ 2 СЕКУНДЫ
        setTimeout(function() {
            try {
                if (typeof $ !== 'undefined') {
                    $('#processModal').modal('hide');
                } else {
                    var modal = document.getElementById('processModal');
                    if (modal) {
                        modal.classList.remove('in');
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        var backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    }
                }
                location.reload();
            } catch(e) {
                location.reload();
            }
        }, 2000);
    }
}

// Функция для запуска бэкапа со спиннером
function startBackup() {
    var backupDir = document.getElementById('backup_dir').value;
    if (!backupDir) {
        alert('Пожалуйста, укажите папку для сохранения резервной копии.');
        return;
    }
    
    if (!confirm('Внимание! Создание резервной копии может занять несколько минут.\n\nПродолжить?')) {
        return;
    }
    
    showProcessModal('🔄 Создание резервной копии', 'Идет создание резервной копии базы данных...', 'Подготовка...');
    
    var formData = new FormData(document.getElementById('backup-form'));
    formData.append('ajax', '1');

    fetch('<?php echo URL::site("dbsetting/backup"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        // Закрываем модальное окно
        try {
            if (typeof $ !== 'undefined') {
                $('#processModal').modal('hide');
            } else {
                var modal = document.getElementById('processModal');
                if (modal) {
                    modal.classList.remove('in');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
            }
        } catch(e) {}
        
        if (text.indexOf('✅ Резервная копия успешно создана') !== -1) {
            var fileMatch = text.match(/Файл:\s*([^\n]+)/);
            var sizeMatch = text.match(/Размер:\s*([^\s]+)/);
            var msg = '✅ Резервная копия успешно создана!\n\n';
            if (fileMatch) msg += 'Файл: ' + fileMatch[1] + '\n';
            if (sizeMatch) msg += 'Размер: ' + sizeMatch[1] + '\n';
            alert(msg);
            location.reload();
        } else if (text.indexOf('❌ Ошибка') !== -1) {
            alert('❌ Ошибка создания резервной копии!\n\n' + text.replace(/\n/g, '\n'));
        } else {
            alert('⚠️ Неизвестный ответ сервера.\n\n' + text.replace(/\n/g, '\n'));
        }
    })
    .catch(err => {
        try {
            if (typeof $ !== 'undefined') {
                $('#processModal').modal('hide');
            }
        } catch(e) {}
        alert('❌ Ошибка соединения: ' + err.message);
    });
}

// Функция для запуска восстановления со спиннером
function startRestore() {
    var backupFile = document.querySelector('select[name="backup_file"]');
    if (!backupFile || !backupFile.value) {
        alert('Пожалуйста, выберите файл для восстановления.');
        return;
    }
    
    if (!confirm('ВНИМАНИЕ! Будет выполнено восстановление базы данных.\n\n' +
                 'Это может занять несколько минут.\n\n' +
                 'Продолжить?')) {
        return;
    }
    
    showProcessModal('🔄 Восстановление базы данных', 'Идет восстановление базы данных...', 'Подготовка...');
    
    var formData = new FormData(document.getElementById('restore-form'));
    formData.append('ajax', '1');

    fetch('<?php echo URL::site("dbsetting/restore"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        // Закрываем модальное окно
        try {
            if (typeof $ !== 'undefined') {
                $('#processModal').modal('hide');
            } else {
                var modal = document.getElementById('processModal');
                if (modal) {
                    modal.classList.remove('in');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
            }
        } catch(e) {}
        
        if (text.indexOf('✅ База данных успешно восстановлена') !== -1) {
            var fileMatch = text.match(/Файл:\s*([^\n]+)/);
            var targetDir = '<?php echo dirname($database_path); ?>';
            var targetFile = '<?php echo basename($database_path); ?>';
            
            var msg = '✅ БАЗА ДАННЫХ ВОССТАНОВЛЕНА!\n\n';
            if (fileMatch) msg += '📁 Восстановленный файл: ' + fileMatch[1] + '\n\n';
            msg += '⚠️ ДАЛЕЕ НЕОБХОДИМО ВРУЧНУЮ ЗАМЕНИТЬ ФАЙЛ:\n';
            msg += '1. Остановить Firebird сервис\n';
            msg += '2. Скопировать ' + (fileMatch ? fileMatch[1] : 'файл') + ' в папку\n';
            msg += '   ' + targetDir + '\n';
            msg += '3. Переименовать в ' + targetFile + '\n';
            msg += '4. Запустить Firebird сервис';
            alert(msg);
            location.reload();
        } else if (text.indexOf('❌ Ошибка') !== -1) {
            alert('❌ Ошибка восстановления!\n\n' + text.replace(/\n/g, '\n'));
        } else {
            alert('⚠️ Неизвестный ответ сервера.\n\n' + text.replace(/\n/g, '\n'));
        }
    })
    .catch(err => {
        try {
            if (typeof $ !== 'undefined') {
                $('#processModal').modal('hide');
            }
        } catch(e) {}
        alert('❌ Ошибка соединения: ' + err.message);
    });
}
</script>