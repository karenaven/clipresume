<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_clipresume_mod_form extends moodleform_mod {

    /**
     * Define el formulario de configuración para la actividad clipresume.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Campo para el nombre de la actividad.
        $mform->addElement('text', 'name', get_string('name', 'clipresume'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Campo para la descripción de la actividad.
        $this->standard_intro_elements();

        // Añadir el encabezado "Clip Resume"
        $mform->addElement('header', 'modulename', get_string('modulename', 'clipresume'));

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

        // Elementos estándar de configuración de Moodle.
        $this->standard_coursemodule_elements();

        // Botones de guardar y cancelar.
        $this->add_action_buttons();
    }
}
