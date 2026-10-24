<?php

namespace App\Filament\Resources\WifiNetworks\Concerns;

use App\Filament\Resources\WifiNetworks\WifiPasswordActions;
use App\Models\WifiNetwork;
use App\Services\Wifi\WifiPasswordAccess;
use Filament\Actions\Action;

/**
 * Untuk halaman/relation manager yang memakai WifiPasswordActions: aksi modal password dan pencatatan "Salin".
 */
trait ShowsWifiPasswords
{
    public function showWifiPasswordAction(): Action
    {
        return WifiPasswordActions::show();
    }

    /**
     * Dipanggil tombol "Salin" di modal password (penyalinan ke clipboard terjadi di browser).
     */
    public function copyWifiPassword(int $network, ?int $history = null): void
    {
        $record = WifiNetwork::findOrFail($network);
        $access = app(WifiPasswordAccess::class);

        abort_unless(auth()->user()?->can('revealPassword', $record) && $access->isConfirmed(), 403);

        $access->logCopied($record, WifiPasswordActions::history($record, ['history' => $history]));
    }
}
