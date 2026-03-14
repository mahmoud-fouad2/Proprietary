<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use DateTimeImmutable;
use PDO;
use Zaco\Core\Http;
use Zaco\Core\Flash;
use Zaco\Core\Notify;
use Zaco\Core\Pdf;
use Zaco\Core\View;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

final class EmployeesController extends BaseController
{
    private const UNDO_TTL_SECONDS = 300;

    public function __construct(private readonly Auth $auth)
    {
    }

    private function ensureEmployeesOrgSupport(PDO $db): void
    {
        try {
            if (!$this->auth->isAdminLike() && !$this->auth->can('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        try {
            if (!$this->hasTable($db, 'organizations') || !$this->hasTable($db, 'employees')) {
                return;
            }

            if ($this->hasColumn($db, 'employees', 'org_id')) {
                return;
            }

            // Add org_id column (best-effort). If DB user lacks ALTER privileges, this will fail silently.
            try {
                $db->exec('ALTER TABLE employees ADD COLUMN org_id BIGINT UNSIGNED NULL');
            } catch (\Throwable) {
                return;
            }

            $this->clearHasColumnCache('employees', 'org_id');

            try {
                $db->exec('CREATE INDEX idx_employees_org_id ON employees(org_id)');
            } catch (\Throwable) {
                // ignore
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    public function index(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $dep = trim((string)($_GET['dep'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 25;

        $sort = (string)($_GET['sort'] ?? '');
        $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $db = $this->db();
        $this->ensureEmployeesOrgSupport($db);
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sortMap = [
            'id' => 'e.id',
            'name' => 'e.full_name',
            'no' => 'e.employee_no',
            'department' => 'e.department',
            'job' => 'e.job_title',
            'status' => 'e.emp_status',
        ];
        if ($orgEnabled) {
            $sortMap['org'] = 'o.name';
        }
        $orderExpr = $sortMap[$sort] ?? 'e.id';
        $orderBy = $orderExpr . ' ' . $dir . ', e.id DESC';

        $where = 'e.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $where .= ' AND e.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $where .= ' AND (e.full_name LIKE ? OR e.employee_no LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($dep !== '') {
            $where .= ' AND e.department = ?';
            $args[] = $dep;
        }
        if ($status !== '') {
            $where .= ' AND e.emp_status = ?';
            $args[] = $status;
        }

        $countStmt = $db->prepare('SELECT COUNT(*) AS c FROM employees e WHERE ' . $where);
        $countStmt->execute($args);
        $totalCount = (int)($countStmt->fetch()['c'] ?? 0);

        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $offset = max(0, $offset);

        $selectSql = $orgEnabled
            ? 'SELECT e.*, o.name AS org_name FROM employees e LEFT JOIN organizations o ON o.id = e.org_id'
            : 'SELECT e.* FROM employees e';
        $sql = $selectSql . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $items = $stmt->fetchAll();

        $depsSql = "SELECT DISTINCT e.department FROM employees e WHERE e.deleted_at IS NULL AND e.department IS NOT NULL AND e.department <> ''";
        $depsArgs = [];
        if ($orgEnabled && $orgId > 0) {
            $depsSql .= " AND e.org_id = ?";
            $depsArgs[] = $orgId;
        }
        $depsSql .= " ORDER BY e.department ASC";
        $depsStmt = $db->prepare($depsSql);
        $depsStmt->execute($depsArgs);
        $deps = array_values(array_filter(array_map(static fn($r) => (string)($r['department'] ?? ''), $depsStmt->fetchAll())));

        $undoId = 0;
        $undo = $_SESSION['undo_employees'] ?? null;
        if (is_array($undo) && isset($undo['id'], $undo['t'])) {
            $age = time() - (int)$undo['t'];
            if ($age >= 0 && $age <= self::UNDO_TTL_SECONDS) {
                $undoId = (int)$undo['id'];
            } else {
                unset($_SESSION['undo_employees']);
            }
        }

        View::render('employees/index', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'items' => $items,
            'q' => $q,
            'org_id' => $orgId,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'orgEnabled' => $orgEnabled,
            'dep' => $dep,
            'status' => $status,
            'deps' => $deps,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'count' => $totalCount,
            'sort' => $sort,
            'dir' => $dir,
            'canEdit' => $this->auth->can('edit_data'),
            'canDelete' => $this->auth->can('delete_data'),
            'undoId' => $undoId,
        ]);
    }

    public function importForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $db = $this->db();
        $this->ensureEmployeesOrgSupport($db);
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');

        View::render('employees/import', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'orgEnabled' => $orgEnabled,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'error' => null,
            'data' => null,
            'contacts_html' => null,
        ]);
    }

    public function importSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $dataText = trim((string)($_POST['data'] ?? ''));
        $contactsHtml = trim((string)($_POST['contacts_html'] ?? ''));

        $db = $this->db();
        $this->ensureEmployeesOrgSupport($db);
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');

        if ($dataText === '') {
            View::render('employees/import', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'orgEnabled' => $orgEnabled,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'error' => 'الرجاء لصق بيانات الموظفين (من Excel/Google Sheets).',
                'data' => $dataText,
                'contacts_html' => $contactsHtml,
            ]);
            return;
        }

        $rows = $this->parseEmployeesImportText($dataText);
        if (empty($rows)) {
            View::render('employees/import', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'orgEnabled' => $orgEnabled,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'error' => 'لم يتم التعرف على أي صفوف صالحة. تأكد أنك لصقت جدولاً (Tab/CSV) وليس نصاً عادياً.',
                'data' => $dataText,
                'contacts_html' => $contactsHtml,
            ]);
            return;
        }

        $contactsByName = [];
        if ($contactsHtml !== '') {
            $contactsByName = $this->parseContactsFromUsersHtml($contactsHtml);
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $invalid = 0;
        $seen = [];

        $orgNameToId = [];
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        try {
            $db->beginTransaction();

            foreach ($rows as $r) {
                $fullName = trim((string)($r['full_name'] ?? ''));
                if ($fullName === '') {
                    $invalid++;
                    continue;
                }

                $orgId = null;
                $orgName = trim((string)($r['org_name'] ?? ''));
                if ($orgEnabled && $orgName !== '') {
                    $orgId = $this->getOrCreateOrgId($db, $orgName, $orgNameToId, $now);
                }

                $email = $this->cleanEmail($r['email'] ?? null);
                $phone = $this->cleanPhone($r['phone'] ?? null);
                $jobTitle = $this->cleanText($r['job_title'] ?? null);
                $department = $this->cleanText($r['department'] ?? null);

                $nameKey = $this->nameKey($fullName);
                $dedupeKey = $email !== null ? ('email:' . $email) : ('name:' . $nameKey . '|org:' . (string)($orgId ?? 0));
                if (isset($seen[$dedupeKey])) {
                    $skipped++;
                    continue;
                }
                $seen[$dedupeKey] = true;

                // Enrich from pasted Users HTML if we can match by name.
                $contact = $this->findContactForEmployeeRow($contactsByName, $fullName, $r);
                if ($contact !== null) {
                    if ($email === null && isset($contact['email'])) {
                        $email = $this->cleanEmail($contact['email']);
                    }
                    if ($phone === null && isset($contact['phone'])) {
                        $phone = $this->cleanPhone($contact['phone']);
                    }
                    if ($jobTitle === null && isset($contact['job_title'])) {
                        $jobTitle = $this->cleanText($contact['job_title']);
                    }
                    if ($department === null && isset($contact['department'])) {
                        $department = $this->cleanText($contact['department']);
                    }
                }

                // Prefer skipping duplicates rather than failing on unique constraints.
                $existing = $this->findExistingEmployee($db, $fullName, $email, $orgId);
                if ($existing !== null) {
                    $didUpdate = $this->updateEmployeeMissingFields($db, $existing, [
                        'email' => $email,
                        'phone' => $phone,
                        'job_title' => $jobTitle,
                        'department' => $department,
                        'org_id' => $orgId,
                        'updated_at' => $now,
                    ]);
                    if ($didUpdate) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $employeeNo = trim((string)($r['employee_no'] ?? ''));
                if ($employeeNo === '') {
                    $employeeNo = $this->generateEmployeeNo($db);
                }

                if ($this->employeeNoExists($db, $employeeNo)) {
                    $skipped++;
                    continue;
                }

                $cols = [
                    'full_name' => $fullName,
                    'employee_no' => $employeeNo,
                ];
                $this->addEmployeeCol($db, $cols, 'department', $department);
                $this->addEmployeeCol($db, $cols, 'job_title', $jobTitle);
                $this->addEmployeeCol($db, $cols, 'phone', $phone);
                $this->addEmployeeCol($db, $cols, 'email', $email);
                $this->addEmployeeCol($db, $cols, 'contract_type', 'permanent');
                $this->addEmployeeCol($db, $cols, 'emp_status', 'active');
                $this->addEmployeeCol($db, $cols, 'created_at', $now);
                $this->addEmployeeCol($db, $cols, 'updated_at', $now);

                if ($orgEnabled && $this->hasColumn($db, 'employees', 'org_id')) {
                    $cols['org_id'] = $orgId;
                }

                $stmt = $this->prepareInsertEmployees($db, array_keys($cols));

                try {
                    $stmt->execute(array_values($cols));
                } catch (\Throwable $e) {
                    // If a provided employee_no collides, skip; if generated, retry a few times.
                    if (($r['employee_no'] ?? '') === '') {
                        $retryOk = false;
                        for ($i = 0; $i < 3; $i++) {
                            $cols['employee_no'] = $this->generateEmployeeNo($db);
                            $stmt = $this->prepareInsertEmployees($db, array_keys($cols));
                            try {
                                $stmt->execute(array_values($cols));
                                $retryOk = true;
                                break;
                            } catch (\Throwable) {
                                // keep retrying
                            }
                        }
                        if (!$retryOk) {
                            $this->logApp('Employees import failed: ' . $e->getMessage() . ' | name=' . $fullName . ' | user_id=' . (string)($u['id'] ?? ''));
                            $invalid++;
                            continue;
                        }
                    } else {
                        $this->logApp('Employees import skipped row: ' . $e->getMessage() . ' | employee_no=' . $employeeNo . ' | name=' . $fullName . ' | user_id=' . (string)($u['id'] ?? ''));
                        $skipped++;
                        continue;
                    }
                }

                $inserted++;
            }

            $db->commit();
        } catch (\Throwable $e) {
            try {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (\Throwable) {
                // ignore
            }

            $this->logApp('Employees import batch failed: ' . $e->getMessage() . ' | user_id=' . (string)($u['id'] ?? ''));
            View::render('employees/import', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'orgEnabled' => $orgEnabled,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'error' => 'تعذر تنفيذ الاستيراد. راجع السجل: storage/logs/app.log',
                'data' => $dataText,
                'contacts_html' => $contactsHtml,
            ]);
            return;
        }

        Flash::success('تم الاستيراد: إضافة ' . $inserted . '، تحديث ' . $updated . '، تجاهل (مكرر) ' . $skipped . '، غير صالح ' . $invalid . '.');
        Http::redirect('/employees');
    }

    public function show(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) {
            Http::redirect('/employees');
            return;
        }

        $db = $this->db();

        $notesStmt = $db->prepare('SELECT n.*, u.name AS created_by_name FROM employee_notes n LEFT JOIN users u ON u.id = n.created_by_user_id WHERE n.employee_id = ? ORDER BY n.created_at DESC');
        $notesStmt->execute([$id]);
        $notes = $notesStmt->fetchAll();

        $reportsStmt = $db->prepare('SELECT r.*, u.name AS created_by_name FROM employee_reports r LEFT JOIN users u ON u.id = r.created_by_user_id WHERE r.employee_id = ? ORDER BY r.created_at DESC');
        $reportsStmt->execute([$id]);
        $reports = $reportsStmt->fetchAll();

        $awardsStmt = $db->prepare('SELECT a.*, u.name AS created_by_name FROM employee_awards a LEFT JOIN users u ON u.id = a.created_by_user_id WHERE a.employee_id = ? ORDER BY a.created_at DESC');
        $awardsStmt->execute([$id]);
        $awards = $awardsStmt->fetchAll();

        View::render('employees/show', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'notes' => $notes,
            'reports' => $reports,
            'awards' => $awards,
            'canEdit' => $this->auth->can('edit_data'),
        ]);
    }

    public function noteSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $item = $this->find($employeeId);
        if (!$item) {
            Http::redirect('/employees');
            return;
        }

        $text = trim((string)($_POST['note_text'] ?? ''));
        $date = trim((string)($_POST['note_date'] ?? ''));
        if ($text === '') {
            Http::redirect('/employees/show?id=' . $employeeId . '&msg=note_err');
            return;
        }
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000);
        }
        $dateVal = $date !== '' ? $date : null;

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO employee_notes (employee_id,note_date,note_text,created_by_user_id,created_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$employeeId, $dateVal, $text, (int)$u['id'], $now]);

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'employee_notes', 'emp_id=' . $employeeId);
        Http::redirect('/employees/show?id=' . $employeeId . '&msg=note_added');
    }

    public function reportSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $item = $this->find($employeeId);
        if (!$item) {
            Http::redirect('/employees');
            return;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $text = trim((string)($_POST['report_text'] ?? ''));
        $from = trim((string)($_POST['period_from'] ?? ''));
        $to = trim((string)($_POST['period_to'] ?? ''));

        if ($title === '' || $text === '') {
            Http::redirect('/employees/show?id=' . $employeeId . '&msg=report_err');
            return;
        }
        if (mb_strlen($title) > 190) $title = mb_substr($title, 0, 190);
        if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO employee_reports (employee_id,period_from,period_to,title,report_text,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $employeeId,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $title,
            $text,
            (int)$u['id'],
            $now,
        ]);

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'employee_reports', 'emp_id=' . $employeeId . ' ' . $title);
        Http::redirect('/employees/show?id=' . $employeeId . '&msg=report_added');
    }

    public function awardSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            return;
        }

        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $item = $this->find($employeeId);
        if (!$item) {
            Http::redirect('/employees');
            return;
        }

        $title = trim((string)($_POST['award_title'] ?? ''));
        $text = trim((string)($_POST['award_text'] ?? ''));
        $issue = trim((string)($_POST['issue_date'] ?? ''));

        if ($title === '' || $text === '') {
            Http::redirect('/employees/show?id=' . $employeeId . '&msg=award_err');
            return;
        }
        if (mb_strlen($title) > 190) $title = mb_substr($title, 0, 190);
        if (mb_strlen($text) > 8000) $text = mb_substr($text, 0, 8000);

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $stmt = $db->prepare('INSERT INTO employee_awards (employee_id,issue_date,award_title,award_text,created_by_user_id,created_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            $employeeId,
            $issue !== '' ? $issue : null,
            $title,
            $text,
            (int)$u['id'],
            $now,
        ]);
        $awardId = (int)$db->lastInsertId();

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'employee_awards', 'emp_id=' . $employeeId . ' award_id=' . $awardId);
        Http::redirect('/employees/show?id=' . $employeeId . '&msg=award_added');
    }

    public function awardPrint(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Http::redirect('/employees');
            return;
        }

        $db = $this->db();
        $stmt = $db->prepare('SELECT a.*, e.full_name, e.employee_no FROM employee_awards a JOIN employees e ON e.id = a.employee_id WHERE a.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $award = $stmt->fetch();
        if (!$award) {
            Http::redirect('/employees');
            return;
        }

        View::render('employees/award_print', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'award' => $award,
        ]);
    }

    public function createForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $orgId = (int)($_GET['org_id'] ?? 0);
        $db = $this->db();

        $this->ensureEmployeesOrgSupport($db);
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $orgs = $orgEnabled ? $this->orgsList() : [];
        $defaultOrgId = $orgId > 0 ? $orgId : (count($orgs) === 1 ? (int)$orgs[0]['id'] : 0);

        View::render('employees/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $defaultOrgId > 0 ? ['org_id' => $defaultOrgId] : null,
            'orgs' => $orgs,
            'orgEnabled' => $orgEnabled,
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
        $this->ensureEmployeesOrgSupport($db);

        $data = $this->readPost();
        $err = $this->validate($data);
        if ($err !== null) {
            $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
            View::render('employees/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'error' => $err,
                'mode' => 'create',
            ]);
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        // $db already initialized

        // Build INSERT in a schema-tolerant way (deployments may have missing columns).
        $cols = [
            'full_name' => $data['full_name'],
            'employee_no' => $data['employee_no'],
        ];

        $this->addEmployeeCol($db, $cols, 'department', $data['department']);
        $this->addEmployeeCol($db, $cols, 'job_title', $data['job_title']);
        $this->addEmployeeCol($db, $cols, 'hire_date', $data['hire_date']);
        $this->addEmployeeCol($db, $cols, 'salary', $data['salary']);
        $this->addEmployeeCol($db, $cols, 'allowances', $data['allowances']);
        $this->addEmployeeCol($db, $cols, 'deductions', $data['deductions']);
        $this->addEmployeeCol($db, $cols, 'phone', $data['phone']);
        $this->addEmployeeCol($db, $cols, 'email', $data['email']);
        $this->addEmployeeCol($db, $cols, 'national_id', $data['national_id']);
        $this->addEmployeeCol($db, $cols, 'contract_type', $data['contract_type']);
        $this->addEmployeeCol($db, $cols, 'emp_status', $data['emp_status']);
        $this->addEmployeeCol($db, $cols, 'notes', $data['notes']);
        $this->addEmployeeCol($db, $cols, 'created_at', $now);
        $this->addEmployeeCol($db, $cols, 'updated_at', $now);

        $orgEnabled = $this->hasColumn($db, 'employees', 'org_id');
        if ($orgEnabled) {
            $cols['org_id'] = $data['org_id'];
        } else {
            $data['org_id'] = null;
        }

        if ($this->hasColumn($db, 'employees', 'device_password')) {
            $cols['device_password'] = $data['device_password'];
        }

        if ($this->hasColumn($db, 'employees', 'photo')) {
            $photoUploaded = end($data['_photo_upload']);
            $cols['photo'] = $photoUploaded ?: ($data['photo'] ?? null);
        }

        $colNames = implode(',', array_keys($cols));
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $stmt = $db->prepare("INSERT INTO employees ($colNames) VALUES ($placeholders)");

        try {
            $stmt->execute(array_values($cols));
        } catch (\Throwable $e) {
            $this->logApp('Employees create failed: ' . $e->getMessage() . ' | employee_no=' . (string)($data['employee_no'] ?? '') . ' | user_id=' . (string)($u['id'] ?? ''));
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
            View::render('employees/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'error' => 'تعذر حفظ الموظف (تأكد أن الرقم الوظيفي غير مكرر).',
                'mode' => 'create',
            ]);
            return;
        }

        $newId = (int)$db->lastInsertId();
        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Create', 'employees', $data['full_name'] . ' [' . $data['employee_no'] . ']');
        
        // Send notification
        Notify::create(
            null, // admin notification
            (int)$u['id'],
            (string)$u['name'],
            'create',
            'employee',
            $newId,
            $data['full_name'],
            'تمت إضافة موظف جديد: ' . $data['full_name']
        );
        
        Http::redirect('/employees?msg=created');
    }

    public function editForm(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $id = (int)($_GET['id'] ?? 0);
        $item = $this->find($id);
        if (!$item) {
            Http::redirect('/employees');
        }

        $db = $this->db();
        $this->ensureEmployeesOrgSupport($db);
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');

        View::render('employees/form', [
            'csrf' => Csrf::token(),
            'user' => $u,
            'item' => $item,
            'orgs' => $orgEnabled ? $this->orgsList() : [],
            'orgEnabled' => $orgEnabled,
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

        $db = $this->db();
        $this->ensureEmployeesOrgSupport($db);

        $id = (int)($_POST['id'] ?? 0);
        $existing = $this->find($id);
        if (!$existing) {
            Http::redirect('/employees');
        }

        $data = $this->readPost();
        $err = $this->validate($data);
        if ($err !== null) {
            $data['id'] = $id;
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
            View::render('employees/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'error' => $err,
                'mode' => 'edit',
            ]);
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        // $db already initialized

        // Build UPDATE in a schema-tolerant way.
        $cols = [
            'full_name' => $data['full_name'],
            'employee_no' => $data['employee_no'],
        ];

        $this->addEmployeeCol($db, $cols, 'department', $data['department']);
        $this->addEmployeeCol($db, $cols, 'job_title', $data['job_title']);
        $this->addEmployeeCol($db, $cols, 'hire_date', $data['hire_date']);
        $this->addEmployeeCol($db, $cols, 'salary', $data['salary']);
        $this->addEmployeeCol($db, $cols, 'allowances', $data['allowances']);
        $this->addEmployeeCol($db, $cols, 'deductions', $data['deductions']);
        $this->addEmployeeCol($db, $cols, 'phone', $data['phone']);
        $this->addEmployeeCol($db, $cols, 'email', $data['email']);
        $this->addEmployeeCol($db, $cols, 'national_id', $data['national_id']);
        $this->addEmployeeCol($db, $cols, 'contract_type', $data['contract_type']);
        $this->addEmployeeCol($db, $cols, 'emp_status', $data['emp_status']);
        $this->addEmployeeCol($db, $cols, 'notes', $data['notes']);
        $this->addEmployeeCol($db, $cols, 'updated_at', $now);

        $orgEnabled = $this->hasColumn($db, 'employees', 'org_id');
        if ($orgEnabled) {
            $cols['org_id'] = $data['org_id'];
        } else {
            $data['org_id'] = null;
        }

        $photoUploaded = end($data['_photo_upload']);
        if ($this->hasColumn($db, 'employees', 'device_password')) {
            $cols['device_password'] = $data['device_password'];
        }

        if ($this->hasColumn($db, 'employees', 'photo') && $photoUploaded) {
            $cols['photo'] = $photoUploaded;
        }

        $setPart = implode(',', array_map(fn($k) => "$k=?", array_keys($cols)));
        $stmt = $db->prepare("UPDATE employees SET $setPart WHERE id=? AND deleted_at IS NULL");
        
        $vals = array_values($cols);
        $vals[] = $id;

        try {
            $stmt->execute($vals);
        } catch (\Throwable $e) {
            $this->logApp('Employees update failed: ' . $e->getMessage() . ' | id=' . (string)$id . ' | employee_no=' . (string)($data['employee_no'] ?? '') . ' | user_id=' . (string)($u['id'] ?? ''));
            $data['id'] = $id;
            $db = $this->db();
            $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
            View::render('employees/form', [
                'csrf' => Csrf::token(),
                'user' => $u,
                'item' => $data,
                'orgs' => $orgEnabled ? $this->orgsList() : [],
                'orgEnabled' => $orgEnabled,
                'error' => 'تعذر تعديل الموظف.',
                'mode' => 'edit',
            ]);
            return;
        }

        $this->auth->audit((int)$u['id'], (string)$u['name'], 'Edit', 'employees', 'ID=' . $id . ' ' . $data['full_name']);
        
        // Record change history
        $changes = Notify::diff($existing, $data, ['id', 'created_at', 'updated_at', 'deleted_at', '_photo_upload']);
        if (!empty($changes)) {
            Notify::recordChange(
                'employee',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'update',
                $changes
            );
        }
        
        // Send notification
        Notify::create(
            null, // admin notification
            (int)$u['id'],
            (string)$u['name'],
            'update',
            'employee',
            $id,
            $data['full_name'],
            'تم تعديل بيانات الموظف: ' . $data['full_name']
        );
        
        Http::redirect('/employees?msg=updated');
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
            Http::redirect('/employees');
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $db = $this->db();
        $item = $this->find($id);
        $stmt = $db->prepare('UPDATE employees SET deleted_at=?, updated_at=? WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$now, $now, $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['undo_employees'] = ['id' => $id, 't' => time()];
        }

        if ($item) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Delete', 'employees', 'ID=' . $id . ' ' . (string)$item['full_name']);
            
            // Record change history
            Notify::recordChange(
                'employee',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                ['deleted' => true]
            );
            
            // Send notification
            Notify::create(
                null, // admin notification
                (int)$u['id'],
                (string)$u['name'],
                'delete',
                'employee',
                $id,
                (string)$item['full_name'],
                'تم حذف الموظف: ' . (string)$item['full_name']
            );
        }
        Http::redirect('/employees?msg=deleted');
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
        $undo = $_SESSION['undo_employees'] ?? null;
        if ($id <= 0 || !is_array($undo) || !isset($undo['id'], $undo['t']) || (int)$undo['id'] !== $id) {
            Http::redirect('/employees');
            return;
        }

        $age = time() - (int)$undo['t'];
        if ($age < 0 || $age > self::UNDO_TTL_SECONDS) {
            unset($_SESSION['undo_employees']);
            Http::redirect('/employees');
            return;
        }

        $db = $this->db();
        $stmtInfo = $db->prepare('SELECT full_name FROM employees WHERE id = ? LIMIT 1');
        $stmtInfo->execute([$id]);
        $target = $stmtInfo->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE employees SET deleted_at = NULL, updated_at = ? WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$now, $id]);

        if ($stmt->rowCount() > 0) {
            $this->auth->audit((int)$u['id'], (string)$u['name'], 'Restore', 'employees', 'ID=' . $id . ' ' . (string)($target['full_name'] ?? ''));
            
            // Record change history
            Notify::recordChange(
                'employee',
                $id,
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                ['restored' => true]
            );
            
            // Send notification
            Notify::create(
                null, // admin notification
                (int)$u['id'],
                (string)$u['name'],
                'restore',
                'employee',
                $id,
                (string)($target['full_name'] ?? ''),
                'تم استعادة الموظف: ' . (string)($target['full_name'] ?? '')
            );
        }

        unset($_SESSION['undo_employees']);
        Http::redirect('/employees?msg=restored');
    }

    public function exportExcel(): void
    {
        $u = $this->auth->user();
        if (!$u) Http::redirect('/login');

        $q = trim((string)($_GET['q'] ?? ''));
        $orgId = (int)($_GET['org_id'] ?? 0);
        $dep = trim((string)($_GET['dep'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? 'SELECT e.id,e.full_name,e.employee_no,e.department,e.job_title,e.phone,e.email,e.emp_status,o.name AS org_name FROM employees e LEFT JOIN organizations o ON o.id = e.org_id WHERE e.deleted_at IS NULL'
            : 'SELECT e.id,e.full_name,e.employee_no,e.department,e.job_title,e.phone,e.email,e.emp_status FROM employees e WHERE e.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= ' AND e.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (e.full_name LIKE ? OR e.employee_no LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($dep !== '') {
            $sql .= ' AND e.department = ?';
            $args[] = $dep;
        }
        if ($status !== '') {
            $sql .= ' AND e.emp_status = ?';
            $args[] = $status;
        }
        $sql .= ' ORDER BY e.id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees.xls"');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for better Arabic support in Excel
        $now = new DateTimeImmutable('now');
        $title = 'تقرير الموظفين';
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
        echo '<th>الاسم</th><th>الرقم</th><th>القسم</th><th>الوظيفة</th><th>الهاتف</th><th>البريد</th><th>الحالة</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) {
                echo '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            }
            echo '<td>' . $esc((string)$r['full_name']) . '</td>';
            echo '<td>' . $esc((string)$r['employee_no']) . '</td>';
            echo '<td>' . $esc((string)($r['department'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)($r['job_title'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)($r['phone'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)($r['email'] ?? '')) . '</td>';
            echo '<td>' . $esc((string)$r['emp_status']) . '</td>';
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
        $dep = trim((string)($_GET['dep'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $db = $this->db();
        $orgEnabled = $this->orgFeatureEnabled($db, 'employees');
        if (!$orgEnabled) {
            $orgId = 0;
        }

        $sql = $orgEnabled
            ? 'SELECT e.id,e.full_name,e.employee_no,e.department,e.job_title,e.phone,e.email,e.emp_status,o.name AS org_name FROM employees e LEFT JOIN organizations o ON o.id = e.org_id WHERE e.deleted_at IS NULL'
            : 'SELECT e.id,e.full_name,e.employee_no,e.department,e.job_title,e.phone,e.email,e.emp_status FROM employees e WHERE e.deleted_at IS NULL';
        $args = [];
        if ($orgEnabled && $orgId > 0) {
            $sql .= ' AND e.org_id = ?';
            $args[] = $orgId;
        }
        if ($q !== '') {
            $sql .= ' AND (e.full_name LIKE ? OR e.employee_no LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
            $args[] = '%' . $q . '%';
        }
        if ($dep !== '') {
            $sql .= ' AND e.department = ?';
            $args[] = $dep;
        }
        if ($status !== '') {
            $sql .= ' AND e.emp_status = ?';
            $args[] = $status;
        }
        $sql .= ' ORDER BY e.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $now = new DateTimeImmutable('now');
        $title = 'تقرير الموظفين';
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
        $html .= '<th>الاسم</th><th>الرقم</th><th>القسم</th><th>الوظيفة</th><th>الهاتف</th><th>البريد</th><th>الحالة</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . (int)$r['id'] . '</td>';
            if ($orgEnabled) $html .= '<td>' . $esc((string)($r['org_name'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['full_name']) . '</td>';
            $html .= '<td>' . $esc((string)$r['employee_no']) . '</td>';
            $html .= '<td>' . $esc((string)($r['department'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)($r['job_title'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)($r['phone'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)($r['email'] ?? '')) . '</td>';
            $html .= '<td>' . $esc((string)$r['emp_status']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        Pdf::download('employees.pdf', $html);
    }

    public function photo(): void
    {
        $u = $this->auth->user();
        if (!$u) {
            http_response_code(401);
            echo 'Unauthorized';
            return;
        }

        if (!$this->auth->can('view_employees')) {
            http_response_code(403);
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        $row = $this->find($id);
        if (!$row || empty($row['photo'])) {
            http_response_code(404);
            return;
        }

        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$row['photo']), '/');
        $file = $this->uploadsRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }

        $contentType = 'image/jpeg';
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = (string)$finfo->file($file);
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (in_array($detected, $allowed, true)) {
                $contentType = $detected;
            } else {
                http_response_code(404);
                return;
            }
        } catch (\Throwable) {
            // keep default
        }

        header('Content-Type: ' . $contentType);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($file);
    }

    /** @return array<string,mixed>|null */
    private function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed> */
    private function readPost(): array
    {
        $orgId = (int)($_POST['org_id'] ?? 0);
        try {
            $db = $this->db();
            if (!$this->hasColumn($db, 'employees', 'org_id')) {
                $orgId = 0;
            }
        } catch (\Throwable) {
            $orgId = 0;
        }
        
        $upload = $this->handleUpload('photo');

        $hireDateRaw = trim((string)($_POST['hire_date'] ?? ''));
        $hireDate = $hireDateRaw !== '' ? $hireDateRaw : null;

        return [
            'org_id' => $orgId > 0 ? $orgId : null,
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'employee_no' => trim((string)($_POST['employee_no'] ?? '')),
            'department' => trim((string)($_POST['department'] ?? '')) ?: null,
            'job_title' => trim((string)($_POST['job_title'] ?? '')) ?: null,
            'hire_date' => $hireDate,
            'salary' => (float)($_POST['salary'] ?? 0),
            'allowances' => (float)($_POST['allowances'] ?? 0),
            'deductions' => (float)($_POST['deductions'] ?? 0),
            'phone' => trim((string)($_POST['phone'] ?? '')) ?: null,
            'email' => trim((string)($_POST['email'] ?? '')) ?: null,
            'national_id' => trim((string)($_POST['national_id'] ?? '')) ?: null,
            'contract_type' => (string)($_POST['contract_type'] ?? 'permanent'),
            'emp_status' => (string)($_POST['emp_status'] ?? 'active'),
            'device_password' => trim((string)($_POST['device_password'] ?? '')) ?: null,
            'photo' => $upload['path'] ?: null, // Only used in create
            '_photo_upload' => [$upload['path'] ?: null], // Keep track if new uploaded
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];
    }

    private function addEmployeeCol(PDO $db, array &$cols, string $column, mixed $value): void
    {
        if ($this->hasColumn($db, 'employees', $column)) {
            $cols[$column] = $value;
        }
    }

    private function logApp(string $message): void
    {
        try {
            $logsDir = $this->storageRoot() . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0775, true);
            }
            $file = $logsDir . DIRECTORY_SEPARATOR . 'app.log';
            $ts = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            @file_put_contents($file, '[' . $ts . '] ' . $message . "\n", FILE_APPEND);
        } catch (\Throwable) {
            // ignore logging errors
        }
    }

    /** @return array{path:?string,size:int} */
    private function handleUpload(string $field): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return ['path' => null, 'size' => 0];
        }
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'size' => 0];
        }
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'size' => 0];
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        $size = (int)($f['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > 20_000_000) {
            return ['path' => null, 'size' => 0];
        }

        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mime, $allowed, true)) {
                return ['path' => null, 'size' => 0];
            }
        } catch (\Throwable) {
            // If mime detection isn't available, fall back to accepting the upload.
        }

        $origName = (string)($f['name'] ?? 'photo');
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName) ?: 'photo';

        $now = new DateTimeImmutable('now');
        $dir = $this->uploadsRoot() . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $now->format('Y/m'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $targetName = $now->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase;
        $target = $dir . DIRECTORY_SEPARATOR . $targetName;

        if (!move_uploaded_file($tmp, $target)) {
            return ['path' => null, 'size' => 0];
        }

        return [
            'path' => 'employees/' . $now->format('Y/m') . '/' . $targetName,
            'size' => $size,
        ];
    }

    /** @return array<int,array{id:int,name:string}> */
    private function orgsList(): array
    {
        $db = $this->db();
        try {
            if (!$this->hasTable($db, 'organizations')) {
                return [];
            }

            $nameCol = null;
            if ($this->hasColumn($db, 'organizations', 'name')) {
                $nameCol = 'name';
            } elseif ($this->hasColumn($db, 'organizations', 'org_name')) {
                $nameCol = 'org_name';
            } elseif ($this->hasColumn($db, 'organizations', 'title')) {
                $nameCol = 'title';
            }

            if ($nameCol === null) {
                return [];
            }

            $sql = 'SELECT id, ' . $nameCol . ' AS name FROM organizations';
            if ($this->hasColumn($db, 'organizations', 'is_active')) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY name ASC';
            $stmt = $db->query($sql);
            /** @var array<int,array{id:int,name:string}> $rows */
            $rows = $stmt->fetchAll();
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private function validate(array $data): ?string
    {
        if (($data['full_name'] ?? '') === '' || ($data['employee_no'] ?? '') === '') {
            return 'الاسم والرقم الوظيفي حقول مطلوبة.';
        }
        $allowedContract = ['permanent','temporary','parttime','freelance'];
        if (!in_array((string)$data['contract_type'], $allowedContract, true)) {
            return 'نوع العقد غير صحيح.';
        }
        $allowedStatus = ['active','suspended','resigned','terminated'];
        if (!in_array((string)$data['emp_status'], $allowedStatus, true)) {
            return 'حالة الموظف غير صحيحة.';
        }
        return null;
    }

    /** @return array<int,array{full_name:string,org_name:?string,department:?string,job_title:?string,email:?string,phone:?string,employee_no:?string,match_name?:string}> */
    private function parseEmployeesImportText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split('/\n+/', $text) ?: [];

        $rows = [];
        $headerMap = null;

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            $cells = $this->splitRowCells($line);
            $cells = array_values(array_map(static fn($c) => trim((string)$c, " \t\n\r\0\x0B\"'"), $cells));

            if ($headerMap === null) {
                $maybeHeader = $this->buildHeaderMap($cells);
                if ($maybeHeader !== null) {
                    $headerMap = $maybeHeader;
                    continue;
                }
            }

            if ($headerMap !== null) {
                $row = [
                    'full_name' => '',
                    'org_name' => null,
                    'department' => null,
                    'job_title' => null,
                    'email' => null,
                    'phone' => null,
                    'employee_no' => null,
                ];

                foreach ($headerMap as $idx => $key) {
                    $val = $cells[$idx] ?? '';
                    if ($key === 'full_name') {
                        $row['full_name'] = (string)$val;
                    } elseif ($key === 'org_name') {
                        $row['org_name'] = $val !== '' ? (string)$val : null;
                    } elseif ($key === 'department') {
                        $row['department'] = $val !== '' ? (string)$val : null;
                    } elseif ($key === 'job_title') {
                        $row['job_title'] = $val !== '' ? (string)$val : null;
                    } elseif ($key === 'email') {
                        $row['email'] = $val !== '' ? (string)$val : null;
                    } elseif ($key === 'phone') {
                        $row['phone'] = $val !== '' ? (string)$val : null;
                    } elseif ($key === 'employee_no') {
                        $row['employee_no'] = $val !== '' ? (string)$val : null;
                    }
                }

                $row['full_name'] = trim((string)$row['full_name']);
                if ($row['full_name'] !== '') {
                    $rows[] = $row;
                }
                continue;
            }

            // No header: support the screenshot/Excel format:
            // title_en, title_ar, org, name_en, name_ar, [index]
            if (count($cells) >= 5 && count($cells) <= 6 && ($cells[2] ?? '') !== '') {
                $titleEn = (string)($cells[0] ?? '');
                $titleAr = (string)($cells[1] ?? '');
                $org = (string)($cells[2] ?? '');
                $nameEn = (string)($cells[3] ?? '');
                $nameAr = (string)($cells[4] ?? '');

                $fullName = trim($nameAr !== '' ? $nameAr : $nameEn);
                if ($fullName === '') {
                    continue;
                }

                $job = trim($titleAr !== '' ? $titleAr : $titleEn);
                $rows[] = [
                    'full_name' => $fullName,
                    // For contacts enrichment: try matching against English name if present.
                    'match_name' => trim($nameEn !== '' ? $nameEn : $nameAr),
                    'org_name' => trim($org) !== '' ? trim($org) : null,
                    'department' => null,
                    'job_title' => $job !== '' ? $job : null,
                    'email' => null,
                    'phone' => null,
                    'employee_no' => null,
                ];
                continue;
            }

            // Fallback: org, full_name, job_title, [department], [email], [phone], [employee_no]
            if (count($cells) >= 2) {
                $org = trim((string)($cells[0] ?? ''));
                $fullName = trim((string)($cells[1] ?? ''));
                if ($fullName === '') {
                    continue;
                }

                $rows[] = [
                    'full_name' => $fullName,
                    'org_name' => $org !== '' ? $org : null,
                    'job_title' => isset($cells[2]) && trim((string)$cells[2]) !== '' ? trim((string)$cells[2]) : null,
                    'department' => isset($cells[3]) && trim((string)$cells[3]) !== '' ? trim((string)$cells[3]) : null,
                    'email' => isset($cells[4]) && trim((string)$cells[4]) !== '' ? trim((string)$cells[4]) : null,
                    'phone' => isset($cells[5]) && trim((string)$cells[5]) !== '' ? trim((string)$cells[5]) : null,
                    'employee_no' => isset($cells[6]) && trim((string)$cells[6]) !== '' ? trim((string)$cells[6]) : null,
                ];
            }
        }

        return $rows;
    }

    /** @return array<string,array{email?:string,phone?:string,job_title?:string,department?:string}> */
    private function parseContactsFromUsersHtml(string $html): array
    {
        $out = [];
        $bucket = [];

        try {
            $dom = new \DOMDocument();
            // Suppress warnings for malformed HTML
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new \DOMXPath($dom);
            $rows = $xpath->query('//tr');
            if ($rows === false) {
                return $out;
            }

            foreach ($rows as $tr) {
                $nameNode = $xpath->query('.//div[contains(@class,"font-bold")]', $tr);
                $name = '';
                if ($nameNode !== false && $nameNode->length > 0) {
                    $node0 = $nameNode->item(0);
                    $name = $node0 ? trim((string)$node0->textContent) : '';
                }
                if ($name === '') {
                    continue;
                }

                $text = (string)$tr->textContent;
                $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

                $email = null;
                if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
                    $email = $m[0];
                }

                $phone = null;
                if (preg_match('/\b\+?\d[\d\s\-]{7,}\d\b/', $text, $m)) {
                    $phone = preg_replace('/\s+/', '', $m[0]);
                }

                // Prefer reading job title + department by column index (Users table layout).
                $jobTitle = null;
                $department = null;
                $tds = $xpath->query('.//td', $tr);
                if ($tds !== false && $tds->length >= 5) {
                    $jobTd = $tds->item(3);
                    $depTd = $tds->item(4);
                    $jobTitle = $jobTd ? $this->cleanRowText((string)$jobTd->textContent) : null;
                    $department = $depTd ? $this->cleanRowText((string)$depTd->textContent) : null;
                }

                // Fallback heuristics.
                if ($jobTitle === null) {
                    $jobNode = $xpath->query('.//span[contains(@class,"text-slate-700")]', $tr);
                    if ($jobNode !== false && $jobNode->length > 0) {
                        $node0 = $jobNode->item(0);
                        $jobTitle = $node0 ? $this->cleanRowText((string)$node0->textContent) : null;
                    }
                }
                if ($department === null) {
                    $depNode = $xpath->query('.//span[contains(@class,"bg-slate-100")]', $tr);
                    if ($depNode !== false && $depNode->length > 0) {
                        $node0 = $depNode->item(0);
                        $department = $node0 ? $this->cleanRowText((string)$node0->textContent) : null;
                    }
                }

                $data = array_filter([
                    'email' => $email,
                    'phone' => $phone,
                    'job_title' => $jobTitle,
                    'department' => $department,
                ], static fn($v) => $v !== null && $v !== '');

                $keys = $this->nameKeysForMatching($name);
                foreach ($keys as $k) {
                    $bucket[$k][] = $data;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        // Only keep keys that map to exactly one contact row to reduce false positives.
        foreach ($bucket as $k => $list) {
            if (count($list) === 1) {
                $out[$k] = $list[0];
            }
        }

        return $out;
    }

    /** @param array<string,array{email?:string,phone?:string,job_title?:string,department?:string}> $contactsByKey */
    private function findContactForEmployeeRow(array $contactsByKey, string $fullName, array $row): ?array
    {
        $c = $this->findContactForName($contactsByKey, $fullName);
        if ($c !== null) {
            return $c;
        }

        $alt = trim((string)($row['match_name'] ?? ''));
        if ($alt !== '' && $alt !== $fullName) {
            $c = $this->findContactForName($contactsByKey, $alt);
            if ($c !== null) {
                return $c;
            }
        }

        return null;
    }

    /** @param array<string,array{email?:string,phone?:string,job_title?:string,department?:string}> $contactsByKey */
    private function findContactForName(array $contactsByKey, string $name): ?array
    {
        foreach ($this->nameKeysForMatching($name) as $k) {
            if (isset($contactsByKey[$k])) {
                return $contactsByKey[$k];
            }
        }
        return null;
    }

    /** @return array<int,string> */
    private function nameKeysForMatching(string $name): array
    {
        $base = $this->matchNameKey($name);
        if ($base === '') {
            return [];
        }

        $keys = [$base];
        $tokens = preg_split('/\s+/u', $base) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn($t) => $t !== ''));

        $count = count($tokens);
        if ($count >= 2) {
            $keys[] = $tokens[0] . ' ' . $tokens[1];
            $keys[] = $tokens[0] . ' ' . $tokens[$count - 1];
            $keys[] = $tokens[$count - 2] . ' ' . $tokens[$count - 1];
        }
        if ($count >= 3) {
            $keys[] = $tokens[0] . ' ' . $tokens[1] . ' ' . $tokens[2];
        }

        $keys = array_values(array_unique(array_map(static fn($k) => trim((string)$k), $keys)));
        return array_values(array_filter($keys, static fn($k) => $k !== ''));
    }

    private function matchNameKey(string $name): string
    {
        $n = $this->nameKey($name);
        // Remove punctuation/hyphens that commonly vary between sources.
        $n = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $n) ?: $n;
        $n = preg_replace('/\s+/u', ' ', $n) ?: $n;
        return trim($n);
    }

    private function cleanRowText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $t = preg_replace('/\s+/u', ' ', trim($text)) ?: trim($text);
        $t = trim($t, " \t\n\r\0\x0B\xE2\x80\x94-");
        return $t !== '' ? $t : null;
    }

    /** @return array<int,string> */
    private function splitRowCells(string $line): array
    {
        if (str_contains($line, "\t")) {
            return explode("\t", $line);
        }

        if (str_contains($line, ',')) {
            return str_getcsv($line);
        }

        return [$line];
    }

    /** @param array<int,string> $cells @return array<int,string>|null */
    private function buildHeaderMap(array $cells): ?array
    {
        $map = [];
        $recognized = 0;

        foreach ($cells as $i => $c) {
            $key = $this->headerCellToKey($c);
            if ($key !== null) {
                $map[$i] = $key;
                $recognized++;
            }
        }

        // Header if we recognized at least 2 columns and one of them is the name.
        $hasName = in_array('full_name', $map, true);
        if ($recognized >= 2 && $hasName) {
            return $map;
        }

        return null;
    }

    private function headerCellToKey(string $cell): ?string
    {
        $c = trim($cell);
        if ($c === '') return null;

        $norm = mb_strtolower($c);
        $norm = preg_replace('/\s+/u', '', $norm) ?: $norm;

        $aliases = [
            'full_name' => ['fullname', 'full_name', 'name', 'employee', 'الاسم', 'الاسم_الكامل', 'الاسمالكامل', 'اسم', 'اسم_الموظف', 'اسم_كامل'],
            'employee_no' => ['employeeno', 'employee_no', 'empno', 'رقم', 'الرقم', 'الرقمالوظيفي', 'رقم_وظيفي', 'الرقم_الوظيفي'],
            'org_name' => ['org', 'organization', 'orgname', 'org_name', 'المنظمة', 'الشركة', 'المؤسسة'],
            'department' => ['department', 'dep', 'القسم', 'الادارة', 'إدارة', 'ادارة'],
            'job_title' => ['job', 'jobtitle', 'job_title', 'المسمى', 'المسمىالوظيفي', 'الوظيفة', 'المنصب'],
            'email' => ['email', 'mail', 'البريد', 'البريدالالكتروني', 'الايميل', 'إيميل'],
            'phone' => ['phone', 'mobile', 'tel', 'الهاتف', 'الجوال', 'رقم_الجوال'],
        ];

        foreach ($aliases as $k => $vals) {
            foreach ($vals as $a) {
                if ($norm === mb_strtolower((string)$a)) {
                    return $k;
                }
            }
        }
        return null;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) return null;
        $s = trim((string)$value);
        return $s === '' ? null : $s;
    }

    private function cleanEmail(mixed $value): ?string
    {
        $s = $this->cleanText($value);
        if ($s === null) return null;
        $s = mb_strtolower($s);
        if (!filter_var($s, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $s;
    }

    private function cleanPhone(mixed $value): ?string
    {
        $s = $this->cleanText($value);
        if ($s === null) return null;
        $s = preg_replace('/\s+/', '', $s) ?: $s;
        return $s === '' ? null : $s;
    }

    private function nameKey(string $name): string
    {
        $n = trim($name);
        $n = preg_replace('/\s+/u', ' ', $n) ?: $n;
        $n = mb_strtolower($n);
        return $n;
    }

    private function employeeNoExists(PDO $db, string $employeeNo): bool
    {
        try {
            $stmt = $db->prepare('SELECT 1 FROM employees WHERE employee_no = ? LIMIT 1');
            $stmt->execute([$employeeNo]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function generateEmployeeNo(PDO $db): string
    {
        for ($i = 0; $i < 10; $i++) {
            $no = 'IMP' . (new DateTimeImmutable('now'))->format('ymd') . '-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (!$this->employeeNoExists($db, $no)) {
                return $no;
            }
        }
        // last resort
        return 'IMP' . (new DateTimeImmutable('now'))->format('ymdHis') . '-' . bin2hex(random_bytes(2));
    }

    private function getOrCreateOrgId(PDO $db, string $orgName, array &$cache, string $now): ?int
    {
        $orgName = trim($orgName);
        if ($orgName === '') return null;

        $key = mb_strtolower($orgName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if (!$this->hasTable($db, 'organizations')) {
            return null;
        }

        $nameCol = null;
        if ($this->hasColumn($db, 'organizations', 'name')) {
            $nameCol = 'name';
        } elseif ($this->hasColumn($db, 'organizations', 'org_name')) {
            $nameCol = 'org_name';
        } elseif ($this->hasColumn($db, 'organizations', 'title')) {
            $nameCol = 'title';
        }
        if ($nameCol === null) {
            return null;
        }

        try {
            $stmt = $db->prepare('SELECT id FROM organizations WHERE ' . $nameCol . ' = ? LIMIT 1');
            $stmt->execute([$orgName]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                $cache[$key] = $id;
                return $id;
            }

            // Create organization (best-effort)
            $cols = [$nameCol => $orgName];
            if ($this->hasColumn($db, 'organizations', 'is_active')) {
                $cols['is_active'] = 1;
            }
            if ($this->hasColumn($db, 'organizations', 'created_at')) {
                $cols['created_at'] = $now;
            }
            if ($this->hasColumn($db, 'organizations', 'updated_at')) {
                $cols['updated_at'] = $now;
            }

            $colNames = implode(',', array_keys($cols));
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $ins = $db->prepare('INSERT INTO organizations (' . $colNames . ') VALUES (' . $placeholders . ')');
            $ins->execute(array_values($cols));
            $newId = (int)$db->lastInsertId();
            if ($newId > 0) {
                $cache[$key] = $newId;
                return $newId;
            }
        } catch (\Throwable) {
            // If race condition on unique, re-select.
            try {
                $stmt = $db->prepare('SELECT id FROM organizations WHERE ' . $nameCol . ' = ? LIMIT 1');
                $stmt->execute([$orgName]);
                $id = (int)($stmt->fetchColumn() ?: 0);
                if ($id > 0) {
                    $cache[$key] = $id;
                    return $id;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function findExistingEmployee(PDO $db, string $fullName, ?string $email, ?int $orgId): ?array
    {
        try {
            if ($email !== null && $this->hasColumn($db, 'employees', 'email')) {
                $stmt = $db->prepare('SELECT * FROM employees WHERE deleted_at IS NULL AND email = ? LIMIT 1');
                $stmt->execute([$email]);
                $row = $stmt->fetch();
                if ($row) {
                    return $row;
                }
            }

            if ($this->hasColumn($db, 'employees', 'org_id')) {
                $stmt = $db->prepare('SELECT * FROM employees WHERE deleted_at IS NULL AND full_name = ? AND org_id <=> ? LIMIT 1');
                $stmt->execute([$fullName, $orgId]);
                $row = $stmt->fetch();
                if ($row) {
                    return $row;
                }

                // Backward compatibility/backfill:
                // If records were created before org support was enabled, they may have org_id NULL.
                // When importing with a specific org, treat a NULL-org row with same name as a match
                // so we can fill org_id instead of inserting a duplicate.
                if ($orgId !== null) {
                    $stmt2 = $db->prepare('SELECT * FROM employees WHERE deleted_at IS NULL AND full_name = ? AND org_id IS NULL LIMIT 1');
                    $stmt2->execute([$fullName]);
                    $row2 = $stmt2->fetch();
                    return $row2 ?: null;
                }

                return null;
            }

            $stmt = $db->prepare('SELECT * FROM employees WHERE deleted_at IS NULL AND full_name = ? LIMIT 1');
            $stmt->execute([$fullName]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $existing @param array<string,mixed> $incoming */
    private function updateEmployeeMissingFields(PDO $db, array $existing, array $incoming): bool
    {
        $id = (int)($existing['id'] ?? 0);
        if ($id <= 0) return false;

        $cols = [];
        foreach (['email', 'phone', 'job_title', 'department'] as $k) {
            if (!isset($incoming[$k])) {
                continue;
            }
            $newVal = $incoming[$k];
            if ($newVal === null || $newVal === '') {
                continue;
            }

            $oldVal = $existing[$k] ?? null;
            $oldVal = is_string($oldVal) ? trim($oldVal) : $oldVal;
            if ($oldVal === null || $oldVal === '') {
                if ($this->hasColumn($db, 'employees', $k)) {
                    $cols[$k] = $newVal;
                }
            }
        }

        if (isset($incoming['org_id']) && $incoming['org_id'] !== null) {
            $newOrgId = (int)$incoming['org_id'];
            if ($newOrgId > 0 && $this->hasColumn($db, 'employees', 'org_id')) {
                $oldOrgIdRaw = $existing['org_id'] ?? null;
                $oldOrgId = is_numeric($oldOrgIdRaw) ? (int)$oldOrgIdRaw : 0;
                if ($oldOrgId <= 0) {
                    $cols['org_id'] = $newOrgId;
                }
            }
        }

        if (!empty($cols) && isset($incoming['updated_at']) && $this->hasColumn($db, 'employees', 'updated_at')) {
            $cols['updated_at'] = $incoming['updated_at'];
        }

        if (empty($cols)) {
            return false;
        }

        $setPart = implode(',', array_map(static fn($k) => $k . '=?', array_keys($cols)));
        $stmt = $db->prepare('UPDATE employees SET ' . $setPart . ' WHERE id = ? AND deleted_at IS NULL');
        $vals = array_values($cols);
        $vals[] = $id;
        try {
            $stmt->execute($vals);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return \PDOStatement */
    private function prepareInsertEmployees(PDO $db, array $columns): \PDOStatement
    {
        static $cache = [];
        $sig = implode('|', $columns);
        if (isset($cache[$sig])) {
            return $cache[$sig];
        }

        $colNames = implode(',', $columns);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $cache[$sig] = $db->prepare('INSERT INTO employees (' . $colNames . ') VALUES (' . $placeholders . ')');
        return $cache[$sig];
    }


}
