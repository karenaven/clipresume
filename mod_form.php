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
 * Activity creation/editing form for the mod_[modname] plugin.
 *
 * @package   mod_[modname]
 * @copyright Year, You Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/clipresume/lib.php');

class mod_clipresume_mod_form extends moodleform_mod
{

    function definition()
    {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        // Section header title according to language file.
        $mform->addElement('header', 'general', get_string('general', 'clipresume'));

        // Add a text input for the name of the clipresume.
        $mform->addElement('text', 'name', get_string('name', 'clipresume'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Añadir el encabezado "Clip Resume"
        $mform->addElement('header', 'clipresumeheader', get_string('modulename', 'clipresume'));

        // Campos de selección para la programación del cron
        $minutes = array_merge(array('*' => get_string('all', 'clipresume')), array_combine(range(0, 59), range(0, 59)));
        $mform->addElement('select', 'minute', get_string('minute', 'clipresume'), $minutes);
        $mform->setDefault('minute', '*');

        $hours = array_merge(array('*' => get_string('all', 'clipresume')), array_combine(range(0, 23), range(0, 23)));
        $mform->addElement('select', 'hour', get_string('hour', 'clipresume'), $hours);
        $mform->setDefault('hour', '*');

        $day_of_month = array('*' => get_string('all', 'clipresume'));
        for ($i = 1; $i <= 31; $i++) {
            $day_of_month[$i] = $i;
        }
        $mform->addElement('select', 'day', get_string('day', 'clipresume'), $day_of_month);
        $mform->setDefault('day', '*');

        $months = array(
            '*' => get_string('all', 'clipresume'),
            '1' => get_string('january', 'clipresume'),
            '2' => get_string('february', 'clipresume'),
            '3' => get_string('march', 'clipresume'),
            '4' => get_string('april', 'clipresume'),
            '5' => get_string('may', 'clipresume'),
            '6' => get_string('june', 'clipresume'),
            '7' => get_string('july', 'clipresume'),
            '8' => get_string('august', 'clipresume'),
            '9' => get_string('september', 'clipresume'),
            '10' => get_string('october', 'clipresume'),
            '11' => get_string('november', 'clipresume'),
            '12' => get_string('december', 'clipresume')
        );
        $mform->addElement('select', 'month', get_string('month', 'clipresume'), $months);
        $mform->setDefault('month', '*');

        $day_of_week = array(
            '*' => get_string('all', 'clipresume'),
            '0' => get_string('sunday', 'clipresume'),
            '1' => get_string('monday', 'clipresume'),
            '2' => get_string('tuesday', 'clipresume'),
            '3' => get_string('wednesday', 'clipresume'),
            '4' => get_string('thursday', 'clipresume'),
            '5' => get_string('friday', 'clipresume'),
            '6' => get_string('saturday', 'clipresume')
        );
        $mform->addElement('select', 'dayofweek', get_string('dayofweek', 'clipresume'), $day_of_week);
        $mform->setDefault('dayofweek', '*');

        // Checkbox para habilitar fechas entre
        $mform->addElement('advcheckbox', 'enable_dates', get_string('enable_dates', 'clipresume'), get_string('enable_dates_desc', 'clipresume'));
        $mform->setDefault('enable_dates', 0);

        // Campos de fecha
        $mform->addElement('date_selector', 'start_date', get_string('start_date', 'clipresume'));
        $mform->addElement('date_selector', 'end_date', get_string('end_date', 'clipresume'));

        // File manager para credenciales
        $options = array(
            'accepted_types' => array('.json'),
            'maxfiles' => 1,
            'subdirs' => 0
        );
        $mform->addElement('filemanager', 'credentials', get_string('credentials', 'clipresume'), null, $options);
        $mform->addHelpButton('credentials', 'credentials_help', 'clipresume');

        // Campos adicionales de configuración
        $mform->addElement('text', 'drive_folder_id', get_string('drive_folder_id', 'clipresume'));
        $mform->setType('drive_folder_id', PARAM_TEXT);
        $mform->addHelpButton('drive_folder_id', 'drive_folder_id_help', 'clipresume');

        $mform->addElement('text', 'zoom_client_id', get_string('zoom_client_id', 'clipresume'));
        $mform->setType('zoom_client_id', PARAM_TEXT);
        $mform->addHelpButton('zoom_client_id', 'zoom_client_id_help', 'clipresume');

        $mform->addElement('passwordunmask', 'zoom_client_secret', get_string('zoom_client_secret', 'clipresume'));
        $mform->setType('zoom_client_secret', PARAM_TEXT);
        $mform->addHelpButton('zoom_client_secret', 'zoom_client_secret_help', 'clipresume');

        $mform->addElement('text', 'zoom_account_id', get_string('zoom_account_id', 'clipresume'));
        $mform->setType('zoom_account_id', PARAM_TEXT);
        $mform->addHelpButton('zoom_account_id', 'zoom_account_id_help', 'clipresume');

        $mform->addElement('text', 'zoom_user_id', get_string('zoom_user_id', 'clipresume'));
        $mform->setType('zoom_user_id', PARAM_TEXT);
        $mform->addHelpButton('zoom_user_id', 'zoom_user_id_help', 'clipresume');

        // Standard Moodle course module elements (course, category, etc.).
        $this->standard_coursemodule_elements();
        $this->standard_intro_elements();

        // Standard Moodle form buttons.
        $this->add_action_buttons();
    }

    function validation($data, $files)
    {
        $errors = array();

        // Validate the 'name' field.
        if (empty($data['name'])) {
            $errors['name'] = get_string('errornoname', 'clipresume');
        }
        if (empty($data['credentials'])) {
            $errors['credentials'] = get_string('required');
        }
        if (empty($data['drive_folder_id'])) {
            $errors['drive_folder_id'] = get_string('required');
        }
        if (empty($data['zoom_client_id'])) {
            $errors['zoom_client_id'] = get_string('required');
        }
        if (empty($data['zoom_client_secret'])) {
            $errors['zoom_client_secret'] = get_string('required');
        }
        if (empty($data['zoom_account_id'])) {
            $errors['zoom_account_id'] = get_string('required');
        }
        if (empty($data['zoom_user_id'])) {
            $errors['zoom_user_id'] = get_string('required');
        }

        return $errors;
    }

    public function data_preprocessing(&$default_values)
    {
        global $CFG, $DB;
        $context = context_system::instance();
        $fs = get_file_storage();
        // Precargar valores guardados en un archivo JSON de configuración si existe
        $config_path = $CFG->dataroot . '/clipresume_configurations.json';
        if (file_exists($config_path)) {
            $config_data = json_decode(file_get_contents($config_path), true);
            if ($config_data) {
                // Cargar valores generales
                $default_values['drive_folder_id'] = $config_data['drive_folder_id'] ?? '';
                $default_values['zoom_client_id'] = $config_data['zoom_client_id'] ?? '';
                $default_values['zoom_client_secret'] = $config_data['zoom_client_secret'] ?? '';
                $default_values['zoom_account_id'] = $config_data['zoom_account_id'] ?? '';
                $default_values['zoom_user_id'] = $config_data['zoom_user_id'] ?? '';

                // Configuración de ejecución en cron
                $course_id = $this->current->course;
                if (isset($config_data['courses'])) {
                    foreach ($config_data['courses'] as $course) {
                        if ($course['course_id'] == $course_id) {
                            $default_values['minute'] = $course['execution_parameters']['minute'] ?? '*';
                            $default_values['hour'] = $course['execution_parameters']['hour'] ?? '*';
                            $default_values['day'] = $course['execution_parameters']['day'] ?? '*';
                            $default_values['month'] = $course['execution_parameters']['month'] ?? '*';
                            $default_values['dayofweek'] = $course['execution_parameters']['dayofweek'] ?? '*';
                            $default_values['enable_dates'] = $course['execution_parameters']['enable_dates'] ?? 0;
                            $default_values['start_date'] = !empty($course['execution_parameters']['start_date']) ? strtotime($course['execution_parameters']['start_date']) : null;
                            $default_values['end_date'] = !empty($course['execution_parameters']['end_date']) ? strtotime($course['execution_parameters']['end_date']) : null;
                            break;
                        }
                    }
                }
            }
        }

        // Manejo de archivos JSON de credenciales si ya existen en el área de archivos
        $files = $fs->get_area_files($context->id, 'mod_clipresume', 'credentials', 0, 'id', false);
        if ($files) {
            $file = reset($files);
            if ($file) {
                $draftitemid = file_get_submitted_draft_itemid('credentials');
                file_prepare_draft_area($draftitemid, $context->id, 'mod_clipresume', 'credentials', 0, array('subdirs' => 0, 'maxfiles' => 1));
                $default_values['credentials'] = $draftitemid;
            }
        }
    }

    public function data_postprocessing($data)
    {
        global $CFG, $USER, $DB;
        $fs = get_file_storage();
       
        // Ruta del archivo de configuración
        $config_path = $CFG->dataroot . '/clipresume_configurations.json';

        // Cargar datos existentes del archivo de configuración
        $config_data = array();
        if (file_exists($config_path)) {
            $config_data = json_decode(file_get_contents($config_path), true);
        }

        // Actualizar configuraciones generales
        $config_data['drive_folder_id'] = $data->drive_folder_id;
        $config_data['zoom_client_id'] = $data->zoom_client_id;
        $config_data['zoom_client_secret'] = $data->zoom_client_secret;
        $config_data['zoom_account_id'] = $data->zoom_account_id;
        $config_data['zoom_user_id'] = $data->zoom_user_id;

        // Formatear fechas
        $start_date = !empty($data->start_date) ? date('Y-m-d', $data->start_date) : null;
        $end_date = !empty($data->end_date) ? date('Y-m-d', $data->end_date) : null;
        if ($data->enable_dates == 0) {
            $start_date = null;
            $end_date = null;
        }

        // Actualizar o añadir las configuraciones del curso actual
        $course_id = $this->current->course;

        // Obtener información del curso
        $course = $DB->get_record('course', array('id' => $course_id), 'fullname, shortname');

        $course_name = isset($course->fullname) ? $course->fullname : '';
        $course_short_name = isset($course->shortname) ? $course->shortname : '';

        $execution_parameters = array(
            'minute' => $data->minute,
            'hour' => $data->hour,
            'day' => $data->day,
            'month' => $data->month,
            'dayofweek' => $data->dayofweek,
            'enable_dates' => $data->enable_dates,
            'start_date' => $start_date,
            'end_date' => $end_date
        );

        // Inicializar el array de cursos si no existe
        if (!isset($config_data['courses'])) {
            $config_data['courses'] = array();
        }

        // Buscar el índice del curso en el array
        $course_index = null;
        if (isset($config_data['courses'])) {
            foreach ($config_data['courses'] as $index => $course) {
                if ($course['course_id'] == $course_id) {
                    $course_index = $index;
                    break;
                }
            }
        }
        // Si el curso ya existe, actualizarlo, si no, añadirlo
        if ($course_index !== null) {
            $config_data['courses'][$course_index]['execution_parameters'] = $execution_parameters;
            $config_data['courses'][$course_index]['course_name'] = html_entity_decode($course_name);
            $config_data['courses'][$course_index]['course_short_name'] = html_entity_decode($course_short_name);
        } else {
            $config_data['courses'][] = array(
                'course_id' => $course_id,
                'course_name' => html_entity_decode($course_name),
                'course_short_name' => html_entity_decode($course_short_name),
                'execution_parameters' => $execution_parameters
            );
        }
        file_put_contents($config_path, json_encode($config_data, JSON_PRETTY_PRINT));
       
        // Guardar archivo de credenciales en credentials.json
        $draftitemid = $data->credentials;
        if ($draftitemid) {
            $context = context_system::instance();
            file_save_draft_area_files($draftitemid, $context->id, 'mod_clipresume', 'credentials', 0, array('subdirs' => 0, 'maxfiles' => 1));

            $storedfiles = $fs->get_area_files($context->id, 'mod_clipresume', 'credentials', 0, 'id', false);
            if ($storedfiles) {
                $storedfile = reset($storedfiles);
                $credentials_path = $CFG->dataroot . '/clipresume_credentials.json';
                $storedfile->copy_content_to($credentials_path);
            }
        }
    }
}