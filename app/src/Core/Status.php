<?php
declare(strict_types=1);

namespace Zaco\Core;

/**
 * Status Helper Class
 * Centralizes status labels and badge classes across the application
 */
final class Status
{
    // Asset conditions
    private const ASSET_CONDITIONS = [
        'excellent' => ['label_ar' => 'ممتاز', 'label_en' => 'Excellent', 'badge' => 'text-bg-success'],
        'good' => ['label_ar' => 'جيد', 'label_en' => 'Good', 'badge' => 'text-bg-primary'],
        'fair' => ['label_ar' => 'مقبول', 'label_en' => 'Fair', 'badge' => 'text-bg-warning'],
        'poor' => ['label_ar' => 'سيئ', 'label_en' => 'Poor', 'badge' => 'text-bg-danger'],
        'disposed' => ['label_ar' => 'مستبعد', 'label_en' => 'Disposed', 'badge' => 'text-bg-secondary'],
    ];

    // Employee statuses
    private const EMPLOYEE_STATUSES = [
        'active' => ['label_ar' => 'نشط', 'label_en' => 'Active', 'badge' => 'text-bg-success'],
        'suspended' => ['label_ar' => 'موقوف', 'label_en' => 'Suspended', 'badge' => 'text-bg-warning'],
        'resigned' => ['label_ar' => 'مستقيل', 'label_en' => 'Resigned', 'badge' => 'text-bg-secondary'],
        'terminated' => ['label_ar' => 'منتهي', 'label_en' => 'Terminated', 'badge' => 'text-bg-danger'],
    ];

    // Custody statuses
    private const CUSTODY_STATUSES = [
        'active' => ['label_ar' => 'فعّالة', 'label_en' => 'Active', 'badge' => 'text-bg-success'],
        'assigned' => ['label_ar' => 'مُعهد', 'label_en' => 'Assigned', 'badge' => 'text-bg-primary'],
        'returned' => ['label_ar' => 'مُسترجعة', 'label_en' => 'Returned', 'badge' => 'text-bg-secondary'],
        'lost' => ['label_ar' => 'مفقودة', 'label_en' => 'Lost', 'badge' => 'text-bg-danger'],
        'damaged' => ['label_ar' => 'تالفة', 'label_en' => 'Damaged', 'badge' => 'text-bg-warning'],
    ];

    // User roles
    private const USER_ROLES = [
        'admin' => ['label_ar' => 'مدير', 'label_en' => 'Admin', 'badge' => 'text-bg-danger'],
        'manager' => ['label_ar' => 'مشرف', 'label_en' => 'Manager', 'badge' => 'text-bg-warning'],
        'viewer' => ['label_ar' => 'مستعرض', 'label_en' => 'Viewer', 'badge' => 'text-bg-info'],
    ];

    // Active/Inactive status
    private const ACTIVE_STATUS = [
        '1' => ['label_ar' => 'مفعل', 'label_en' => 'Active', 'badge' => 'text-bg-success'],
        '0' => ['label_ar' => 'معطل', 'label_en' => 'Inactive', 'badge' => 'text-bg-secondary'],
    ];

    // Cleaning status
    private const CLEANING_STATUSES = [
        'pending' => ['label_ar' => 'قيد الانتظار', 'label_en' => 'Pending', 'badge' => 'text-bg-warning'],
        'cleaned' => ['label_ar' => 'تم التنظيف', 'label_en' => 'Cleaned', 'badge' => 'text-bg-success'],
        'skipped' => ['label_ar' => 'تم التخطي', 'label_en' => 'Skipped', 'badge' => 'text-bg-secondary'],
    ];

