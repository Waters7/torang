<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Untuk notifikasi on-demand: kirim lewat semua kanal yang diberi rute (mail, whatsapp, telegram).
 */
trait ViaOnDemandRoutes
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }
}
