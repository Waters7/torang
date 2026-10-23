<?php

namespace App\Enums;

enum Permission: string
{
    // Sistem
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';
    case AuditView = 'audit.view';
    case BackupsManage = 'backups.manage';
    case DepartmentsManage = 'departments.manage';
    case EmployeesManage = 'employees.manage';

    // Lab CBT & pengajuan
    case LabsView = 'labs.view';
    case LabsManage = 'labs.manage';
    case BookingsView = 'bookings.view';
    case BookingsManage = 'bookings.manage';
    case BookingsApprove = 'bookings.approve';
    case UsageLogsManage = 'usage-logs.manage';

    // Jadwal petugas
    case DutyViewOwn = 'duty.view-own';
    case DutyManage = 'duty.manage';
    case DutyPublish = 'duty.publish';
    case StaffManage = 'staff.manage';

    // Monitoring
    case MonitorsView = 'monitors.view';
    case MonitorsManage = 'monitors.manage';
    case MonitoringSettings = 'monitoring.settings';

    // WiFi
    case WifiView = 'wifi.view';
    case WifiRevealPassword = 'wifi.reveal-password';
    case WifiManage = 'wifi.manage';

    // Hosting, VPS, domain
    case HostingView = 'hosting.view';
    case HostingManage = 'hosting.manage';

    // Layanan
    case TicketsView = 'tickets.view';
    case TicketsManage = 'tickets.manage';
    case AnnouncementsManage = 'announcements.manage';

    // Laporan
    case ReportsView = 'reports.view';
    case ReportsUkomManage = 'reports.ukom.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
