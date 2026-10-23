<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HeadOfUnit = 'kepala_unit';
    case ItStaff = 'petugas_it';
    case CbtManager = 'pengelola_cbt';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::HeadOfUnit => 'Kepala Unit',
            self::ItStaff => 'Petugas IT',
            self::CbtManager => 'Pengelola CBT',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::HeadOfUnit => 'primary',
            self::ItStaff => 'info',
            self::CbtManager => 'success',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Permission yang dimiliki tiap role. Super admin mendapat semua permission.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::values(),
            self::HeadOfUnit => [
                Permission::DepartmentsManage->value,
                Permission::EmployeesManage->value,
                Permission::LabsView->value,
                Permission::BookingsView->value,
                Permission::BookingsManage->value,
                Permission::BookingsApprove->value,
                Permission::DutyViewOwn->value,
                Permission::DutyManage->value,
                Permission::DutyPublish->value,
                Permission::StaffManage->value,
                Permission::MonitorsView->value,
                Permission::WifiView->value,
                Permission::WifiRevealPassword->value,
                Permission::HostingView->value,
                Permission::TicketsView->value,
                Permission::AnnouncementsManage->value,
                Permission::ReportsView->value,
                Permission::ReportsUkomManage->value,
                Permission::AuditView->value,
            ],
            self::ItStaff => [
                Permission::LabsView->value,
                Permission::BookingsView->value,
                Permission::UsageLogsManage->value,
                Permission::DutyViewOwn->value,
                Permission::MonitorsView->value,
                Permission::MonitorsManage->value,
                Permission::WifiView->value,
                Permission::WifiRevealPassword->value,
                Permission::WifiManage->value,
                Permission::HostingView->value,
                Permission::HostingManage->value,
                Permission::TicketsView->value,
                Permission::TicketsManage->value,
            ],
            self::CbtManager => [
                Permission::LabsView->value,
                Permission::LabsManage->value,
                Permission::BookingsView->value,
                Permission::BookingsManage->value,
                Permission::UsageLogsManage->value,
                Permission::DutyViewOwn->value,
                Permission::ReportsView->value,
                Permission::ReportsUkomManage->value,
            ],
        };
    }
}
