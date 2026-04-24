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
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <!-- ODBC Configuration -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">ODBC База данных</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Текущий DSN</h4>
                            <div class="well">
                                <code><?php echo HTML::chars($current_dsn); ?></code>
                                <div class="text-muted small">config/database.php</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4>Доступные DSN</h4>
                            <div class="well" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Имя</th>
                                            <th>DSN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($odbc_dsns as $name => $dsn): ?>
                                            <tr class="<?php echo ($dsn === $current_dsn) ? 'success' : ''; ?>">
                                                <td><strong><?php echo HTML::chars($name); ?></strong></td>
                                                <td><code><?php echo HTML::chars($dsn); ?></code></td>
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
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <!-- Database Configuration -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Конфигурация базы данных</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <tr>
                            <th width="40%">Путь к папке базы данных:</th>
                            <td><code><?php echo HTML::chars($database_dir); ?></code></td>
                        </tr>
                        <tr>
                            <th>Имя файла базы данных:</th>
                            <td><code><?php echo HTML::chars($database_filename); ?></code></td>
                        </tr>
                        <tr>
                            <th>Полный путь к базе данных:</th>
                            <td><code><?php echo HTML::chars($database_path); ?></code></td>
                        </tr>
                        <tr>
                            <th>Папка для резервных копий:</th>
                            <td><code><?php echo HTML::chars($backup_dir); ?></code></td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info">
                        <h5>Пример имени файла резервной копии:</h5>
                        <div class="well well-sm" style="font-family: monospace; margin-bottom: 0;">
                            <?php
                            $db_filename = pathinfo($database_path, PATHINFO_FILENAME);
                            $timestamp = date('Y-m-d_His');
                            $preview_filename = $db_filename . '_' . $timestamp . '.fbk';
                            echo HTML::chars($preview_filename);
                            ?>
                        </div>
                        <div class="text-muted small">Формат: имя_базы_данных_год-месяц-день_время.fbk</div>
                    </div>
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
                    $status_text = $status;
                    if ($status === 'running') $status_text = 'Запущен';
                    elseif ($status === 'stopped') $status_text = 'Остановлен';
                    elseif ($status === 'unknown') $status_text = 'Неизвестен';
                    ?>
                    <div class="text-center">
                        <h2>
                            <span class="label <?php echo $label_class; ?>" style="font-size: 24px; padding: 15px 30px;">
                                <?php echo HTML::chars($status_text); ?>
                            </span>
                        </h2>
                        <p class="text-muted">Текущий статус сервиса Firebird</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h5>Восстановление базы данных</h5>
                        <p>Функция восстановления отключена. Для восстановления базы данных обратитесь к администратору системы.</p>
                    </div>
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
                        <h4><i class="glyphicon glyphicon-eye-open"></i> Режим только для чтения</h4>
                        <p>Все настройки отображаются в режиме просмотра. Редактирование конфигурации отключено.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <strong><i class="glyphicon glyphicon-exclamation-sign"></i> Внимание!</strong> Эти настройки влияют на базу данных и сервис. Изменения должны выполняться только администратором системы.
    </div>
</div>