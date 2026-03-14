<?php
/**
 * ZACO Assets - Application Routes
 * 
 * @author    Mahmoud Fouad <mahmoud.a.fouad2@gmail.com>
 * @copyright Copyright (c) 2024-<?= date('Y') ?> Mahmoud Fouad
 * @license   Proprietary - All Rights Reserved
 */
declare(strict_types=1);

use Zaco\Controllers\AuthController;
use Zaco\Controllers\AssetsController;
use Zaco\Controllers\CustodyController;
use Zaco\Controllers\CleaningController;
use Zaco\Controllers\DashboardController;
use Zaco\Controllers\EmployeesController;
use Zaco\Controllers\NotificationController;
use Zaco\Controllers\PlaceholderController;
use Zaco\Controllers\SettingsController;
use Zaco\Controllers\SoftwareController;
use Zaco\Controllers\UsersController;
use Zaco\Core\Http;

/** @var Zaco\Core\Router $router */
/** @var Zaco\Security\Auth $auth */

$authController = new AuthController($auth);
$dashboardController = new DashboardController($auth);
$usersController = new UsersController($auth);
$settingsController = new SettingsController($auth);
$cleaningController = new CleaningController($auth);
$assetsController = new AssetsController($auth);
$employeesController = new EmployeesController($auth);
$custodyController = new CustodyController($auth);
$softwareController = new SoftwareController($auth);

$router->get('/', [$dashboardController, 'index'], middleware: ['auth']);

$router->get('/login', [$authController, 'loginForm']);
$router->post('/login', [$authController, 'loginSubmit']);
$router->post('/logout', [$authController, 'logout'], middleware: ['auth']);

// Branding (public)
$router->get('/branding/logo', [$settingsController, 'logo']);

