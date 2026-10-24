<?php

namespace App\Models;

use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penyedia hosting/VPS/domain/cloud (mis. Domainesia, Jagoanhosting, Hostinger).
 */
#[Fillable(['name', 'website', 'panel_url', 'support_contact', 'notes'])]
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    /**
     * @return HasMany<HostedService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(HostedService::class);
    }
}
