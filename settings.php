<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_clipresume', get_string('pluginname', 'local_clipresume'));

    if ($ADMIN->fulltree) {
        // Añade un campo de texto para cualquier otra configuración (por ejemplo, el ID del proyecto de GCP).
        // $settings->add(new admin_setting_configtext(
        //     'local_clipresume/projectid',
        //     get_string('projectid', 'local_clipresume'),
        //     get_string('projectid_desc', 'local_clipresume'),
        //     '', // Valor predeterminado.
        //     PARAM_TEXT
        // ));
    
        // Añade el campo para subir el archivo JSON de credenciales.
        $settings->add(new admin_setting_configstoredfile(
            'local_clipresume/credentials_path',
            get_string('credentials_path', 'local_clipresume'),
            get_string('credentials_path_desc', 'local_clipresume'),
            'credentials_path' // Nombre del área de archivo en Moodle.
        ));
    }

    // Google Drive Folder ID
    $settings->add(new admin_setting_configtext(
        'local_clipresume/drive_folder_id',
        get_string('drive_folder_id', 'local_clipresume'),
        get_string('drive_folder_id_desc', 'local_clipresume'),
        '',
        PARAM_TEXT
    ));

    // Zoom Client ID
    $settings->add(new admin_setting_configtext(
        'local_clipresume/zoom_client_id',
        get_string('zoom_client_id', 'local_clipresume'),
        get_string('zoom_client_id_desc', 'local_clipresume'),
        '',
        PARAM_TEXT
    ));

    // Zoom Client Secret
    $settings->add(new admin_setting_configpasswordunmask(
        'local_clipresume/zoom_client_secret',
        get_string('zoom_client_secret', 'local_clipresume'),
        get_string('zoom_client_secret_desc', 'local_clipresume'),
        ''
    ));

    // Zoom Account ID
    $settings->add(new admin_setting_configtext(
        'local_clipresume/zoom_account_id',
        get_string('zoom_account_id', 'local_clipresume'),
        get_string('zoom_account_id_desc', 'local_clipresume'),
        '',
        PARAM_TEXT
    ));

    // User ID
    $settings->add(new admin_setting_configtext(
        'local_clipresume/zoom_user_id',
        get_string('zoom_user_id', 'local_clipresume'),
        get_string('zoom_user_id_desc', 'local_clipresume'),
        '',
        PARAM_TEXT
    ));

    // Agregar la página de configuración a las opciones de administración.
    $ADMIN->add('localplugins', $settings);
}
