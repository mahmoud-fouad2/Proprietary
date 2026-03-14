<?php
declare(strict_types=1);

namespace Zaco\Controllers;

use Zaco\Core\Http;
use Zaco\Core\Notify;
use Zaco\Security\Auth;
use Zaco\Security\Csrf;

/**
 * Notification controller - handles notification API endpoints
 */
final class NotificationController
{
    /**
     * Get unread notifications (AJAX)
     */
    public function unread(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $auth = new Auth();
        $user = $auth->user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        if (!Notify::enabled()) {
            echo json_encode(['notifications' => [], 'count' => 0]);
            return;
        }

        $isAdmin = (int)($user['role'] ?? 0) === 1;
        $notifications = Notify::getUnread((int)$user['id'], $isAdmin, 20);
        $count = Notify::countUnread((int)$user['id'], $isAdmin);

        // Format notifications for frontend
        $formatted = [];
        foreach ($notifications as $n) {
            $formatted[] = [
                'id' => (int)$n['id'],
                'type' => $n['type'],
                'entity_type' => $n['entity_type'],
                'entity_id' => $n['entity_id'],
                'entity_name' => $n['entity_name'],
                'message' => $n['message'],
                'actor_name' => $n['actor_name'],
                'created_at' => $n['created_at'],
                'time_ago' => $this->timeAgo($n['created_at']),
                'icon' => $this->getIcon($n['type']),
                'color' => $this->getColor($n['type']),
                'url' => $this->getUrl($n['entity_type'], $n['entity_id']),
            ];
        }

        echo json_encode([
            'notifications' => $formatted,
            'count' => $count
        ]);
    }

    /**
     * Mark all notifications as read (POST)
     */
    public function markAllRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $auth = new Auth();
        $user = $auth->user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // CSRF validation
        $csrfToken = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        if (!Notify::enabled()) {
            echo json_encode(['success' => true]);
            return;
        }

        $isAdmin = (int)($user['role'] ?? 0) === 1;
        Notify::markAllRead((int)$user['id'], $isAdmin);

        echo json_encode(['success' => true]);
    }

    /**
     * Mark single notification as read (POST)
     */
    public function markRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $auth = new Auth();
        $user = $auth->user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // CSRF validation
        $csrfToken = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification ID']);
            return;
        }

        if (!Notify::enabled()) {
            echo json_encode(['success' => true]);
            return;
        }

        $isAdmin = (int)($user['role'] ?? 0) === 1;
        Notify::markRead($id, (int)$user['id'], $isAdmin);

        echo json_encode(['success' => true]);
    }

    /**
     * Get change history for an entity (admin only)
     */
    public function history(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $auth = new Auth();
        $user = $auth->user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Admin only
        $isAdmin = (int)($user['role'] ?? 0) === 1;
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        if (!Notify::historyEnabled()) {
            echo json_encode(['history' => []]);
            return;
        }

        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int)($_GET['entity_id'] ?? 0);

        if (!$entityType || $entityId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        $history = Notify::getHistory($entityType, $entityId, 50);

        // Format history for frontend
        $formatted = [];
        foreach ($history as $h) {
            $changes = is_string($h['changes']) ? json_decode($h['changes'], true) : $h['changes'];
            $formatted[] = [
                'id' => (int)$h['id'],
                'action' => $h['action'],
                'action_label' => Notify::actionLabel($h['action']),
                'changes' => $changes ?: [],
                'actor_name' => $h['actor_name'],
                'ip' => $h['ip'],
                'created_at' => $h['created_at'],
                'time_ago' => $this->timeAgo($h['created_at']),
            ];
        }

        echo json_encode(['history' => $formatted]);
    }

    /**
     * Calculate time ago string
     */
    private function timeAgo(string $datetime): string
    {
        $now = new \DateTime();
        $past = new \DateTime($datetime);
        $diff = $now->diff($past);

        if ($diff->y > 0) {
            return $diff->y === 1 ? 'منذ سنة' : 'منذ ' . $diff->y . ' سنوات';
        }
        if ($diff->m > 0) {
            return $diff->m === 1 ? 'منذ شهر' : 'منذ ' . $diff->m . ' أشهر';
        }
        if ($diff->d > 0) {
            if ($diff->d === 1) return 'منذ يوم';
            if ($diff->d < 7) return 'منذ ' . $diff->d . ' أيام';
            $weeks = floor($diff->d / 7);
            return $weeks === 1 ? 'منذ أسبوع' : 'منذ ' . $weeks . ' أسابيع';
        }
        if ($diff->h > 0) {
            return $diff->h === 1 ? 'منذ ساعة' : 'منذ ' . $diff->h . ' ساعات';
        }
        if ($diff->i > 0) {
            return $diff->i === 1 ? 'منذ دقيقة' : 'منذ ' . $diff->i . ' دقائق';
        }
        return 'الآن';
    }

    /**
     * Get icon for notification type
     */
    private function getIcon(string $type): string
    {
        return match ($type) {
            'create' => 'bi-plus-circle-fill',
            'update' => 'bi-pencil-fill',
            'delete' => 'bi-trash-fill',
            'restore' => 'bi-arrow-counterclockwise',
            'login' => 'bi-box-arrow-in-right',
            default => 'bi-bell-fill',
        };
    }

    /**
     * Get color class for notification type
     */
    private function getColor(string $type): string
    {
        return match ($type) {
            'create' => 'text-success',
            'update' => 'text-primary',
            'delete' => 'text-danger',
            'restore' => 'text-info',
            'login' => 'text-secondary',
            default => 'text-muted',
        };
    }

    /**
     * Get URL for entity
     */
    private function getUrl(string $entityType, ?int $entityId): ?string
    {
        if (!$entityId) return null;
        
        $base = rtrim(Http::url('/'), '/');
        
        return match ($entityType) {
            'employee' => $base . '/employees/edit/' . $entityId,
            'asset' => $base . '/inventory/edit/' . $entityId,
            'custody' => $base . '/custody/edit/' . $entityId,
            'software' => $base . '/software/edit/' . $entityId,
            'user' => $base . '/users/edit/' . $entityId,
            default => null,
        };
    }
}
