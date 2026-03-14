<?php
/**
 * ZACO Assets - Assets Controller
 * 
 * @author    Mahmoud Fouad <mahmoud.a.fouad2@gmail.com>
 * @copyright Copyright (c) 2024-<?= date('Y') ?> Mahmoud Fouad
 * @license   Proprietary - All Rights Reserved
 */
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\Notify;
use Zaco\Core\Pdf;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class AssetsController extends BaseController
{
    private const UNDO_TTL_SECONDS = 300;

    public function __construct(private readonly Auth $auth)
    {
    }

    public function index(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $cat = trim((string)($_GET['cat'] ?? ''));
        $cond = trim((string)($_GET['cond'] ?? ''));
        $view = (string)($_GET['view'] ?? 'list');
        if (!in_array($view, ['list', 'cards'], true)) $view = 'list';

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 25;

        $sort = (string)($_GET['sort'] ?? '');
        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $db = $this->db();
        $orgTableExists = $this->hasTable($db, 'organizations');
        $orgColumnExists = $this->hasColumn($db, 'assets', 'org_id');
        $orgEnabled = $orgTableExists && $orgColumnExists;
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $categoriesEnabled = $this->assetCategoriesEnabled($db);
        $sectionsEnabled = $this->assetSectionsEnabled($db);
        $assetSections = $sectionsEnabled ? $this->assetSectionsList() : [];
        $subsectionsBySection = $sectionsEnabled ? $this->assetSubsectionsBySection() : [];

        $sortMap = [
            'id' => 'a.id',
            'name' => 'a.name',
            'category' => 'a.category',
            'code' => 'a.code',
            'qty' => 'a.quantity',
            'cost' => 'a.cost',
            'condition' => 'a.asset_condition',
        ];
        if ($orgEnabled) {
            $sortMap['org'] = 'o.name';
        }
        $orderExpr = $sortMap[$sort] ?? 'a.id';
        $orderBy = $orderExpr . ' ' . $dir . ', a.id DESC';

        $where = 'a.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $where .= ' AND a.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $where .= ' AND (a.name LIKE ? OR a.code LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $where .= ' AND a.category = ?';
            $args[] = $cat;
        }
        if ($cond !== '') {
            $where .= ' AND a.asset_condition = ?';
            $args[] = $cond;
        }

        $countStmt = $db->prepare('SELECT COUNT(*) AS c FROM assets a WHERE ' . $where);
        $countStmt->execute($args);
        $totalCount = (int)($countStmt->fetch()['c'] ?? 0);

        $sumStmt = $db->prepare('SELECT COALESCE(SUM(a.cost * a.quantity), 0) AS v FROM assets a WHERE ' . $where);
        $sumStmt->execute($args);
        $totalValue = (float)($sumStmt->fetch()['v'] ?? 0);

        $qtyStmt = $db->prepare('SELECT COALESCE(SUM(a.quantity), 0) AS q FROM assets a WHERE ' . $where);
        $qtyStmt->execute($args);
        $totalQty = (int)($qtyStmt->fetch()['q'] ?? 0);

        $lowStmt = $db->prepare('SELECT COUNT(*) AS c FROM assets a WHERE ' . $where . ' AND a.quantity < a.min_quantity');
        $lowStmt->execute($args);
        $lowCount = (int)($lowStmt->fetch()['c'] ?? 0);

        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) $page = $totalPages;

        $offset = ($page - 1) * $perPage;
        $offset = max(0, $offset);

        $select = 'SELECT a.*';
        $from = ' FROM assets a';
        $joins = '';
        if ($orgEnabled) {
            $select .= ', o.name AS org_name';
            $joins .= ' LEFT JOIN organizations o ON o.id = a.org_id';
        }
        if ($sectionsEnabled) {
            $select .= ', s.name AS section_name, ss.name AS subsection_name';
            $joins .= ' LEFT JOIN asset_sections s ON s.id = a.section_id';
            $joins .= ' LEFT JOIN asset_subsections ss ON ss.id = a.subsection_id';
        }
        $sql = $select . $from . $joins . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $items = $stmt->fetchAll();

        $cats = [];
        if ($categoriesEnabled) {
            $cats = array_values(array_filter(array_map(static fn($r) => (string)($r['name'] ?? ''), $this->assetCategoriesList())));
        } else {
            $catsSql = 'SELECT DISTINCT a.category FROM assets a WHERE a.deleted_at IS NULL';
            $catsArgs = [];
            if ($orgEnabled && $orgId > 0) {
                $catsSql .= ' AND a.org_id = ?';
                $catsArgs[] = $orgId;
            }
            $catsSql .= ' ORDER BY a.category ASC';
            $catsStmt = $db->prepare($catsSql);
            $catsStmt->execute($catsArgs);
            $cats = array_values(array_filter(array_map(static fn($r) => (string)($r['category'] ?? ''), $catsStmt->fetchAll())));
        }

        $count = $totalCount;
        $value = $totalValue;
        $qty = $totalQty;
        $low = $lowCount;

        $undoId = 0;
        $undo = $_SESSION['undo_assets'] ?? null;
        if (is_array($undo) && isset($undo['id'], $undo['t'])) {
            $age = time() - (int)$undo['t'];
            if ($age >= 0 && $age <= self::UNDO_TTL_SECONDS) {
                $undoId = (int)$undo['id'];
            } else {
                unset($_SESSION['undo_assets']);
            }
        }

        View::render('assets/index', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'items' => $items,
            'q' => $q,
            'org_id' => $orgId,
            'orgEnabled' => $orgEnabled,
            'orgTableExists' => $orgTableExists,
            'orgColumnExists' => $orgColumnExists,
            'orgs' => $orgTableExists ? $this->orgsList() : [],
            'sectionsEnabled' => $sectionsEnabled,
            'assetSections' => $assetSections,
            'subsectionsBySection' => $subsectionsBySection,
            'cat' => $cat,
            'cond' => $cond,
            'view' => $view,
            'cats' => $cats,
            'count' => $count,
            'qty' => $qty,
            'value' => $value,
            'low' => $low,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'sort' => $sort,
            'dir' => $dir,
            'canEdit' => $this->auth->can('edit_data'),
            'canDelete' => $this->auth->can('delete_data'),
            'undoId' => $undoId,
        ]);
    }

    public function show(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) {
            Http::redirect('/inventory');
            return;
        }

        View::render('assets/show', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'canEdit' => $this->auth->can('edit_data'),
        ]);
    }

    public function createForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $orgId = (int)($_GET['org_id'] ?? 0);
        $db = $this->db();
        $orgTableExists = $this->hasTable($db, 'organizations');
        $orgColumnExists = $this->hasColumn($db, 'assets', 'org_id');
        $orgEnabled = $orgTableExists && $orgColumnExists;
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $orgs = $orgEnabled ? $this->orgsList() : [];
        $defaultOrgId = $orgId > 0 ? $orgId : (count($orgs) === 1 ? (int)$orgs[0]['id'] : 0);

        $categoriesEnabled = $this->assetCategoriesEnabled($db);
        $sectionsEnabled = $this->assetSectionsEnabled($db);

        View::render('assets/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $defaultOrgId > 0 ? ['org_id' => $defaultOrgId] : null,
            'orgs' => $orgs,
            'orgEnabled' => $orgEnabled,
            'orgTableExists' => $orgTableExists,
            'orgColumnExists' => $orgColumnExists,
            'categoriesEnabled' => $categoriesEnabled,
            'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
            'sectionsEnabled' => $sectionsEnabled,
            'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
            'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
            'error' => null,
            'mode' => 'create',
        ]);
    }

    public function createSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        $orgTableExists = $this->hasTable($db, 'organizations');
        $orgColumnExists = $this->hasColumn($db, 'assets', 'org_id');
        $orgEnabled = $orgTableExists && $orgColumnExists;

        $categoriesEnabled = $this->assetCategoriesEnabled($db);
        $sectionsEnabled = $this->assetSectionsEnabled($db);

        $data = $this->readAssetPost();

        if ($categoriesEnabled && !empty($data['category_id'])) {
            $name = $this->categoryNameById($db, (int)$data['category_id']);
            if ($name !== null) {
                $data['category'] = $name;
            } else {
                $data['category_id'] = null;
            }
        }

        if ($sectionsEnabled && !empty($data['subsection_id'])) {
            $realSectionId = $this->subsectionSectionId($db, (int)$data['subsection_id']);
            if ($realSectionId === null) {
                View::render('assets/form', [
                    'csrf' => Csrf::token(),
                    'user' => $u,
                    'item' => $data,
                    'orgs' => $orgEnabled ? $this->orgsList() : [],
                    'orgEnabled' => $orgEnabled,
                    'orgTableExists' => $orgTableExists,
                    'orgColumnExists' => $orgColumnExists,
                    'categoriesEnabled' => $categoriesEnabled,
                    'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                    'sectionsEnabled' => $sectionsEnabled,
                    'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                    'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                    'error' => 'القسم الفرعي غير صحيح.',
                    'mode' => 'create',
                ]);
                return;
            }
            if (!empty($data['section_id']) && (int)$data['section_id'] !== $realSectionId) {
                View::render('assets/form', [
                    'csrf' => Csrf::token(),
                    'user' => $u,
                    'item' => $data,
                    'orgs' => $orgEnabled ? $this->orgsList() : [],
                    'orgEnabled' => $orgEnabled,
                    'orgTableExists' => $orgTableExists,
                    'orgColumnExists' => $orgColumnExists,
                    'categoriesEnabled' => $categoriesEnabled,
                    'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                    'sectionsEnabled' => $sectionsEnabled,
                    'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                    'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                    'error' => 'القسم الفرعي لا يتبع هذا القسم.',
                    'mode' => 'create',
                ]);
                return;
            }
            if (empty($data['section_id'])) {
                $data['section_id'] = $realSectionId;
            }
        }

        $err = $this->validateAsset($data, requireOrgOnCreate: $orgEnabled);
        if ($err !== null) {
            View::render('assets/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'orgTableExists' => $orgTableExists,
                'orgColumnExists' => $orgColumnExists,
                'categoriesEnabled' => $categoriesEnabled,
                'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                'sectionsEnabled' => $sectionsEnabled,
                'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                'error' => $err,
                'mode' => 'create',
            ]);
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $imgPath = $this->handleImageUpload('image');

        if (!$orgEnabled) {
            $data['org_id'] = null;
        }

        $cols = [];
        $vals = [];
        if ($orgEnabled) {
            $cols[] = 'org_id';
            $vals[] = $data['org_id'];
        }
        $cols = array_merge($cols, ['name', 'category', 'asset_type', 'image_path', 'code', 'quantity', 'min_quantity', 'purchase_date', 'supplier', 'cost', 'asset_condition', 'location', 'plate_number', 'vehicle_model', 'vehicle_year', 'mileage', 'notes']);
        $vals = array_merge($vals, [
            $data['name'],
            $data['category'],
            $data['asset_type'],
            $imgPath,
            $data['code'],
            $data['quantity'],
            $data['min_quantity'],
            $data['purchase_date'],
            $data['supplier'],
            $data['cost'],
            $data['asset_condition'],
            $data['location'],
            $data['plate_number'],
            $data['vehicle_model'],
            $data['vehicle_year'],
            $data['mileage'],
            $data['notes'],
        ]);
        if ($categoriesEnabled) {
            $cols[] = 'category_id';
            $vals[] = $data['category_id'] ?? null;
        }
        if ($sectionsEnabled) {
            $cols[] = 'section_id';
            $vals[] = $data['section_id'] ?? null;
            $cols[] = 'subsection_id';
            $vals[] = $data['subsection_id'] ?? null;
        }
        $cols[] = 'created_at';
        $vals[] = $now;
        $cols[] = 'updated_at';
        $vals[] = $now;

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $db->prepare('INSERT INTO assets (' . implode(',', $cols) . ') VALUES (' . $ph . ')');

        try {
            $stmt->execute($vals);
        } catch (\Throwable) {
            View::render('assets/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'orgTableExists' => $orgTableExists,
                'orgColumnExists' => $orgColumnExists,
                'categoriesEnabled' => $categoriesEnabled,
                'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                'sectionsEnabled' => $sectionsEnabled,
                'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                'error' => 'تعذر حفظ الأصل (تأكد أن الكود غير مكرر).',
                'mode' => 'create',
            ]);
            return;
        }

        $newId = (int)$db->lastInsertId();
        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'assets', (string)$data['name'] . ' [' . (string)$data['code'] . ']');
        
        // Send notification
        Notify::create(
            null,
            (int)$u['id'],
            (string)$u['name'],
            'create',
            'asset',
            $newId,
            (string)$data['name'],
            'تمت إضافة أصل جديد: ' . (string)$data['name']
        );
        
        Http::redirect('/inventory?msg=created');
    }

    public function editForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) {
            Http::redirect('/inventory');
            return;
        }

        $db = $this->db();
        $orgTableExists = $this->hasTable($db, 'organizations');
        $orgColumnExists = $this->hasColumn($db, 'assets', 'org_id');
        $orgEnabled = $orgTableExists && $orgColumnExists;

        $categoriesEnabled = $this->assetCategoriesEnabled($db);
        $sectionsEnabled = $this->assetSectionsEnabled($db);

        View::render('assets/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'orgEnabled' => $orgEnabled,
            'orgTableExists' => $orgTableExists,
            'orgColumnExists' => $orgColumnExists,
            'categoriesEnabled' => $categoriesEnabled,
            'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
            'sectionsEnabled' => $sectionsEnabled,
            'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
            'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
            'error' => null,
            'mode' => 'edit',
        ]);
    }

    public function editSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $existing = $this->find($id);
        if (!$existing) {
            Http::redirect('/inventory');
            return;
        }

        $db = $this->db();
        $orgTableExists = $this->hasTable($db, 'organizations');
        $orgColumnExists = $this->hasColumn($db, 'assets', 'org_id');
        $orgFeatureEnabled = $orgTableExists && $orgColumnExists;

        $categoriesEnabled = $this->assetCategoriesEnabled($db);
        $sectionsEnabled = $this->assetSectionsEnabled($db);

        $data = $this->readAssetPost();

        if ($categoriesEnabled && !empty($data['category_id'])) {
            $name = $this->categoryNameById($db, (int)$data['category_id']);
            if ($name !== null) {
                $data['category'] = $name;
            } else {
                $data['category_id'] = null;
            }
        }

        if ($sectionsEnabled && !empty($data['subsection_id'])) {
            $realSectionId = $this->subsectionSectionId($db, (int)$data['subsection_id']);
            if ($realSectionId === null) {
                $data['id'] = $id;
                $data['image_path'] = $existing['image_path'] ?? null;
                View::render('assets/form', [
                    'csrf' => Csrf::token(),
                    'user' => $u,
                    'item' => $data,
                    'orgs' => $orgFeatureEnabled ? $this->orgsList() : [],
                    'orgEnabled' => $orgFeatureEnabled,
                    'orgTableExists' => $orgTableExists,
                    'orgColumnExists' => $orgColumnExists,
                    'categoriesEnabled' => $categoriesEnabled,
                    'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                    'sectionsEnabled' => $sectionsEnabled,
                    'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                    'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                    'error' => 'القسم الفرعي غير صحيح.',
                    'mode' => 'edit',
                ]);
                return;
            }
            if (!empty($data['section_id']) && (int)$data['section_id'] !== $realSectionId) {
                $data['id'] = $id;
                $data['image_path'] = $existing['image_path'] ?? null;
                View::render('assets/form', [
                    'csrf' => Csrf::token(),
                    'user' => $u,
                    'item' => $data,
                    'orgs' => $orgFeatureEnabled ? $this->orgsList() : [],
                    'orgEnabled' => $orgFeatureEnabled,
                    'orgTableExists' => $orgTableExists,
                    'orgColumnExists' => $orgColumnExists,
                    'categoriesEnabled' => $categoriesEnabled,
                    'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                    'sectionsEnabled' => $sectionsEnabled,
                    'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                    'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                    'error' => 'القسم الفرعي لا يتبع هذا القسم.',
                    'mode' => 'edit',
                ]);
                return;
            }
            if (empty($data['section_id'])) {
                $data['section_id'] = $realSectionId;
            }
        }

        $err = $this->validateAsset($data);
        if ($err !== null) {
            $data['id'] = $id;
            $data['image_path'] = $existing['image_path'] ?? null;
            View::render('assets/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgFeatureEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgFeatureEnabled,
                'orgTableExists' => $orgTableExists,
                'orgColumnExists' => $orgColumnExists,
                'categoriesEnabled' => $categoriesEnabled,
                'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                'sectionsEnabled' => $sectionsEnabled,
                'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                'error' => $err,
                'mode' => 'edit',
            ]);
            return;
        }

        $imgPath = $existing['image_path'] ?? null;
        $newImg = $this->handleImageUpload('image');
        if ($newImg !== null) {
            $imgPath = $newImg;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $orgColumnEnabled = $this->hasColumn($db, 'assets', 'org_id');
        if (!$orgColumnEnabled) {
            $data['org_id'] = null;
        }

        $set = [];
        $vals = [];
        if ($orgColumnEnabled) {
            $set[] = 'org_id=?';
            $vals[] = $data['org_id'];
        }
        $set = array_merge($set, ['name=?', 'category=?', 'asset_type=?', 'image_path=?', 'code=?', 'quantity=?', 'min_quantity=?', 'purchase_date=?', 'supplier=?', 'cost=?', 'asset_condition=?', 'location=?', 'plate_number=?', 'vehicle_model=?', 'vehicle_year=?', 'mileage=?', 'notes=?']);
        $vals = array_merge($vals, [
            $data['name'],
            $data['category'],
            $data['asset_type'],
            $imgPath,
            $data['code'],
            $data['quantity'],
            $data['min_quantity'],
            $data['purchase_date'],
            $data['supplier'],
            $data['cost'],
            $data['asset_condition'],
            $data['location'],
            $data['plate_number'],
            $data['vehicle_model'],
            $data['vehicle_year'],
            $data['mileage'],
            $data['notes'],
        ]);
        if ($categoriesEnabled) {
            $set[] = 'category_id=?';
            $vals[] = $data['category_id'] ?? null;
        }
        if ($sectionsEnabled) {
            $set[] = 'section_id=?';
            $vals[] = $data['section_id'] ?? null;
            $set[] = 'subsection_id=?';
            $vals[] = $data['subsection_id'] ?? null;
        }
        $set[] = 'updated_at=?';
        $vals[] = $now;
        $vals[] = $id;

        $stmt = $db->prepare('UPDATE assets SET ' . implode(',', $set) . ' WHERE id=? AND deleted_at IS NULL');

        try {
            $stmt->execute($vals);
        } catch (\Throwable) {
            $data['id'] = $id;
            $data['image_path'] = $existing['image_path'] ?? null;
            View::render('assets/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgFeatureEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgFeatureEnabled,
                'orgTableExists' => $orgTableExists,
                'orgColumnExists' => $orgColumnExists,
                'categoriesEnabled' => $categoriesEnabled,
                'assetCategories' => $categoriesEnabled ? $this->assetCategoriesList() : [],
                'sectionsEnabled' => $sectionsEnabled,
                'assetSections' => $sectionsEnabled ? $this->assetSectionsList() : [],
                'subsectionsBySection' => $sectionsEnabled ? $this->assetSubsectionsBySection() : [],
                'error' => 'تعذر تعديل الأصل.',
                'mode' => 'edit',
            ]);
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Edit', 'assets', 'ID=' . $id . ' ' . (string)$data['name']);
        
        // Record change history
        $changes = Notify::diff($existing, $data, ['id', 'created_at', 'updated_at', 'deleted_at', 'image_path']);
        if (!empty($changes)) {
            Notify::recordChange(
                'asset',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'update',
                $changes
            );
        }
        
        // Send notification
        Notify::create(
            null,
            (int)$u['id'],
            (string)$u['name'],
            'update',
            'asset',
            $id,
            (string)$data['name'],
            'تم تعديل بيانات الأصل: ' . (string)$data['name']
        );
        
        Http::redirect('/inventory?msg=updated');
    }

    public function moveBulkSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        if (!$this->assetSectionsEnabled($db)) {
            Http::redirect('/inventory?move_err=missing');
            return;
        }

        $idsRaw = $_POST['ids'] ?? [];
        if (!is_array($idsRaw)) {
            $idsRaw = [];
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn($x) => (int)$x, $idsRaw), static fn($x) => $x > 0)));
        if (empty($ids)) {
            Http::redirect('/inventory?move_err=empty');
            return;
        }

        $sectionId = (int)($_POST['section_id'] ?? 0);
        $subsectionId = (int)($_POST['subsection_id'] ?? 0);
        if ($sectionId < 0) $sectionId = 0;
        if ($subsectionId < 0) $subsectionId = 0;

        if ($subsectionId > 0) {
            $realSectionId = $this->subsectionSectionId($db, $subsectionId);
            if ($realSectionId === null) {
                Http::redirect('/inventory?move_err=sub_invalid');
                return;
            }
            if ($sectionId > 0 && $realSectionId !== $sectionId) {
                Http::redirect('/inventory?move_err=sub_mismatch');
                return;
            }
            if ($sectionId <= 0) {
                $sectionId = $realSectionId;
            }
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE assets SET section_id = ?, subsection_id = ?, updated_at = ? WHERE deleted_at IS NULL AND id IN (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $sectionId > 0 ? $sectionId : null,
            $subsectionId > 0 ? $subsectionId : null,
            $now,
            ...$ids,
        ]);

        $this->auth->audit(
            (int)$u['id'],
            (string)$u['name'],
            'Move',
            'assets',
            'count=' . count($ids) . ' section_id=' . ($sectionId > 0 ? (string)$sectionId : 'NULL') . ' subsection_id=' . ($subsectionId > 0 ? (string)$subsectionId : 'NULL')
        );

        Http::redirect('/inventory?msg=moved');
    }

    public function deleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/inventory');
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $item = $this->find($id);
        $stmt = $db->prepare('UPDATE assets SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$now, $now, $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['undo_assets'] = ['id' => $id, 't' => time()];
        }

        if ($item) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'assets', 'ID=' . $id . ' ' . (string)$item['name']);
            
            // Record change history
            Notify::recordChange(
                'asset',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                ['deleted' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                'asset',
                $id,
                (string)$item['name'],
                'تم حذف الأصل: ' . (string)$item['name']
            );
        }
        Http::redirect('/inventory?msg=deleted');
    }

    public function undoDeleteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_POST['id'] ?? 0);
        $undo = $_SESSION['undo_assets'] ?? null;
        if ($id <= 0 || !is_array($undo) || !isset($undo['id'], $undo['t']) || (int)$undo['id'] !== $id) {
            Http::redirect('/inventory');
            return;
        }

        $age = time() - (int)$undo['t'];
        if ($age < 0 || $age > self::UNDO_TTL_SECONDS) {
            unset($_SESSION['undo_assets']);
            Http::redirect('/inventory');
            return;
        }

        $db = $this->db();
        $stmtInfo = $db->prepare('SELECT name FROM assets WHERE id = ? LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE assets SET deleted_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$now, $id]);

        if ($stmt->rowCount() > 0) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Restore', 'assets', 'ID=' . $id . ' ' . (string)($target['name'] ?? ''));
            
            // Record change history
            Notify::recordChange(
                'asset',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                ['restored' => true]
            );
            
            // Send notification
            Notify::create(
                null,
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                'asset',
                $id,
                (string)($target['name'] ?? ''),
                'تم استعادة الأصل: ' . (string)($target['name'] ?? '')
            );
        }

        unset($_SESSION['undo_assets']);
        Http::redirect('/inventory?msg=restored');
    }

    public function image(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        $it = $this->find($id);
        if (!$it || empty($it['image_path'])) {
            http_response_code(404);
            return;
        }

        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$it['image_path']), '/');
        $file = $this->uploadsRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($file);
    }

    public function exportExcel(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $cat = trim((string)($_GET['cat'] ?? ''));
        $cond = trim((string)($_GET['cond'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'assets');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? 'SELECT a.id,a.name,a.category,a.code,a.quantity,a.cost,a.asset_condition,a.purchase_date,a.location,o.name AS org_name FROM assets a LEFT JOIN organizations o ON o.id = a.org_id WHERE a.deleted_at IS NULL'
            : 'SELECT a.id,a.name,a.category,a.code,a.quantity,a.cost,a.asset_condition,a.purchase_date,a.location FROM assets a WHERE a.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= ' AND a.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (a.name LIKE ? OR a.code LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $sql .= ' AND a.category = ?';
            $args[] = $cat;
        }
        if ($cond !== '') {
            $sql .= ' AND a.asset_condition = ?';
            $args[] = $cond;
        }
        $sql .= ' ORDER BY a.id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="assets.xls"');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for better Arabic support in Excel
        $now = new DateTimeImmutable('now');
        $title = 'تقرير الأصول';
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        echo '<html lang="ar" dir="rtl"><head><meta charset="utf-8" />'
            . '<style>'
            . 'body{font-family:Tahoma,Arial,sans-serif;font-size:12px;color:#111;}'
            . 'h2{font-size:16px;margin:0 0 8px;}'
            . '.meta{font-size:11px;color:#555;margin:0 0 10px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #999;padding:6px;vertical-align:top;}'
            . 'th{background:#f2f2f2;font-weight:bold;}'
            . '</style></head><body>';

        echo '<h2>' . $esc($title) . '</h2>';
        echo '<div class="meta">تاريخ: ' . $esc($now->format('Y-m-d H:i')) . ' — عدد السجلات: ' . (int)count($rows) . '</div>';

        echo '<table><thead><tr><th>ID</th>';
        if ($orgEnabled) echo '<th>المنظمة</th>';
        echo '<th>الاسم</th><th>الفئة</th><th>الكود</th><th>الكمية</th><th>التكلفة</th><th>الحالة</th><th>تاريخ الشراء</th><th>الموقع</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) {
                echo '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            }
            echo '<td>' . $esc((string)$r['name']) . '</td>';
            echo '<td>' . $esc((string)$r['category']) . '</td>';
            echo '<td>' . $esc((string)$r['code']) . '</td>';
            echo '<td>' . (int)$r['quantity'] . '</td>';
            echo '<td>' . $esc(number_format((float)($r['cost'] ?? 0), 2)) . '</td>';
            echo '<td>' . $esc((string)$r['asset_condition']) . '</td>';
            echo '<td>' . $esc((string)($r['purchase_date'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)($r['location'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }

    public function exportPdf(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $cat = trim((string)($_GET['cat'] ?? ''));
        $cond = trim((string)($_GET['cond'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'assets');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? 'SELECT a.id,a.name,a.category,a.code,a.quantity,a.cost,a.asset_condition,a.purchase_date,a.location,o.name AS org_name FROM assets a LEFT JOIN organizations o ON o.id = a.org_id WHERE a.deleted_at IS NULL'
            : 'SELECT a.id,a.name,a.category,a.code,a.quantity,a.cost,a.asset_condition,a.purchase_date,a.location FROM assets a WHERE a.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= ' AND a.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (a.name LIKE ? OR a.code LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($cat !== '') {
            $sql .= ' AND a.category = ?';
            $args[] = $cat;
        }
        if ($cond !== '') {
            $sql .= ' AND a.asset_condition = ?';
            $args[] = $cond;
        }
        $sql .= ' ORDER BY a.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $now = new DateTimeImmutable('now');
        $title = 'تقرير الأصول';

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8" />'
            . '<style>'
            . 'body{font-family:dejavusans;font-size:12px;color:#111;}'
            . 'h1{font-size:18px;margin:0 0 8px;}'
            . '.meta{font-size:11px;color:#555;margin-bottom:10px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #999;padding:6px;vertical-align:top;}'
            . 'th{background:#f2f2f2;}'
            . '</style></head><body>';

        $html .= '<h1>' . $esc($title) . '</h1>';
        $html .= '<div class="meta">تاريخ: ' . $esc($now->format('Y-m-d H:i')) . ' — عدد السجلات: ' . (int)count($rows) . '</div>';

        $html .= '<table><thead><tr>';
        $html .= '<th>ID</th>';
        if ($orgEnabled) $html .= '<th>المنظمة</th>';
        $html .= '<th>الاسم</th><th>الفئة</th><th>الكود</th><th>الكمية</th><th>التكلفة</th><th>الحالة</th><th>تاريخ الشراء</th><th>الموقع</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) $html .= '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['name']) . '</td>';
            $html .= '<td>' . $esc((string)$r['category']) . '</td>';
            $html .= '<td>' . $esc((string)$r['code']) . '</td>';
            $html .= '<td>' . (int)$r['quantity'] . '</td>';
            $html .= '<td>' . $esc(number_format((float)$r['cost'], 2)) . '</td>';
            $html .= '<td>' . $esc((string)$r['asset_condition']) . '</td>';
            $html .= '<td>' . $esc((string)($r['purchase_date'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)($r['location'] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        Pdf::download('inventory.pdf', $html);
    }

    /** @return array<string,mixed>|null */
    private function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $db = $this->db();
        $select = 'SELECT a.*';
        $joins = '';
        if ($this->assetCategoriesEnabled($db)) {
            $select .= ', ac.name AS category_name';
            $joins .= ' LEFT JOIN asset_categories ac ON ac.id = a.category_id';
        }
        if ($this->assetSectionsEnabled($db)) {
            $select .= ', s.name AS section_name, ss.name AS subsection_name';
            $joins .= ' LEFT JOIN asset_sections s ON s.id = a.section_id';
            $joins .= ' LEFT JOIN asset_subsections ss ON ss.id = a.subsection_id';
        }
        $stmt = $db->prepare($select . ' FROM assets a' . $joins . ' WHERE a.id = ? AND a.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed> */
    private function readAssetPost(): array
    {
        $orgId = (int)($_POST['org_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $subsectionId = (int)($_POST['subsection_id'] ?? 0);
        try {
            $db = $this->db();
            if (!$this->hasColumn($db, 'assets', 'org_id')) {
                $orgId = 0;
            }
        } catch (\Throwable) {
            $orgId = 0;
        }
        return [
            'org_id' => $orgId > 0 ? $orgId : null,
            'name' => trim((string)($_POST['name'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? '')),
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'subsection_id' => $subsectionId > 0 ? $subsectionId : null,
            'asset_type' => (string)($_POST['asset_type'] ?? 'general'),
            'code' => trim((string)($_POST['code'] ?? '')),
            'quantity' => max(0, (int)($_POST['quantity'] ?? 0)),
            'min_quantity' => max(0, (int)($_POST['min_quantity'] ?? 0)),
            'purchase_date' => (string)($_POST['purchase_date'] ?? null),
            'supplier' => trim((string)($_POST['supplier'] ?? '')) ?: null,
            'cost' => (float)($_POST['cost'] ?? 0),
            'asset_condition' => (string)($_POST['asset_condition'] ?? 'good'),
            'location' => trim((string)($_POST['location'] ?? '')) ?: null,
            'plate_number' => trim((string)($_POST['plate_number'] ?? '')) ?: null,
            'vehicle_model' => trim((string)($_POST['vehicle_model'] ?? '')) ?: null,
            'vehicle_year' => ($_POST['vehicle_year'] ?? '') !== '' ? (int)$_POST['vehicle_year'] : null,
            'mileage' => ($_POST['mileage'] ?? '') !== '' ? (int)$_POST['mileage'] : null,
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];
    }

    private function assetCategoriesEnabled(PDO $db): bool
    {
        return $this->hasTable($db, 'asset_categories') && $this->hasColumn($db, 'assets', 'category_id');
    }

    private function assetSectionsEnabled(PDO $db): bool
    {
        return $this->hasTable($db, 'asset_sections')
            && $this->hasTable($db, 'asset_subsections')
            && $this->hasColumn($db, 'assets', 'section_id')
            && $this->hasColumn($db, 'assets', 'subsection_id');
    }

    /** @return array<int,array{id:int,name:string}> */
    private function assetCategoriesList(): array
    {
        $db = $this->db();
        if (!$this->hasTable($db, 'asset_categories')) return [];
        try {
            /** @var array<int,array{id:int,name:string}> $rows */
            $rows = $db->query('SELECT id, name FROM asset_categories ORDER BY name ASC')->fetchAll();
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array{id:int,name:string}> */
    private function assetSectionsList(): array
    {
        $db = $this->db();
        if (!$this->hasTable($db, 'asset_sections')) return [];
        try {
            /** @var array<int,array{id:int,name:string}> $rows */
            $rows = $db->query('SELECT id, name FROM asset_sections ORDER BY name ASC')->fetchAll();
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,array<int,array{id:int,name:string}>> */
    private function assetSubsectionsBySection(): array
    {
        $db = $this->db();
        if (!$this->hasTable($db, 'asset_subsections')) return [];
        try {
            $rows = $db->query('SELECT id, section_id, name FROM asset_subsections ORDER BY name ASC')->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $sid = (string)(int)($r['section_id'] ?? 0);
            if ($sid === '0') continue;
            if (!isset($out[$sid])) $out[$sid] = [];
            $out[$sid][] = ['id' => (int)($r['id'] ?? 0), 'name' => (string)($r['name'] ?? '')];
        }
        return $out;
    }

    private function categoryNameById(PDO $db, int $id): ?string
    {
        if ($id <= 0 || !$this->hasTable($db, 'asset_categories')) return null;
        try {
            $stmt = $db->prepare('SELECT name FROM asset_categories WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $name = isset($row['name']) ? (string)$row['name'] : '';
            return $name !== '' ? $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function subsectionSectionId(PDO $db, int $subsectionId): ?int
    {
        if ($subsectionId <= 0 || !$this->hasTable($db, 'asset_subsections')) return null;
        try {
            $stmt = $db->prepare('SELECT section_id FROM asset_subsections WHERE id = ? LIMIT 1');
            $stmt->execute([$subsectionId]);
            $row = $stmt->fetch();
            $sid = (int)($row['section_id'] ?? 0);
            return $sid > 0 ? $sid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function validateAsset(array $data, bool $requireOrgOnCreate = false): ?string
    {
        if ($requireOrgOnCreate && empty($data['org_id'])) {
            $orgs = $this->orgsList();
            if (empty($orgs)) {
                return 'لا توجد منظمات نشطة. أضف منظمة من الإعدادات أولاً.';
            }
            return 'الرجاء اختيار المنظمة.';
        }
        if (($data['name'] ?? '') === '' || ($data['category'] ?? '') === '' || ($data['code'] ?? '') === '') {
            return 'الاسم والفئة والكود حقول مطلوبة.';
        }
        $allowedType = ['general', 'vehicle'];
        if (!in_array((string)$data['asset_type'], $allowedType, true)) {
            return 'نوع الأصل غير صحيح.';
        }
        $allowedCond = ['excellent', 'good', 'fair', 'poor', 'disposed'];
        if (!in_array((string)$data['asset_condition'], $allowedCond, true)) {
            return 'الحالة غير صحيحة.';
        }
        return null;
    }

    private function handleImageUpload(string $field): ?string
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        $size = (int)($f['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > 6_000_000) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        $gdAvailable = function_exists('imagecreatefromstring') && function_exists('imagejpeg');
        if (!$gdAvailable && !in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return null;
        }

        $now = new DateTimeImmutable('now');
        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $now->format('Y/m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = 'asset_' . $now->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $target = $dir . DIRECTORY_SEPARATOR . $name;

        $saved = false;
        if ($gdAvailable) {
            $raw = file_get_contents($tmp);
            if ($raw !== false) {
                $img = @imagecreatefromstring($raw);
                if ($img !== false) {
                    $saved = imagejpeg($img, $target, 82);
                    if (PHP_VERSION_ID < 80500) {
                        imagedestroy($img);
                    } else {
                        unset($img);
                    }
                }
            }
        }
        if (!$saved) {
            $saved = move_uploaded_file($tmp, $target);
        }

        if (!$saved) {
            return null;
        }

        return 'assets/' . $now->format('Y/m') . '/' . $name;
    }

    /** @return array<int,array{id:int,name:string}> */
    private function orgsList(): array
    {
        $db = $this->db();
        try {
            $stmt = $db->query('SELECT id, name FROM organizations WHERE is_active = 1 ORDER BY name ASC');
            /** @var array<int,array{id:int,name:string}> $rows */
            $rows = $stmt->fetchAll();
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }


}
