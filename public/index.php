<?php
session_start();
header('Cache-Control: no-store');

require dirname(__DIR__) . '/templates/home.php';
