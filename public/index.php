<?php
declare(strict_types=1);

use Camagru\Http\Session;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

Page::render(
    'home',
    title: 'Camagru',
    styles: ['/assets/css/home.css'],
    scripts: ['/assets/js/main.js']
);
