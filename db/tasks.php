<?php
defined('MOODLE_INTERNAL') || die();

$tasks = array(
    // Definición de la tarea cron
    array(
        'classname' => 'local_clipresume\task\process_video_task',     
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),
);