<?php
//defined('MOODLE_INTERNAL') || die();

$tasks = array(
    // Definición de la tarea cron
    array(
        'classname' => 'mod_clipresume\task\process_video_task',     
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),
);