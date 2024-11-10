<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Crear una página de configuración para el plugin clipresume.
    $settings = new admin_settingpage('mod_clipresume', get_string('pluginname', 'mod_clipresume'));

    if ($ADMIN->fulltree) {
        // Campo para subir el archivo JSON de credenciales.
        $settings->add(new admin_setting_configstoredfile(
            'mod_clipresume/credentials_path',
            get_string('credentials_path', 'clipresume'),
            get_string('credentials_path_desc', 'clipresume'),
            'credentials_path' // Nombre del área de archivo en Moodle.
        ));

        // Google Drive Folder ID
        $settings->add(new admin_setting_configtext(
            'mod_clipresume/drive_folder_id',
            get_string('drive_folder_id', 'mod_clipresume'),
            get_string('drive_folder_id_desc', 'mod_clipresume'),
            '',
            PARAM_TEXT
        ));

        // Zoom Client ID
        $settings->add(new admin_setting_configtext(
            'mod_clipresume/zoom_client_id',
            get_string('zoom_client_id', 'mod_clipresume'),
            get_string('zoom_client_id_desc', 'mod_clipresume'),
            '',
            PARAM_TEXT
        ));

        // Zoom Client Secret
        $settings->add(new admin_setting_configpasswordunmask(
            'mod_clipresume/zoom_client_secret',
            get_string('zoom_client_secret', 'mod_clipresume'),
            get_string('zoom_client_secret_desc', 'mod_clipresume'),
            ''
        ));

        // Zoom Account ID
        $settings->add(new admin_setting_configtext(
            'mod_clipresume/zoom_account_id',
            get_string('zoom_account_id', 'mod_clipresume'),
            get_string('zoom_account_id_desc', 'mod_clipresume'),
            '',
            PARAM_TEXT
        ));

        // User ID
        $settings->add(new admin_setting_configtext(
            'mod_clipresume/zoom_user_id',
            get_string('zoom_user_id', 'mod_clipresume'),
            get_string('zoom_user_id_desc', 'mod_clipresume'),
            '',
            PARAM_TEXT
        ));
    }

    // Agregar la página de configuración a las opciones de administración.
    $ADMIN->add('modsettings', $settings);
}
