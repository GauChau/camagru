<?php
declare(strict_types=1);

use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

if (Session::userId() === null) {
    Response::redirect('/index.php');
}

Page::render(
    'profile',
    title: 'Profile — Camagru',
    styles: ['/assets/css/profile.css']
);
