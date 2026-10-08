<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\WorksController;
use App\Controllers\WorkDetailController;
use App\Controllers\OperationsController;

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
    case '/work':
        (new WorkDetailController())->show();
        break;
    case '/work/document/upload':
        (new WorkDetailController())->uploadDocument();
        break;
    case '/work/document/review':
        (new WorkDetailController())->reviewDocument();
        break;
    case '/work/transition':
        (new WorkDetailController())->transition();
        break;
    case '/work/inspection/create':
        (new OperationsController())->createInspection();
        break;
    case '/work/non-conformity/create':
        (new OperationsController())->createNonConformity();
        break;
    case '/work/non-conformity/close':
        (new OperationsController())->closeNonConformity();
        break;
    case '/work/notification/create':
        (new OperationsController())->createNotification();
        break;
    case '/':
        (new DashboardController())->index();
        break;
    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
