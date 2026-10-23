<?php

namespace Database\Seeders;

use App\Enums\DeviceType;
use App\Enums\LabStatus;
use App\Models\Lab;
use Illuminate\Database\Seeder;

class LabSeeder extends Seeder
{
    /**
     * Lab CBT 1 (40 PC), Lab CBT 2 (40 PC), Lab CBT 3 (30 PC, 4 rusak) — SPEC §16.
     */
    public function run(): void
    {
        $labs = [
            ['LAB-1', 'Lab CBT 1', 'Gedung CBT Center, Lantai 1', '#04B6A3', 40, 0],
            ['LAB-2', 'Lab CBT 2', 'Gedung CBT Center, Lantai 1', '#1BBBCB', 40, 0],
            ['LAB-3', 'Lab CBT 3', 'Gedung CBT Center, Lantai 2', '#42B97C', 26, 4],
        ];

        foreach ($labs as [$code, $name, $location, $color, $goodPcs, $brokenPcs]) {
            $lab = Lab::updateOrCreate(['code' => $code], [
                'name' => $name,
                'location' => $location,
                'capacity' => $goodPcs + $brokenPcs,
                'color' => $color,
                'status' => LabStatus::Active,
            ]);

            $devices = [
                [DeviceType::PcClient, $goodPcs + $brokenPcs, $goodPcs, $brokenPcs],
                [DeviceType::CbtServer, 1, 1, 0],
                [DeviceType::Switch, 2, 2, 0],
                [DeviceType::AccessPoint, 1, 1, 0],
                [DeviceType::Ups, 2, 2, 0],
                [DeviceType::Projector, 1, 1, 0],
            ];

            foreach ($devices as [$type, $total, $good, $broken]) {
                $lab->deviceCounts()->updateOrCreate(
                    ['device_type' => $type],
                    ['total' => $total, 'good' => $good, 'broken' => $broken],
                );
            }
        }
    }
}
