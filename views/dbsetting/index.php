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
        <div id="flash-result" class="alert <?php echo $alert_class; ?> alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
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

</div>

<!-- ============================================ -->
<!-- Модальное окно для бэкапа и восстановления (jQuery UI) -->
<!-- ============================================ -->
<div id="processModal" style="display:none;" title="Выполняется операция">
    <div style="text-align: center; padding: 10px 0;">
        <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
            <span class="sr-only">Загрузка...</span>
        </div>
        <h4 style="margin-top: 20px;" id="processModalTitle">Выполняется операция...</h4>
        <p class="text-muted" id="processModalText">Пожалуйста, подождите. Это может занять несколько минут.</p>
        
        <!-- ============================================ -->
        <!-- ВРЕМЯ СТАРТА И ТАЙМЕР -->
        <!-- ============================================ -->
        <div style="margin-top: 15px; font-size: 13px; color: #666;">
            <div>▶️ Старт: <span id="processStartTime">--:--:--</span></div>
            <div style="margin-top: 5px; font-size: 18px; font-weight: bold; color: #007bff;">
                ⏱️ <span id="processTimer">00:00</span>
            </div>
        </div>
        <!-- ============================================ -->
        
        <div id="processModalStatus" style="margin-top: 10px; font-size: 13px; color: #666;"></div>
        
        <!-- ============================================ -->
        <!-- РЕЗУЛЬТАТ (появляется после завершения) -->
        <!-- ============================================ -->
        <div id="processResult" style="display:none; margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; text-align: left; font-size: 13px; max-height: 150px; overflow-y: auto;">
            <div id="processResultText"></div>
        </div>
        <!-- ============================================ -->
    </div>
</div>
<!-- ============================================ -->

<iframe id="explorerIframe" style="display:none;"></iframe>

<style>

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
.ui-dialog .ui-dialog-titlebar-close {
    display: none !important;
}
.ui-dialog .ui-dialog-buttonpane {
    text-align: center !important;
    border-top: none !important;
    padding-top: 5px !important;
}
.ui-dialog .ui-dialog-buttonpane .ui-dialog-buttonset {
    float: none !important;
}
.ui-dialog .ui-dialog-buttonpane .ui-button {
    padding: 8px 25px;
    font-size: 14px;
}
/* ============================================ */
/* Скрываем спиннер когда он не нужен */
/* ============================================ */
.spinner-border.hidden {
    display: none !important;
}
/* ============================================ */


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
.ui-dialog .ui-dialog-titlebar-close {
    display: none !important;
}
.ui-dialog .ui-dialog-buttonpane {
    text-align: center !important;
    border-top: none !important;
    padding-top: 5px !important;
}
.ui-dialog .ui-dialog-buttonpane .ui-dialog-buttonset {
    float: none !important;
}
.ui-dialog .ui-dialog-buttonpane .ui-button {
    padding: 8px 25px;
    font-size: 14px;
}
</style>

<script>
// CSRF token for AJAX requests
var csrf_token = '<?php echo $csrf_token_path; ?>';
console.log('dbsetting script loaded');

// ============================================
// ПЕРЕМЕННЫЕ ДЛЯ ТАЙМЕРА
// ============================================
var timerInterval = null;
var timerSeconds = 0;
var operationStartTime = null;

// ============================================
// ФУНКЦИИ ДЛЯ МОДАЛЬНОГО ОКНА (jQuery UI)
// ============================================

// Показать результат в модальном окне (без закрытия)
function showResultInModal(title, text, isSuccess) {
    // Останавливаем таймер
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    // Время завершения
    var endTime = new Date();
    var endTimeStr = endTime.toTimeString().slice(0, 8);
    var startTimeStr = document.getElementById('processStartTime').textContent;
    
    // ============================================
    // СКРЫВАЕМ СПИННЕР (надежно)
    // ============================================
    var spinner = document.querySelector('.spinner-border');
    if (spinner) {
        spinner.style.display = 'none';
    }
    // Также через jQuery на всякий случай
    $('.spinner-border').hide();
    // ============================================
    
    // Добавляем время завершения к результату
    var fullText = '🕐 Старт: ' + startTimeStr + ' | 🏁 Финиш: ' + endTimeStr + '<br><br>' + text;
    document.getElementById('processResultText').innerHTML = fullText;
    
    // Меняем заголовок
    $('#processModal').dialog('option', 'title', title);
    document.getElementById('processModalTitle').textContent = title;
    document.getElementById('processModalText').textContent = '';
    document.getElementById('processModalStatus').innerHTML = '';
    
    // Показываем результат
    document.getElementById('processResult').style.display = 'block';
    
    // Показываем и настраиваем кнопку ОК
    var btnOk = $('.ui-dialog-buttonpane .ui-button');
    btnOk.show();
    
    if (isSuccess) {
        btnOk.removeClass('ui-state-default')
            .addClass('ui-state-highlight')
            .css('background', '#5cb85c')
            .css('color', '#fff')
            .css('border-color', '#4cae4c')
            .css('font-weight', 'bold');
    } else {
        btnOk.removeClass('ui-state-default ui-state-highlight')
            .css('background', '#d9534f')
            .css('color', '#fff')
            .css('border-color', '#d43f3a')
            .css('font-weight', 'bold');
    }
    
    btnOk.text('ОК');
    btnOk.off('click').on('click', function() {
        $('#processModal').dialog('close');
        location.reload();
    });
}


