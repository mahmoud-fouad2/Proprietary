<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use PDO;
use Zaco\Core\CleaningWeeklyReport;
use Zaco\Core\View;
use Zaco\Security\Auth;

final class DashboardController extends BaseController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function index(): void
    {
        $u = $this->auth->user();

        $metrics = [
            'users' => 0,
            'assets' => 0,
            'assetsValue' => 0.0,
            'employees' => 0,
            'custodyActive' => 0,
            'software' => 0,
            'cleaningTodayDone' => 0,
            'cleaningPlaces' => 0,
        ];
        $audits = [];
        $charts = [
            'assetsByCondition' => [],
            'custodyByStatus' => [],
            'cleaningLast7Days' => [],
        ];

        if ($u) {
            $db = $this->db();

            // Weekly cleaning email (auto on Mondays for admins). Non-blocking.
            try {
                if ($this->auth->can('manage_cleaning_places')) {
                    CleaningWeeklyReport::maybeAutoSend($db);
                }
            } catch (\Throwable) {
                // ignore
            }

            $metrics['users'] = (int)$db->query('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL')->fetch()['c'];
            $metrics['assets'] = (int)$db->query('SELECT COUNT(*) AS c FROM assets WHERE deleted_at IS NULL')->fetch()['c'];
            $metrics['assetsValue'] = (float)($db->query('SELECT COALESCE(SUM(cost * quantity), 0) AS v FROM assets WHERE deleted_at IS NULL')->fetch()['v'] ?? 0);
            $metrics['employees'] = (int)$db->query('SELECT COUNT(*) AS c FROM employees WHERE deleted_at IS NULL')->fetch()['c'];
            $metrics['custodyActive'] = (int)$db->query("SELECT COUNT(*) AS c FROM custody WHERE deleted_at IS NULL AND custody_status = 'active'")->fetch()['c'];
            $metrics['software'] = (int)$db->query('SELECT COUNT(*) AS c FROM software_library WHERE deleted_at IS NULL')->fetch()['c'];

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');
            $stmt = $db->prepare('SELECT COUNT(*) AS c FROM cleaning_checks WHERE check_date = ?');
            $stmt->execute([$today]);
            $metrics['cleaningTodayDone'] = (int)$stmt->fetch()['c'];
            $metrics['cleaningPlaces'] = (int)$db->query('SELECT COUNT(*) AS c FROM cleaning_places')->fetch()['c'];

            // Recent audits (last 12)
            $a = $db->query('SELECT id, actor_name, action, table_name, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 12');
            $audits = $a->fetchAll();

            // Charts: assets by condition
            try {
                $stmt = $db->query("SELECT asset_condition AS k, COUNT(*) AS c FROM assets WHERE deleted_at IS NULL GROUP BY asset_condition ORDER BY c DESC");
                $charts['assetsByCondition'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $charts['assetsByCondition'] = [];
            }

            // Charts: custody by status
            try {
                $stmt = $db->query("SELECT custody_status AS k, COUNT(*) AS c FROM custody WHERE deleted_at IS NULL GROUP BY custody_status ORDER BY c DESC");
                $charts['custodyByStatus'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $charts['custodyByStatus'] = [];
            }

            // Charts: cleaning checks trend (last 7 days)
            try {
                $stmt = $db->query("SELECT check_date AS d, COUNT(*) AS c FROM cleaning_checks WHERE check_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY check_date ORDER BY check_date ASC");
                $charts['cleaningLast7Days'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $charts['cleaningLast7Days'] = [];
            }
        }

        View::render('dashboard/index', [
            'user' => $u,
            'metrics' => $metrics,
            'audits' => $audits,
            'charts' => $charts,
            'canSeeAudits' => $this->auth->can('manage_users') || $this->auth->can('settings'),
        ]);
    }


}