    /**
     * Get asset condition label
     */
    public static function assetCondition(string $condition, string $locale = 'ar'): string
    {
        $key = mb_strtolower(trim($condition));
        $data = self::ASSET_CONDITIONS[$key] ?? null;
        
        if ($data === null) {
            return $condition;
        }
        
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get asset condition badge class
     */
    public static function assetConditionBadge(string $condition): string
    {
        $key = mb_strtolower(trim($condition));
        return self::ASSET_CONDITIONS[$key]['badge'] ?? 'text-bg-light border';
    }

    /**
     * Render asset condition badge HTML
     */
    public static function assetConditionHtml(string $condition, string $locale = 'ar'): string
    {
        $label = Http::e(self::assetCondition($condition, $locale));
        $badge = self::assetConditionBadge($condition);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get employee status label
     */
    public static function employeeStatus(string $status, string $locale = 'ar'): string
    {
        $key = mb_strtolower(trim($status));
        $data = self::EMPLOYEE_STATUSES[$key] ?? null;
        
        if ($data === null) {
            return $status;
        }
        
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get employee status badge class
     */
    public static function employeeStatusBadge(string $status): string
    {
        $key = mb_strtolower(trim($status));
        return self::EMPLOYEE_STATUSES[$key]['badge'] ?? 'text-bg-light border';
    }

    /**
     * Render employee status badge HTML
     */
    public static function employeeStatusHtml(string $status, string $locale = 'ar'): string
    {
        $label = Http::e(self::employeeStatus($status, $locale));
        $badge = self::employeeStatusBadge($status);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get custody status label
     */
    public static function custodyStatus(string $status, string $locale = 'ar'): string
    {
        $key = mb_strtolower(trim($status));
        $data = self::CUSTODY_STATUSES[$key] ?? null;
        
        if ($data === null) {
            return $status;
        }
        
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get custody status badge class
     */
    public static function custodyStatusBadge(string $status): string
    {
        $key = mb_strtolower(trim($status));
        return self::CUSTODY_STATUSES[$key]['badge'] ?? 'text-bg-light border';
    }

    /**
     * Render custody status badge HTML
     */
    public static function custodyStatusHtml(string $status, string $locale = 'ar'): string
    {
        $label = Http::e(self::custodyStatus($status, $locale));
        $badge = self::custodyStatusBadge($status);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get user role label
     */
    public static function userRole(string $role, string $locale = 'ar'): string
    {
        $key = mb_strtolower(trim($role));
        $data = self::USER_ROLES[$key] ?? null;
        
        if ($data === null) {
            return $role;
        }
        
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get user role badge class
     */
    public static function userRoleBadge(string $role): string
    {
        $key = mb_strtolower(trim($role));
        return self::USER_ROLES[$key]['badge'] ?? 'text-bg-light border';
    }

    /**
     * Render user role badge HTML
     */
    public static function userRoleHtml(string $role, string $locale = 'ar'): string
    {
        $label = Http::e(self::userRole($role, $locale));
        $badge = self::userRoleBadge($role);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get active status label
     */
    public static function activeStatus(bool|int|string $isActive, string $locale = 'ar'): string
    {
        $key = $isActive ? '1' : '0';
        $data = self::ACTIVE_STATUS[$key];
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get active status badge class
     */
    public static function activeStatusBadge(bool|int|string $isActive): string
    {
        $key = $isActive ? '1' : '0';
        return self::ACTIVE_STATUS[$key]['badge'];
    }

    /**
     * Render active status badge HTML
     */
    public static function activeStatusHtml(bool|int|string $isActive, string $locale = 'ar'): string
    {
        $label = Http::e(self::activeStatus($isActive, $locale));
        $badge = self::activeStatusBadge($isActive);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get cleaning status label
     */
    public static function cleaningStatus(string $status, string $locale = 'ar'): string
    {
        $key = mb_strtolower(trim($status));
        $data = self::CLEANING_STATUSES[$key] ?? null;
        
        if ($data === null) {
            return $status;
        }
        
        return $locale === 'ar' ? $data['label_ar'] : $data['label_en'];
    }

    /**
     * Get cleaning status badge class
     */
    public static function cleaningStatusBadge(string $status): string
    {
        $key = mb_strtolower(trim($status));
        return self::CLEANING_STATUSES[$key]['badge'] ?? 'text-bg-light border';
    }

    /**
     * Render cleaning status badge HTML
     */
    public static function cleaningStatusHtml(string $status, string $locale = 'ar'): string
    {
        $label = Http::e(self::cleaningStatus($status, $locale));
        $badge = self::cleaningStatusBadge($status);
        return "<span class=\"badge {$badge}\">{$label}</span>";
    }

    /**
     * Get all asset conditions
     * @return array<string,array{label_ar:string,label_en:string,badge:string}>
     */
    public static function allAssetConditions(): array
    {
        return self::ASSET_CONDITIONS;
    }

    /**
     * Get all employee statuses
     * @return array<string,array{label_ar:string,label_en:string,badge:string}>
     */
    public static function allEmployeeStatuses(): array
    {
        return self::EMPLOYEE_STATUSES;
    }

    /**
     * Get all custody statuses
     * @return array<string,array{label_ar:string,label_en:string,badge:string}>
     */
    public static function allCustodyStatuses(): array
    {
        return self::CUSTODY_STATUSES;
    }

    /**
     * Get all user roles
     * @return array<string,array{label_ar:string,label_en:string,badge:string}>
     */
    public static function allUserRoles(): array
    {
        return self::USER_ROLES;
    }
}