// Language
$router->get('/lang', function (): void {
	$set = (string)($_GET['set'] ?? '');
	if (!in_array($set, ['ar', 'en'], true)) {
		$set = 'ar';
	}

	$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (mb_strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
	setcookie('lang', $set, [
		'expires' => time() + 60 * 60 * 24 * 365,
		'path' => '/',
		'secure' => $cookieSecure,
		'httponly' => false,
		'samesite' => 'Lax',
	]);
	$_SESSION['lang'] = $set;

	$r = (string)($_GET['r'] ?? '/');
	if ($r === '' || $r[0] !== '/') {
		$r = '/';
	}
	Http::redirect($r);
});

$router->get('/setup', [$authController, 'setupForm']);
$router->post('/setup', [$authController, 'setupSubmit']);

$router->get('/users', [$usersController, 'index'], middleware: ['auth', 'perm:manage_users']);
$router->get('/users/create', [$usersController, 'createForm'], middleware: ['auth', 'perm:manage_users']);
$router->post('/users/create', [$usersController, 'createSubmit'], middleware: ['auth', 'perm:manage_users']);
$router->get('/users/edit', [$usersController, 'editForm'], middleware: ['auth', 'perm:manage_users']);
$router->post('/users/edit', [$usersController, 'editSubmit'], middleware: ['auth', 'perm:manage_users']);
$router->post('/users/toggle', [$usersController, 'toggleActive'], middleware: ['auth', 'perm:manage_users']);
$router->post('/users/delete', [$usersController, 'deleteSubmit'], middleware: ['auth', 'perm:manage_users']);
$router->post('/users/undo', [$usersController, 'undoDeleteSubmit'], middleware: ['auth', 'perm:manage_users']);

// Notifications (AJAX API)
$notificationController = new NotificationController();
$router->get('/notifications/unread', [$notificationController, 'unread'], middleware: ['auth']);
$router->post('/notifications/mark-read', [$notificationController, 'markRead'], middleware: ['auth']);
$router->post('/notifications/mark-all-read', [$notificationController, 'markAllRead'], middleware: ['auth']);
$router->get('/notifications/history', [$notificationController, 'history'], middleware: ['auth']);

// Assets (use /inventory to avoid collision with the public /assets folder)
$router->get('/inventory', [$assetsController, 'index'], middleware: ['auth', 'perm:view_assets']);
$router->get('/inventory/show', [$assetsController, 'show'], middleware: ['auth', 'perm:view_assets']);
$router->get('/inventory/create', [$assetsController, 'createForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/inventory/create', [$assetsController, 'createSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/inventory/edit', [$assetsController, 'editForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/inventory/edit', [$assetsController, 'editSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/inventory/delete', [$assetsController, 'deleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->post('/inventory/undo', [$assetsController, 'undoDeleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->post('/inventory/move-bulk', [$assetsController, 'moveBulkSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/inventory/export', [$assetsController, 'exportExcel'], middleware: ['auth', 'perm:view_assets']);
$router->get('/inventory/image', [$assetsController, 'image'], middleware: ['auth', 'perm:view_assets']);

// Employees
$router->get('/employees', [$employeesController, 'index'], middleware: ['auth', 'perm:view_employees']);
$router->get('/employees/show', [$employeesController, 'show'], middleware: ['auth', 'perm:view_employees']);
$router->get('/employees/photo', [$employeesController, 'photo'], middleware: ['auth', 'perm:view_employees']);
$router->get('/employees/import', [$employeesController, 'importForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/import', [$employeesController, 'importSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/note', [$employeesController, 'noteSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/report', [$employeesController, 'reportSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/award', [$employeesController, 'awardSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/employees/award/print', [$employeesController, 'awardPrint'], middleware: ['auth', 'perm:view_employees']);
$router->get('/employees/create', [$employeesController, 'createForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/create', [$employeesController, 'createSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/employees/edit', [$employeesController, 'editForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/edit', [$employeesController, 'editSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/employees/delete', [$employeesController, 'deleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->post('/employees/undo', [$employeesController, 'undoDeleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->get('/employees/export', [$employeesController, 'exportExcel'], middleware: ['auth', 'perm:view_employees']);

// Custody
$router->get('/custody', [$custodyController, 'index'], middleware: ['auth', 'perm:view_custody']);
$router->get('/custody/create', [$custodyController, 'createForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/custody/create', [$custodyController, 'createSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/custody/edit', [$custodyController, 'editForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/custody/edit', [$custodyController, 'editSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/custody/delete', [$custodyController, 'deleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->post('/custody/undo', [$custodyController, 'undoDeleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->get('/custody/attachment', [$custodyController, 'attachment'], middleware: ['auth', 'perm:view_custody']);
$router->get('/custody/export', [$custodyController, 'exportExcel'], middleware: ['auth', 'perm:view_custody']);

// Software
$router->get('/software', [$softwareController, 'index'], middleware: ['auth', 'perm:view_software']);
$router->get('/software/create', [$softwareController, 'createForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/software/create', [$softwareController, 'createSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->get('/software/edit', [$softwareController, 'editForm'], middleware: ['auth', 'perm:edit_data']);
$router->post('/software/edit', [$softwareController, 'editSubmit'], middleware: ['auth', 'perm:edit_data']);
$router->post('/software/delete', [$softwareController, 'deleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->post('/software/undo', [$softwareController, 'undoDeleteSubmit'], middleware: ['auth', 'perm:delete_data']);
$router->get('/software/download', [$softwareController, 'download'], middleware: ['auth', 'perm:view_software']);
$router->get('/software/export', [$softwareController, 'exportExcel'], middleware: ['auth', 'perm:view_software']);
$router->get('/cleaning', [$cleaningController, 'today'], middleware: ['auth', 'perm:cleaning']);
$router->post('/cleaning/check', [$cleaningController, 'checkSubmit'], middleware: ['auth', 'perm:cleaning']);
$router->post('/cleaning/report', [$cleaningController, 'reportSubmit'], middleware: ['auth', 'perm:cleaning']);
$router->get('/cleaning/photo', [$cleaningController, 'photo'], middleware: ['auth', 'perm:cleaning']);

// Admin-only cleaning daily reports
$router->get('/cleaning/reports', [$cleaningController, 'reports'], middleware: ['auth', 'perm:manage_cleaning_places']);
$router->get('/cleaning/reports/print', [$cleaningController, 'reportPrint'], middleware: ['auth', 'perm:manage_cleaning_places']);

$router->get('/cleaning/places', [$cleaningController, 'places'], middleware: ['auth', 'perm:manage_cleaning_places']);
$router->post('/cleaning/places/save', [$cleaningController, 'placesSave'], middleware: ['auth', 'perm:manage_cleaning_places']);
$router->get('/settings', [$settingsController, 'index'], middleware: ['auth', 'perm:settings']);
$router->get('/settings/tools', [$settingsController, 'tools'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/tools/logs/clear', [$settingsController, 'clearLog'], middleware: ['auth', 'perm:settings']);
$router->get('/settings/tools/logs/download', [$settingsController, 'downloadLog'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/save', [$settingsController, 'save'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/orgs/create', [$settingsController, 'orgCreate'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/orgs/toggle', [$settingsController, 'orgToggle'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-categories/create', [$settingsController, 'assetCategoryCreate'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-categories/delete', [$settingsController, 'assetCategoryDelete'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-sections/create', [$settingsController, 'assetSectionCreate'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-sections/delete', [$settingsController, 'assetSectionDelete'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-subsections/create', [$settingsController, 'assetSubsectionCreate'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/asset-subsections/delete', [$settingsController, 'assetSubsectionDelete'], middleware: ['auth', 'perm:settings']);
$router->post('/settings/password', [$settingsController, 'changePassword'], middleware: ['auth']);
$router->post('/settings/maintenance/run', [$settingsController, 'runMaintenance'], middleware: ['auth', 'perm:settings']);
$router->get('/settings/maintenance/stats', [$settingsController, 'dbStats'], middleware: ['auth', 'perm:settings']);
$router->get('/settings/audit', [$settingsController, 'audit'], middleware: ['auth', 'perm:settings']);

$router->get('/forbidden', [$authController, 'forbidden']);
