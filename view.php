<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity view page for the mod_clipresume plugin.
 *
 * @package   mod_clipresume
 * @copyright Year, You Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'clipresume');
$instance = $DB->get_record('clipresume', ['id' => $cm->instance], '*', MUST_EXIST);

// Especifica el contexto de la página y la URL
$context = context_module::instance($cm->id);
$PAGE->set_context($context);
$PAGE->set_url('/mod/clipresume/view.php', ['id' => $id]);

// Verifica que el usuario esté logueado en el curso y con el módulo adecuado
require_login($course, true, $cm);

$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

// Renderiza el contenido de la página
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));
echo $OUTPUT->footer();
