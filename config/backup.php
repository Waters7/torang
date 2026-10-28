<?php

// Pertahankan struktur konfigurasi paket yang terpasang; sesuaikan penyimpanan TORANG.
$config = require base_path('vendor/spatie/laravel-backup/config/backup.php');
$config['backup']['name'] = 'TORANG-Database';
$config['backup']['source']['files']['include'] = [];
$config['backup']['source']['databases'] = [env('DB_CONNECTION', 'mysql')];
$config['backup']['destination']['disks'] = ['backups'];
$config['backup']['verify_backup'] = true;
$config['notifications']['notifications'] = [];
$config['monitor_backups'] = [];
$config['cleanup']['default_strategy'] = [
    'keep_all_backups_for_days' => 14,
    'keep_daily_backups_for_days' => 0,
    'keep_weekly_backups_for_weeks' => 0,
    'keep_monthly_backups_for_months' => 0,
    'keep_yearly_backups_for_years' => 0,
    'delete_oldest_backups_when_using_more_megabytes_than' => null,
];

return $config;
