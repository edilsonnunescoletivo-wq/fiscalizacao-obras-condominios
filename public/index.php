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
use App\Controllers\NotificationController;
use App\Controllers\NotificationPdfController;
use App\Controllers\CompletionController;
use App\Controllers\CompletionPdfController;
use App\Controllers\DossierPdfController;
use App\Controllers\ReportsController;
use App\Controllers\SettingsController;
use App\Controllers\ChecklistPresetController;
use App\Controllers\FiscalPanelController;
use App\Controllers\CorrectionController;
use App\Controllers\InspectionFormController;
use App\Controllers\DocumentController;
use App\Controllers\ResponsibleController;
use App\Controllers\PasswordSetupController;
use App\Controllers\MaintenanceController;
use App\Controllers\CondominiumModuleController;
use App\Controllers\UserAccessController;
use App\Core\Auth;
use App\Core\WorkAccess;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$managementSettingsPaths = [
    '/settings','/settings/rules','/settings/template','/settings/checklist','/settings/checklist/update',
    '/settings/checklist-preset','/settings/checklist/preset','/settings/document','/settings/document/update','/settings/severity',
];
if (in_array($path, $managementSettingsPaths, true)) {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }
    $condoId = (int)($_GET['condo'] ?? $_POST['condo_id'] ?? 0);
    $roles = WorkAccess::rolesForCondo($condoId, (int)(Auth::user()['id'] ?? 0));
    if ($condoId <= 0 || !WorkAccess::canManage($roles)) {
        http_response_code(403);
        exit('Apenas perfis de gestão podem alterar configurações do condomínio.');
    }
}

switch ($path) {
    case '/login': (new AuthController())->login(); break;
    case '/logout': (new AuthController())->logout(); break;
    case '/setup-password': (new PasswordSetupController())->show(); break;
    case '/maintenance/migrations': (new MaintenanceController())->migrations(); break;
    case '/responsible': (new ResponsibleController())->index(); break;
    case '/responsible/work': (new ResponsibleController())->work(); break;
    case '/responsible/document/upload': (new ResponsibleController())->uploadDocument(); break;
    case '/works': (new WorksController())->index(); break;
    case '/works/create': (new WorksController())->create(); break;
    case '/condominium/inspections': (new CondominiumModuleController())->inspections(); break;
    case '/condominium/non-conformities': (new CondominiumModuleController())->nonConformities(); break;
    case '/condominium/notifications': (new CondominiumModuleController())->notifications(); break;
    case '/condominium/documents': (new CondominiumModuleController())->documents(); break;
    case '/condominium/completions': (new CondominiumModuleController())->completions(); break;
    case '/condominium/dossiers': (new CondominiumModuleController())->dossiers(); break;
    case '/users': (new UserAccessController())->index(); break;
    case '/users/access/add': (new UserAccessController())->add(); break;
    case '/users/access/toggle': (new UserAccessController())->toggle(); break;
    case '/work': (new WorkDetailController())->show(); break;
    case '/work/document': (new DocumentController())->show(); break;
    case '/work/document/upload': (new WorkDetailController())->uploadDocument(); break;
    case '/work/document/review': (new WorkDetailController())->reviewDocument(); break;
    case '/work/transition': (new WorkDetailController())->transition(); break;
    case '/work/inspection/create': (new InspectionFormController())->create(); break;
    case '/inspection/new': (new InspectionFormController())->show(); break;
    case '/inspection/create-dynamic': (new InspectionFormController())->create(); break;
    case '/work/non-conformity/create': (new OperationsController())->createNonConformity(); break;
    case '/work/non-conformity/close': (new OperationsController())->closeNonConformity(); break;
    case '/work/notification/create': (new OperationsController())->createNotification(); break;
    case '/notifications': (new NotificationController())->index(); break;
    case '/work/notification/status': (new NotificationController())->updateStatus(); break;
    case '/corrections': (new CorrectionController())->index(); break;
    case '/work/non-conformity/correction': (new CorrectionController())->submit(); break;
    case '/work/non-conformity/correction/review': (new CorrectionController())->review(); break;
    case '/work/non-conformity/evidence': (new CorrectionController())->evidence(); break;
    case '/condominium/photo/upload': (new MediaController())->uploadCondominiumPhoto(); break;
    case '/condominium/photo': (new MediaController())->condominiumPhoto(); break;
    case '/work/photo/upload': (new MediaController())->uploadWorkPhoto(); break;
    case '/work/photo': (new MediaController())->workPhoto(); break;
    case '/inspection/photos': (new MediaController())->inspectionPhotos(); break;
    case '/inspection/photo/upload': (new MediaController())->uploadInspectionPhoto(); break;
    case '/inspection/photo': (new MediaController())->photo(); break;
    case '/work/invite': (new AccessController())->invite(); break;
    case '/invite/accept': (new AccessController())->accept(); break;
    case '/notification/pdf': (new NotificationPdfController())->show(); break;
    case '/work/completion': (new CompletionController())->show(); break;
    case '/work/completion/start': (new CompletionController())->start(); break;
    case '/work/completion/direct': (new CompletionController())->completeDirectly(); break;
    case '/work/completion/record': (new CompletionController())->record(); break;
    case '/work/completion/term': (new CompletionPdfController())->show(); break;
    case '/work/dossier': (new DossierPdfController())->show(); break;
    case '/reports': (new ReportsController())->index(); break;
    case '/fiscal-panel': (new FiscalPanelController())->index(); break;
    case '/settings': (new SettingsController())->index(); break;
    case '/settings/rules': (new SettingsController())->saveRules(); break;
    case '/settings/template': (new SettingsController())->saveTemplate(); break;
    case '/settings/checklist': (new SettingsController())->addChecklistItem(); break;
    case '/settings/checklist/update': (new SettingsController())->updateChecklistItem(); break;
    case '/settings/checklist-preset': (new ChecklistPresetController())->show(); break;
    case '/settings/checklist/preset': (new ChecklistPresetController())->apply(); break;
    case '/settings/document': (new SettingsController())->addDocumentType(); break;
    case '/settings/document/update': (new SettingsController())->updateDocumentType(); break;
    case '/settings/severity': (new SettingsController())->saveSeverityRule(); break;
    case '/': (new DashboardController())->index(); break;
    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
