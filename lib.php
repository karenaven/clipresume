<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Agrega una nueva instancia de la actividad clipresume en la base de datos.
 *
 * @param stdClass $clipresume Objeto que contiene los datos de la actividad.
 * @param mod_clipresume_mod_form $mform Formulario de creación de la actividad.
 * @return int ID de la nueva instancia de clipresume.
 */
function mod_clipresume_add_instance($clipresume, $mform = null) {
    global $DB;

    $clipresume->timecreated = time();
    $clipresume->timemodified = time();

    return $DB->insert_record('clipresume', $clipresume);
}

/**
 * Actualiza una instancia existente de la actividad clipresume.
 *
 * @param stdClass $clipresume Objeto que contiene los datos de la actividad.
 * @param mod_clipresume_mod_form $mform Formulario de actualización de la actividad.
 * @return bool True si la actualización fue exitosa, false en caso contrario.
 */
function mod_clipresume_update_instance($clipresume, $mform = null) {
    global $DB;

    $clipresume->timemodified = time();
    $clipresume->id = $clipresume->instance;

    return $DB->update_record('clipresume', $clipresume);
}

/**
 * Elimina una instancia de la actividad clipresume y su configuración asociada.
 *
 * @param int $id ID de la instancia a eliminar.
 * @return bool True si la eliminación fue exitosa, false en caso contrario.
 */
function mod_clipresume_delete_instance($id) {
    global $DB;

    if (!$clipresume = $DB->get_record('clipresume', array('id' => $id))) {
        return false;
    }

    return $DB->delete_records('clipresume', array('id' => $clipresume->id));
}

/**
 * Define las características de la actividad.
 *
 * @param string $feature Característica a consultar.
 * @return mixed True si se admite la característica, null en caso contrario.
 */
function mod_clipresume_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_INTRO:
            return true; // Indica que esta actividad tiene una introducción.
        case FEATURE_GRADE_HAS_GRADE:
            return false; // Ajustar según si la actividad requiere calificaciones.
        default:
            return null;
    }
}

/**
 * Proporciona información de la instancia para mostrar en el informe del curso.
 *
 * @param cm_info $cm Información del módulo del curso.
 * @return cached_cm_info Objeto de información de caché del módulo del curso.
 */
function mod_clipresume_get_coursemodule_info($cm) {
    global $DB;

    if (!$clipresume = $DB->get_record('clipresume', array('id' => $cm->instance))) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $clipresume->name;

    // Si la introducción está definida, la formatea para mostrarla.
    if (!empty($clipresume->intro)) {
        $info->content = format_module_intro('clipresume', $clipresume, $cm->id, true);
    }

    return $info;
}

/**
 * Devuelve el contenido del nombre de la actividad para usarlo en el informe del curso.
 *
 * @param stdClass $course Curso en el que se encuentran las actividades.
 * @param cm_info $cm Información del módulo del curso.
 * @param stdClass $context Contexto del curso o actividad.
 * @return array Arreglo con los nombres de las columnas.
 */
function mod_clipresume_get_coursemodule_name() {
    return get_string('pluginname', 'clipresume');
}