// Закрыть модальное окно без перезагрузки (для критических ошибок)
function hideProcessModal() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    try {
        $('#processModal').dialog('close');
    } catch(e) {}
}

// ============================================
// БЭКАП
// ============================================

// Функция для запуска бэкапа со спиннером и таймером
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
        if (text.indexOf('✅ Резервная копия успешно создана') !== -1) {
            var fileMatch = text.match(/Файл:\s*([^\n]+)/);
            var sizeMatch = text.match(/Размер:\s*([^\s]+)/);
            var info = '✅ Резервная копия успешно создана!<br><br>';
            if (fileMatch) info += '📁 Файл: ' + fileMatch[1] + '<br>';
            if (sizeMatch) info += '📊 Размер: ' + sizeMatch[1] + '<br>';
            info += '⏱️ Время выполнения: ' + document.getElementById('processTimer').textContent;
            
            showResultInModal('✅ Готово!', info, true);
        } else if (text.indexOf('❌ Ошибка') !== -1) {
            var errorMsg = text.replace(/\n/g, '<br>');
            showResultInModal('❌ Ошибка!', errorMsg, false);
        } else {
            showResultInModal('⚠️ Неизвестный ответ', text.replace(/\n/g, '<br>'), false);
        }
    })
    .catch(err => {
        hideProcessModal();
        alert('❌ Ошибка соединения: ' + err.message);
        location.reload();
    });
}

// ============================================
// ВОССТАНОВЛЕНИЕ
// ============================================

// Функция для запуска восстановления со спиннером и таймером
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
        if (text.indexOf('✅ База данных успешно восстановлена') !== -1) {
            var fileMatch = text.match(/Файл:\s*([^\n]+)/);
            var info = '✅ База данных успешно восстановлена!<br><br>';
            if (fileMatch) info += '📁 Файл: ' + fileMatch[1] + '<br>';
            info += '⏱️ Время выполнения: ' + document.getElementById('processTimer').textContent;
            info += '<br><br>⚠️ ДАЛЕЕ НЕОБХОДИМО ВРУЧНУЮ ЗАМЕНИТЬ ФАЙЛ:<br>';
            info += '1. Остановить Firebird сервис<br>';
            info += '2. Скопировать восстановленный файл в папку программы<br>';
            info += '3. Переименовать<br>';
            info += '4. Запустить Firebird сервис';
            
            showResultInModal('✅ Готово!', info, true);
        } else if (text.indexOf('❌ Ошибка') !== -1) {
            var errorMsg = text.replace(/\n/g, '<br>');
            showResultInModal('❌ Ошибка!', errorMsg, false);
        } else {
            showResultInModal('⚠️ Неизвестный ответ', text.replace(/\n/g, '<br>'), false);
        }
    })
    .catch(err => {
        hideProcessModal();
        alert('❌ Ошибка соединения: ' + err.message);
        location.reload();
    });
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================

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
    return confirm('ВНИМАНИЕ! Будет выполнено восстановление базы данных в новый файл в папку, указанную в настройках.\n\n' +
                   'Процесс может занимать длительное время\n\n' +
                   'Вы уверены, что хотите продолжить?');
}

// Confirm service action
function confirmService(action) {
    var actionText = (action === 'start') ? 'запустить' : 'остановить';
    return confirm('Вы уверены, что хотите ' + actionText + ' сервис Firebird?\n\n' +
                   'Это может повлиять на работу приложения.');
}

