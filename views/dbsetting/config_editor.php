<div class="container">
    <h2>Редактирование конфигурации <small>Модуль настройки базы данных</small></h2>
    
    <?php if (Session::instance()->get('flash_message')): ?>
        <?php
        $flash = Session::instance()->get('flash_message');
        $type = Arr::get($flash, 'type', 'info');
        $text = Arr::get($flash, 'text', '');
        $alert_class = 'alert-' . ($type === 'error' ? 'danger' : $type);
        ?>
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <?php echo HTML::chars($text); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">Редактирование файла конфигурации</h3>
                </div>
                <div class="panel-body">
                    <div class="file-path">
                        <strong>Файл:</strong> <?php echo HTML::chars($config_path); ?>
                    </div>
                    
                    <form action="<?php echo URL::site('dbsetting/save_config'); ?>" method="post" id="config-form">
                        <?php
                        // Generate a simple CSRF token
                        $csrf_token = md5(session_id() . 'dbsetting_config_edit');
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label for="config_content">Содержимое конфигурации (PHP массив):</label>
                            <textarea name="config_content" id="config_content" class="form-control" rows="25" style="width: 100%; font-family: monospace; font-size: 12px;"><?php echo HTML::chars($config_content); ?></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Внимание!</strong> Редактирование этого файла напрямую может нарушить работу модуля.
                            Убедитесь, что вы понимаете синтаксис PHP и структуру конфигурации.
                            Перед сохранением будет автоматически создана резервная копия.
                        </div>
                        
                        <div class="btn-container">
                            <a href="<?php echo URL::site('dbsetting'); ?>" class="btn btn-default">Отмена</a>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add syntax highlighting helper
    $('#config_content').on('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            var start = this.selectionStart;
            var end = this.selectionEnd;
            
            // Insert tab character
            this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
            
            // Move cursor position
            this.selectionStart = this.selectionEnd = start + 4;
        }
    });
    
    // Confirm before leaving if changes were made
    var initialContent = $('#config_content').val();
    $('#config-form').on('submit', function() {
        return confirm('Вы уверены, что хотите сохранить изменения в файле конфигурации?');
    });
    
    $(window).on('beforeunload', function() {
        if ($('#config_content').val() !== initialContent) {
            return 'У вас есть несохраненные изменения. Вы уверены, что хотите уйти?';
        }
    });
});
</script>