<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\WorksController;
use App\Controllers\WorkDetailController;
use App\Controllers\OperationsController;
use App\Controllers\MediaController;
use App\Controllers\AccessController;
use App\Controllers\NotificationPdfController;
use App\Controllers\CompletionController;
use App\Controllers\CompletionPdfController;
use App\Controllers\DossierPdfController;
use App\Controllers\ReportsController;
use App\Controllers\SettingsController;
use App\Controllers\CorrectionController;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

switch ($path) {
    case '/login': (new AuthController())->login(); break;
    case '/logout': (new AuthController())->logout(); break;
    case '/works': (new WorksController())->index(); break;
    case '/works/create': (new WorksController())->create(); break;
    case '/work': (new WorkDetailController())->show(); break;
    case '/work/document/upload': (new WorkDetailController())->uploadDocument(); break;
    case '/work/document/review': (new WorkDetailController())->reviewDocument(); break;
    case '/work/transition': (new WorkDetailController())->transition(); break;
    case '/work/inspection/create': (new OperationsController())->createInspection(); break;
    case '/work/non-conformity/create': (new OperationsController())->createNonConformity(); break;
    case '/work/non-conformity/close': (new OperationsController())->closeNonConformity(); break;
    case '/work/notification/create': (new OperationsController())->createNotification(); break;
    case '/inspection/photos': (new MediaController())->inspectionPhotos(); break;
    case '/inspection/photo/upload': (new MediaController())->uploadInspectionPhoto(); break;
    case '/inspection/photo': (new MediaController())->photo(); break;
    case '/work/invite': (new AccessController())->invite(); break;
    case '/invite/accept': (new AccessController())->accept(); break;
    case '/notification/pdf': (new NotificationPdfController())->show(); break;
    case '/work/completion': (new CompletionController())->show(); break;
    case '/work/completion/start': (new CompletionController())->start(); break;
    case '/work/completion/record': (new CompletionController())->record(); break;
    case '/work/completion/term': (new CompletionPdfController())->show(); break;
    case '/work/dossier': (new DossierPdfController())->show(); break;
    case '/reports': (new ReportsController())->index(); break;
    case '/settings': (new SettingsController())->index(); break;
    case '/settings/rules': (new SettingsController())->saveRules(); break;
    case '/settings/template': (new SettingsController())->saveTemplate(); break;
    case '/settings/checklist': (new SettingsController())->addChecklistItem(); break;
    case '/settings/checklist/update': (new SettingsController())->updateChecklistItem(); break;
    case '/settings/document': (new SettingsController())->addDocumentType(); break;
    case '/settings/document/update': (new SettingsController())->updateDocumentType(); break;
    case '/settings/severity': (new SettingsController())->saveSeverityRule(); break;
    case '/work/non-conformity/correction': (new CorrectionController())->submit(); break;
    case '/work/non-conformity/evidence': (new CorrectionController())->evidence(); break;
    case '/': (new DashboardController())->index(); break;
    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
