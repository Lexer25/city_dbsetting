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
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <?php echo HTML::chars($text); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($db_error) && !empty($db_error)): ?>
        <div class="alert alert-warning alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
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
                    <div class="form-group">
                        <label>Текущий DSN:</label>
                        <div class="well well-sm">
                            <strong><?php echo HTML::chars($current_dsn); ?></strong>
                            <small class="text-muted">(сохранено в config/database.php)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Доступные DSN:</label>
                        <ul class="list-group">
                            <?php foreach ($odbc_dsns as $name => $dsn): ?>
                                <li class="list-group-item <?php echo ($dsn === $current_dsn) ? 'list-group-item-success' : ''; ?>">
                                    <strong><?php echo HTML::chars($name); ?></strong>
                                    <code class="pull-right"><?php echo HTML::chars($dsn); ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <!-- Backup Configuration -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">Конфигурация резервного копирования</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>Путь к папке с базой данных:</label>
                        <div class="well well-sm">
                            <?php echo HTML::chars($database_dir); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Имя файла базы данных:</label>
                        <div class="well well-sm">
                            <?php echo HTML::chars($database_filename); ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Полный путь к базе данных:</label>
                        <div class="well well-sm">
                            <code><?php echo HTML::chars($database_path); ?></code>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Папка для сохранения резервной копии:</label>
                        <div class="well well-sm">
                            <?php echo HTML::chars($backup_dir); ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Пример имени файла резервной копии:</label>
                        <div class="well well-sm" style="font-family: monospace;">
                            <?php
                            $db_filename = pathinfo($database_path, PATHINFO_FILENAME);
                            $timestamp = date('Y-m-d_His');
                            $preview_filename = $db_filename . '_' . $timestamp . '.fbk';
                            echo HTML::chars($preview_filename);
                            ?>
                        </div>
                        <small class="text-muted">Формат: имя_базы_данных_год-месяц-день_время.fbk</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Restore Information -->
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title">Восстановление базы данных</h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <p><strong>Функция восстановления отключена</strong></p>
                        <p>Для восстановления базы данных обратитесь к администратору системы.</p>
                    </div>
                    <p class="help-block small">Сервис Firebird будет остановлен во время восстановления.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <!-- Service Status -->
            <div class="panel panel-info">
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
                            <button class="btn btn-default btn-sm" disabled>
                                <span class="glyphicon glyphicon-play"></span> Запустить (отключено)
                            </button>
                            <button class="btn btn-default btn-sm" disabled>
                                <span class="glyphicon glyphicon-stop"></span> Остановить (отключено)
                            </button>
                        </div>
                    </div>
                    <p class="help-block small" style="margin-top: 10px;">Управление сервисом отключено в режиме только для чтения.</p>
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
                        <div class="col-md-3">
                            <strong>PHP:</strong> <?php echo PHP_VERSION; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Kohana:</strong> <?php echo Kohana::VERSION; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>ОС:</strong> <?php echo PHP_OS; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Модуль:</strong> <?php echo DBSETTING_VERSION; ?>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 15px;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <span class="glyphicon glyphicon-info-sign"></span>
                                <strong>Режим только для чтения</strong> - редактирование конфигурации отключено.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-warning small">
        <strong>Внимание!</strong> Эти операции влияют на базу данных и сервис. Используйте с осторожностью.
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