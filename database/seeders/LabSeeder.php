<?php

namespace Database\Seeders;

use App\Enums\DeviceType;
use App\Enums\LabStatus;
use App\Models\Lab;
use Illuminate\Database\Seeder;

class LabSeeder extends Seeder
{
    /**
     * Dua CBT Center Poltekkes Kemenkes Manado. Jumlah PC adalah nilai awal; yang sudah ada di database tidak ditimpa.
     */
    public function run(): void
    {
        $labs = [
            ['LAB-1', 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang', 'Kampus Malalayang', '#04B6A3', 40, 0],
            ['LAB-2', 'CBT Center Poltekkes Kemenkes Manado Kampus Perkamil', 'Kampus Perkamil', '#1BBBCB', 40, 0],
        ];

        foreach ($labs as [$code, $name, $location, $color, $goodPcs, $brokenPcs]) {
            $lab = Lab::firstOrCreate(['code' => $code], [
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
                $lab->deviceCounts()->firstOrCreate(
                    ['device_type' => $type],
                    ['total' => $total, 'good' => $good, 'broken' => $broken],
                );
            }
        }
    }
}
