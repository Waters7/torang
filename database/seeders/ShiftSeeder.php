<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Shift default SPEC §4: Pagi 07:30–12:00, Siang 12:00–16:00.
     */
    public function run(): void
    {
        foreach ([['Pagi', '07:30', '12:00', 1], ['Siang', '12:00', '16:00', 2]] as [$name, $start, $end, $order]) {
            Shift::updateOrCreate(['name' => $name], [
                'start_time' => $start,
                'end_time' => $end,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
