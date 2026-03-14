<?php
declare(strict_types=1);

namespace Zaco\Core;

/**
 * Flash Message Service
 * Handles session-based flash messages and undo functionality
 */
final class Flash
{
    private const SESSION_KEY = '_flash_messages';
    private const UNDO_KEY = '_flash_undo';

    /**
     * Set a success message
     */
    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    /**
     * Set an error message
     */
    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    /**
     * Set a warning message
     */
    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    /**
     * Set an info message
     */
    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    /**
     * Set a message of a specific type
     */
    public static function set(string $type, string $message): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $_SESSION[self::SESSION_KEY][$type] = $message;
    }

    /**
     * Get and clear a message
     */
    public static function get(string $type): ?string
    {
        $message = $_SESSION[self::SESSION_KEY][$type] ?? null;
        if ($message !== null) {
            unset($_SESSION[self::SESSION_KEY][$type]);
        }
        return $message;
    }

    /**
     * Check if a message exists
     */
    public static function has(string $type): bool
    {
        return isset($_SESSION[self::SESSION_KEY][$type]);
    }

    /**
     * Get all messages and clear them
     * @return array<string,string>
     */
    public static function all(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        $_SESSION[self::SESSION_KEY] = [];
        return $messages;
    }

    /**
     * Set undo data for deleted items
     */
    public static function setUndo(string $action, int $id, array $extraData = []): void
    {
        $_SESSION[self::UNDO_KEY] = [
            'action' => $action,
            'id' => $id,
            'data' => $extraData,
            'timestamp' => time(),
        ];
    }

    /**
     * Get and clear undo data
     * @return array{action:string,id:int,data:array,timestamp:int}|null
     */
    public static function getUndo(): ?array
    {
        $undo = $_SESSION[self::UNDO_KEY] ?? null;
        
        if ($undo === null) {
            return null;
        }

        // Undo expires after 5 minutes
        if (time() - ($undo['timestamp'] ?? 0) > 300) {
            unset($_SESSION[self::UNDO_KEY]);
            return null;
        }

        return $undo;
    }

    /**
     * Clear undo data
     */
    public static function clearUndo(): void
    {
        unset($_SESSION[self::UNDO_KEY]);
    }

    /**
     * Get undo ID if available for a specific action
     */
    public static function getUndoId(string $action): ?int
    {
        $undo = self::getUndo();
        if ($undo !== null && $undo['action'] === $action) {
            return $undo['id'];
        }
        return null;
    }

    /**
     * Render all flash messages as HTML
     */
    public static function render(string $locale = 'ar'): string
    {
        $messages = self::all();
        $undo = self::getUndo();
        
        if (empty($messages) && $undo === null) {
            return '';
        }

        $html = '';
        $alertClasses = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
        ];

        foreach ($messages as $type => $message) {
            $alertClass = $alertClasses[$type] ?? 'alert-secondary';
            $isToast = in_array($type, ['success', 'info'], true);
            $toastAttr = $isToast ? ' data-toast' : '';
            
            $escapedMessage = Http::e($message);
            $html .= "<div class=\"alert {$alertClass}\"{$toastAttr}>{$escapedMessage}";
            
            // Add undo button for delete actions
            if ($type === 'success' && $undo !== null) {
                $undoLabel = $locale === 'ar' ? 'تراجع' : 'Undo';
                $csrfInput = Http::csrfInput();
                $undoAction = match ($undo['action']) {
                    'delete_asset' => '/inventory/undo',
                    'delete_employee' => '/employees/undo',
                    'delete_custody' => '/custody/undo',
                    'delete_software' => '/software/undo',
                    'delete_user' => '/users/undo',
                    default => null,
                };
                
                if ($undoAction !== null) {
                    $undoUrl = Http::e(Http::url($undoAction));
                    $html .= <<<HTML
                        <form method="post" action="{$undoUrl}" class="d-inline ms-2" data-loading>
                            {$csrfInput}
                            <input type="hidden" name="id" value="{$undo['id']}" />
                            <button class="btn btn-outline-secondary btn-sm" type="submit">{$undoLabel}</button>
                        </form>
                    HTML;
                }
            }
            
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Set message based on URL msg parameter (backward compatibility)
     * Call this at the start of index views
     */
    public static function fromUrl(string $module = ''): void
    {
        $msg = $_GET['msg'] ?? null;
        if ($msg === null) {
            return;
        }

        $messages = [
            'created' => [
                'asset' => 'تم إضافة الأصل بنجاح.',
                'employee' => 'تم إضافة الموظف بنجاح.',
                'custody' => 'تم إضافة العهدة بنجاح.',
                'software' => 'تم إضافة البرنامج بنجاح.',
                'user' => 'تم إنشاء المستخدم بنجاح.',
                'default' => 'تم الإنشاء بنجاح.',
            ],
            'updated' => [
                'asset' => 'تم تعديل الأصل بنجاح.',
                'employee' => 'تم تعديل الموظف بنجاح.',
                'custody' => 'تم تعديل العهدة بنجاح.',
                'software' => 'تم تعديل البرنامج بنجاح.',
                'user' => 'تم تعديل المستخدم بنجاح.',
                'default' => 'تم التعديل بنجاح.',
            ],
            'deleted' => [
                'asset' => 'تم حذف الأصل بنجاح.',
                'employee' => 'تم حذف الموظف بنجاح.',
                'custody' => 'تم حذف العهدة بنجاح.',
                'software' => 'تم حذف البرنامج بنجاح.',
                'user' => 'تم حذف المستخدم بنجاح.',
                'default' => 'تم الحذف بنجاح.',
            ],
            'restored' => [
                'asset' => 'تم استرجاع الأصل بنجاح.',
                'employee' => 'تم استرجاع الموظف بنجاح.',
                'custody' => 'تم استرجاع العهدة بنجاح.',
                'software' => 'تم استرجاع البرنامج بنجاح.',
                'user' => 'تم استرجاع المستخدم بنجاح.',
                'default' => 'تم الاسترجاع بنجاح.',
            ],
            'moved' => [
                'default' => 'تم نقل الأصول بنجاح.',
            ],
        ];

        if (isset($messages[$msg])) {
            $text = $messages[$msg][$module] ?? $messages[$msg]['default'];
            self::success($text);
        }
    }
}
