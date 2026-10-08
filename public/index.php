<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\WorksController;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

switch ($path) {
    case '/login':
        (new AuthController())->login();
        break;
    case '/logout':
        (new AuthController())->logout();
        break;
    case '/works':
        (new WorksController())->index();
        break;
    case '/works/create':
        (new WorksController())->create();
        break;
    case '/':
        (new DashboardController())->index();
        break;
    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