// Функция для выбора папки с базами данных
function browseDatabaseFolder() {
    var folderInput = document.createElement('input');
    folderInput.type = 'file';
    folderInput.webkitdirectory = true;
    folderInput.directory = true;
    folderInput.style.display = 'none';
    
    folderInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            var filePath = this.files[0].webkitRelativePath;
            var folderPath = '';
            
            if (this.files[0].path) {
                folderPath = this.files[0].path;
                var lastSeparator = folderPath.lastIndexOf('\\');
                if (lastSeparator > 0) {
                    folderPath = folderPath.substring(0, lastSeparator);
                }
            } else if (this.value) {
                folderPath = this.value;
                var lastSeparator = Math.max(folderPath.lastIndexOf('\\'), folderPath.lastIndexOf('/'));
                if (lastSeparator > 0) {
                    folderPath = folderPath.substring(0, lastSeparator);
                }
            }
            
            if (folderPath) {
                document.getElementById('database_dir').value = folderPath;
                saveBrowsePath(folderPath);
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

// Функция для сохранения папки с базой данных
function saveDatabaseDir() {
    var databaseDir = document.getElementById('database_dir').value;
    if (!databaseDir) {
        alert('Пожалуйста, укажите папку с базой данных.');
        return;
    }
    
    var saveBtn = event.target;
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="glyphicon glyphicon-refresh spinning"></span>';
    saveBtn.disabled = true;
    
    var formData = new FormData();
    formData.append('database_dir', databaseDir);
    formData.append('csrf_token', csrf_token);
    
    fetch('<?php echo URL::site("dbsetting/save_database_dir"); ?>', {
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
            alert('Папка с базой данных сохранена: ' + databaseDir);
            document.getElementById('backup_database_path').value = data.new_full_path || databaseDir;
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

// Функция для сохранения имени файла базы данных
function saveDatabaseFilename() {
    var filename = document.getElementById('database_filename').value;
    if (!filename) {
        alert('Пожалуйста, укажите имя файла базы данных.');
        return;
    }
    
    var saveBtn = event.target;
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="glyphicon glyphicon-refresh spinning"></span>';
    saveBtn.disabled = true;
    
    var formData = new FormData();
    formData.append('database_filename', filename);
    formData.append('csrf_token', csrf_token);
    
    fetch('<?php echo URL::site("dbsetting/save_database_filename"); ?>', {
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
            alert('Имя файла сохранено: ' + filename);
            document.getElementById('backup_database_path').value = data.new_full_path || filename;
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

// Открыть папку с базой данных в проводнике
function openDatabaseFolder() {
    var dir = document.getElementById('database_dir').value.trim();
    if (!dir) {
        alert('Сначала сохраните путь к папке с базой данных!');
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

// ============================================
// АВТОМАТИЧЕСКАЯ ПРОКРУТКА К ВЕРХУ СТРАНИЦЫ
// ============================================
$(document).ready(function() {
    // Ищем любое flash-сообщение (успех, ошибка, предупреждение)
    var $flash = $('.alert.alert-success, .alert.alert-danger, .alert.alert-warning, .alert.alert-info');
    
    if ($flash.length > 0) {
        // Даем странице полностью загрузиться
        setTimeout(function() {
            // Прокручиваем к самому верху страницы
            $('html, body').animate({
                scrollTop: 0
            }, 400);
        }, 150);
    }
});

// Показать модальное окно с таймером и временем старта
function showProcessModal(title, text, status) {
    // Сбрасываем таймер
    timerSeconds = 0;
    operationStartTime = new Date();
    
    // ============================================
    // ПОКАЗЫВАЕМ СПИННЕР
    // ============================================
    var spinner = document.querySelector('.spinner-border');
    if (spinner) {
        spinner.style.display = 'inline-block';
    }
    $('.spinner-border').show();
    // ============================================
    
    // Показываем время старта
    var startTimeStr = operationStartTime.toTimeString().slice(0, 8);
    document.getElementById('processStartTime').textContent = startTimeStr;
    document.getElementById('processTimer').textContent = '00:00';
    
    // Скрываем результат и кнопку ОК
    document.getElementById('processResult').style.display = 'none';
    document.getElementById('processResultText').innerHTML = '';
    
    // Запускаем таймер
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    timerInterval = setInterval(function() {
        timerSeconds++;
        var minutes = Math.floor(timerSeconds / 60);
        var seconds = timerSeconds % 60;
        var timeStr = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        document.getElementById('processTimer').textContent = timeStr;
    }, 1000);
    
    document.getElementById('processModalTitle').textContent = title;
    document.getElementById('processModalText').textContent = text;
    document.getElementById('processModalStatus').innerHTML = status || '';
    
    // Открываем диалог jQuery UI
    $('#processModal').dialog({
        modal: true,
         width: 500,              // ← увеличили с 460 до 500
    height: 'auto',          // ← автоматическая высота
    maxHeight: 600,          // ← максимальная высота 600px
        resizable: false,
        draggable: false,
        closeOnEscape: false,
        dialogClass: 'no-close',
        buttons: [
            {
                text: 'ОК',
                id: 'processModalOkBtn',
                click: function() {
                    $(this).dialog('close');
                    location.reload();
                }
            }
        ],
        open: function() {
            // Скрываем кнопку ОК при открытии
            var btnOk = $('.ui-dialog-buttonpane .ui-button');
            btnOk.hide();
            // Убираем крестик закрытия
            $('.ui-dialog-titlebar-close').hide();
            
            // ============================================
            // Убеждаемся что спиннер виден
            // ============================================
            var spinner = document.querySelector('.spinner-border');
            if (spinner) {
                spinner.style.display = 'inline-block';
            }
            $('.spinner-border').show();
            // ============================================
        }
    });
}
</script>