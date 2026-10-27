<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Chef = 'chef';
    case Supervisor = 'supervisor';
    case Waiter = 'waiter';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدير النظام',
            self::Admin => 'مدير المطعم',
            self::Cashier => 'كاشير',
            self::Chef => 'شيف / نافذة المطبخ',
            self::Supervisor => 'مشرف تشغيل',
            self::Waiter => 'ويتر / مضيف',
            self::Staff => 'موظف',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isCashier(): bool
    {
        return $this === self::Cashier;
    }

    public function isChef(): bool
    {
        return $this === self::Chef;
    }

    public function isSupervisor(): bool
    {
        return $this === self::Supervisor;
    }

    public function isWaiter(): bool
    {
        return $this === self::Waiter;
    }

    /**
     * الصلاحيات الافتراضية المقترنة بكل دور
     * @return array<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => [
                'manage_orders',
                'manage_menu',
                'manage_tables',
                'manage_users',
                'view_orders',
                'update_order_status',
                'view_menu',
            ],
            self::Cashier => [
                'manage_orders',
                'view_orders',
                'update_order_status',
            ],
            self::Chef => [
                'manage_orders',
                'view_orders',
                'update_order_status',
            ],
            self::Supervisor => [
                'manage_orders',
                'manage_tables',
                'manage_menu',
                'view_orders',
                'update_order_status',
                'view_menu',
            ],
            self::Waiter => [
                'manage_orders',
                'manage_tables',
                'view_orders',
            ],
            self::Staff => [
                'manage_orders',
            ],
        };
    }
}
