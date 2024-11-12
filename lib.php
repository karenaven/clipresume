<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Devuelve las características que soporta el módulo.
 *
 * @param string $feature Característica solicitada.
 * @return mixed True si la característica es soportada, null si no.
 */
function clipresume_supports($feature)
{
    switch ($feature) {
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Guarda una nueva instancia del módulo.
 *
 * @param stdClass $clipresume Objeto con los datos del módulo.
 * @param clipresume_mod_form $mform Formulario del módulo.
 * @return int ID de la nueva instancia.
 */
function clipresume_add_instance($data, $mform = null) {
    global $DB;
    
    $data->timecreated = time();
    $data->id = $DB->insert_record('clipresume', $data);

    // Guardar configuración adicional en JSON
    save_configuration_json($data);

    return $data->id;
}

function clipresume_update_instance($data, $mform = null) {
    global $DB;
    
    $data->timemodified = time();
    $data->id = $data->instance;
    $data->intro = isset($data->intro) ? $data->intro : '';
    $data->introformat = isset($data->introformat) ? $data->introformat : FORMAT_HTML;
    $result= $DB->update_record('clipresume', $data);

    // Guardar configuración adicional en JSON
    save_configuration_json($data);

    return $result;
}

function save_configuration_json($data) {
    global $CFG;

    $config_path = $CFG->dataroot . '/clipresume_configurations.json';
    $config_data = array(
        'drive_folder_id' => $data->drive_folder_id,
        'zoom_client_id' => $data->zoom_client_id,
        'zoom_client_secret' => $data->zoom_client_secret,
        'zoom_account_id' => $data->zoom_account_id,
        'zoom_user_id' => $data->zoom_user_id
    );

    file_put_contents($config_path, json_encode($config_data, JSON_PRETTY_PRINT));
}


/**
 * Elimina una instancia del módulo.
 *
 * @param int $id ID de la instancia del módulo.
 * @return bool True si la eliminación fue exitosa.
 */
function clipresume_delete_instance($id)
{
    global $DB, $CFG;

    if (!$clipresume = $DB->get_record('clipresume', array('id' => $id))) {
        return false;
    }

    $result = $DB->delete_records('clipresume', array('id' => $id));
    if ($result) {
        // Eliminar las configuraciones del curso del archivo JSON
        $config_path = $CFG->dataroot . '/clipresume_configurations.json';

        if (file_exists($config_path)) {
            $config_data = json_decode(file_get_contents($config_path), true);
            if ($config_data && isset($config_data['courses'])) {
                foreach ($config_data['courses'] as $index => $course) {
                    if ($course['course_id'] == $clipresume->course) {
                        unset($config_data['courses'][$index]);
                        break;
                    }
                }
                // Reindexar el array para eliminar posibles huecos
                $config_data['courses'] = array_values($config_data['courses']);
                // Guardar los cambios en el archivo JSON
                file_put_contents($config_path, json_encode($config_data, JSON_PRETTY_PRINT));
            }
        }
    }
    return $result;
}

/**
 * Devuelve la información del módulo del curso.
 *
 * @param cm_info $cm Información del módulo del curso.
 * @return cached_cm_info Información en caché del módulo del curso.
 */
// function mod_clipresume_get_coursemodule_info($cm)
// {
//     global $DB;

//     $info = new cached_cm_info();
//     $clipresume = $DB->get_record('clipresume', array('id' => $cm->instance), '*', MUST_EXIST);

//     $info->name = $clipresume->name;

//     if (!empty($clipresume->intro)) {
//         $info->content = format_module_intro('clipresume', $clipresume, $cm->id, true);
//     }

//     return $info;
// }

/**
 * Maneja la entrega de archivos del módulo.
 *
 * @param stdClass $course Objeto del curso.
 * @param stdClass $cm Objeto del módulo del curso.
 * @param stdClass $context Contexto del módulo.
 * @param string $filearea Área de archivos.
 * @param array $args Argumentos adicionales.
 * @param bool $forcedownload Si se debe forzar la descarga.
 * @param array $options Opciones adicionales.
 * @return bool Devuelve falso si no hay archivos que servir.
 */
// function mod_clipresume_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = array())
// {
//     if ($context->contextlevel != CONTEXT_MODULE) {
//         return false;
//     }

//     // Controla las áreas de archivos válidas
//     if ($filearea !== 'content') {
//         return false;
//     }

//     $itemid = array_shift($args);
//     $filename = array_pop($args);
//     $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

//     $fs = get_file_storage();
//     $file = $fs->get_file($context->id, 'clipresume', $filearea, $itemid, $filepath, $filename);

//     if (!$file || $file->is_directory()) {
//         return false;
//     }

//     send_stored_file($file, null, 0, $forcedownload, $options);
// }
