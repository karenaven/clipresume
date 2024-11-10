<?php
require_once('../../config.php');
require_once('lib.php');

// Parámetros
$id = required_param('id', PARAM_INT); // ID del módulo de curso

// Obtener información del módulo y del curso
$cm = get_coursemodule_from_id('clipresume', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$clipresume = $DB->get_record('clipresume', ['id' => $cm->instance], '*', MUST_EXIST); // Información específica de la actividad

$context = context_module::instance($cm->id); // Contexto de la actividad

// Verificar que el usuario esté logueado y tenga permiso para ver la actividad
require_login($course, true, $cm);
require_capability('mod/clipresume:view', $context);

// Configuración de la página
$PAGE->set_url('/mod/clipresume/view.php', ['id' => $id]);
$PAGE->set_title($course->shortname . ': ' . $clipresume->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

// Iniciar el renderizado de la página
echo $OUTPUT->header();

// Mostrar el nombre de la actividad
echo $OUTPUT->heading(format_string($clipresume->name), 2);

// Mostrar la descripción de la actividad
if (trim($clipresume->intro)) {
    echo $OUTPUT->box(format_module_intro('clipresume', $clipresume, $cm->id), 'generalbox mod_introbox', 'clipresumeintro');
}

// Aquí puedes añadir cualquier lógica o visualización adicional que necesite tu módulo

// Finalizar el renderizado de la página
echo $OUTPUT->footer();
