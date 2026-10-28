<?php

namespace App\Services\System;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GeneralSettings
{
    public const KEYS = [
        'institution_name' => 'identity.institution_name', 'unit_name' => 'identity.unit_name', 'head_name' => 'identity.head_name', 'head_nip' => 'identity.head_nip',
        'helpdesk_whatsapp' => 'identity.helpdesk_whatsapp', 'helpdesk_email' => 'identity.helpdesk_email', 'service_hours' => 'identity.service_hours',
        'min_days_ahead' => 'bookings.min_days_ahead', 'open_time' => 'bookings.open_time', 'close_time' => 'bookings.close_time',
        'approver_roles' => 'bookings.approver_roles', 'show_sunday' => 'bookings.show_sunday',
        'service_days' => 'reminders.service_days', 'rotation_days' => 'wifi.rotation_days',
        'refresh_seconds' => 'public.refresh_seconds', 'show_history_bars' => 'public.show_history_bars',
        'uptime_good_percent' => 'public.uptime_good_percent', 'uptime_warning_percent' => 'public.uptime_warning_percent',
        'operating_days' => 'reports.cbt.operating_days',
    ];

    public function save(array $input, User $actor): void
    {
        abort_unless($actor->is_active && $actor->can(Permission::SettingsManage->value), 403);
        $data = Validator::make($input, [
            'institution_name' => ['required', 'string', 'max:255'], 'unit_name' => ['required', 'string', 'max:255'],
            'head_name' => ['nullable', 'string', 'max:255'], 'head_nip' => ['nullable', 'regex:/^\d{18}$/'],
            'helpdesk_whatsapp' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'], 'helpdesk_email' => ['nullable', 'email', 'max:255'], 'service_hours' => ['required', 'string', 'max:255'],
            'min_days_ahead' => ['required', 'integer', 'between:0,365'], 'open_time' => ['required', 'date_format:H:i'], 'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'approver_roles' => ['required', 'array', 'min:1'], 'approver_roles.*' => [Rule::in(Role::values())], 'show_sunday' => ['required', 'boolean'],
            'service_days' => ['required', 'array', 'min:1'], 'service_days.*' => ['integer', 'between:0,365', 'distinct'], 'rotation_days' => ['required', 'integer', 'between:1,730'],
            'refresh_seconds' => ['required', 'integer', 'between:15,3600'], 'show_history_bars' => ['required', 'boolean'],
            'uptime_good_percent' => ['required', 'numeric', 'between:0,100'], 'uptime_warning_percent' => ['required', 'numeric', 'between:0,100', 'lt:uptime_good_percent'],
            'operating_days' => ['required', 'array', 'min:1'], 'operating_days.*' => ['integer', 'between:1,7', 'distinct'],
        ])->validate();
        $values = [];
        foreach (self::KEYS as $field => $key) {
            $values[$key] = $data[$field] ?? '';
        }
        foreach (['reminders.service_days', 'reports.cbt.operating_days'] as $key) {
            $values[$key] = array_map('intval', $values[$key]);
        }
        app(SettingsService::class)->setMany($values);
        foreach (['summary', 'labs-now', 'week', 'networks', 'apps', 'status-page'] as $key) {
            Cache::forget('public.'.$key);
        }
        activity('settings')->causedBy($actor)->withProperties(['keys' => array_keys($values)])->log('Pengaturan umum diperbarui');
    }
}
