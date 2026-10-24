<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Password WiFi lama (terenkripsi) yang diganti pada `changed_at`.
 */
#[Fillable(['wifi_network_id', 'password', 'changed_by', 'changed_at'])]
#[Hidden(['password'])]
class WifiPasswordHistory extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WifiNetwork, $this>
     */
    public function network(): BelongsTo
    {
        return $this->belongsTo(WifiNetwork::class, 'wifi_network_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
