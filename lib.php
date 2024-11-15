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
    return $data->id;
}

function clipresume_update_instance($data, $mform = null) {
    global $DB;
    
    $data->timemodified = time();
    $data->id = $data->instance;
    $data->intro = isset($data->intro) ? $data->intro : '';
    $data->introformat = isset($data->introformat) ? $data->introformat : FORMAT_HTML;
    $result= $DB->update_record('clipresume', $data);
    return $result;
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