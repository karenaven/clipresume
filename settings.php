<?php

// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin administration pages are defined here.
 *
 * @package     mod_clipresume
 * @category    admin
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if ($hassiteconfig) {
    $settings = new admin_settingpage('mod_clipresume_settings', new lang_string('pluginname', 'mod_clipresume'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
    }
}


// if ($hassiteconfig) {
//     // Crear una página de configuración para el plugin clipresume.
//     $settings = new admin_settingpage('mod_clipresume', get_string('pluginname', 'mod_clipresume'));

//     if ($ADMIN->fulltree) {
//         // Campo para subir el archivo JSON de credenciales.
//         $settings->add(new admin_setting_configstoredfile(
//             'mod_clipresume/credentials_path',
//             get_string('credentials_path', 'clipresume'),
//             get_string('credentials_path_desc', 'clipresume'),
//             'credentials_path' // Nombre del área de archivo en Moodle.
//         ));

//         // Google Drive Folder ID
//         $settings->add(new admin_setting_configtext(
//             'mod_clipresume/drive_folder_id',
//             get_string('drive_folder_id', 'mod_clipresume'),
//             get_string('drive_folder_id_desc', 'mod_clipresume'),
//             '',
//             PARAM_TEXT
//         ));

//         // Zoom Client ID
//         $settings->add(new admin_setting_configtext(
//             'mod_clipresume/zoom_client_id',
//             get_string('zoom_client_id', 'mod_clipresume'),
//             get_string('zoom_client_id_desc', 'mod_clipresume'),
//             '',
//             PARAM_TEXT
//         ));

//         // Zoom Client Secret
//         $settings->add(new admin_setting_configpasswordunmask(
//             'mod_clipresume/zoom_client_secret',
//             get_string('zoom_client_secret', 'mod_clipresume'),
//             get_string('zoom_client_secret_desc', 'mod_clipresume'),
//             ''
//         ));

//         // Zoom Account ID
//         $settings->add(new admin_setting_configtext(
//             'mod_clipresume/zoom_account_id',
//             get_string('zoom_account_id', 'mod_clipresume'),
//             get_string('zoom_account_id_desc', 'mod_clipresume'),
//             '',
//             PARAM_TEXT
//         ));

//         // User ID
//         $settings->add(new admin_setting_configtext(
//             'mod_clipresume/zoom_user_id',
//             get_string('zoom_user_id', 'mod_clipresume'),
//             get_string('zoom_user_id_desc', 'mod_clipresume'),
//             '',
//             PARAM_TEXT
//         ));
//     }

//     // Agregar la página de configuración a las opciones de administración.
//     $ADMIN->add('modsettings', $settings);
// }
