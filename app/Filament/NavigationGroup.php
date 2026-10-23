<?php

namespace App\Filament;

use Filament\Support\Contracts\HasLabel;

/**
 * Grup navigasi panel admin (SPEC §10), urutan case = urutan di sidebar.
 */
enum NavigationGroup implements HasLabel
{
    case LabCbt;
    case Staff;
    case Monitoring;
    case Infrastructure;
    case Services;
    case System;

    public function getLabel(): string
    {
        return match ($this) {
            self::LabCbt => 'Lab CBT',
            self::Staff => 'Petugas',
            self::Monitoring => 'Monitoring',
            self::Infrastructure => 'Infrastruktur',
            self::Services => 'Layanan',
            self::System => 'Sistem',
        };
    }
}
