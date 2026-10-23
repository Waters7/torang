<?php

namespace Database\Seeders;

use App\Enums\DepartmentType;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Departemen/unit sesuai SPEC §16 — sesuaikan dengan struktur resmi.
     */
    public function run(): void
    {
        $departments = [
            ['Direktorat', 'Direktorat', DepartmentType::Unit],
            ['Jurusan Keperawatan', 'Keperawatan', DepartmentType::Department],
            ['Jurusan Kebidanan', 'Kebidanan', DepartmentType::Department],
            ['Jurusan Gizi', 'Gizi', DepartmentType::Department],
            ['Jurusan Kesehatan Lingkungan', 'Kesling', DepartmentType::Department],
            ['Jurusan Farmasi', 'Farmasi', DepartmentType::Department],
            ['Jurusan Teknologi Laboratorium Medis', 'TLM', DepartmentType::Department],
            ['Jurusan Kesehatan Gigi', 'Kesgi', DepartmentType::Department],
            ['Perpustakaan', 'Perpustakaan', DepartmentType::Unit],
            ['CBT Center', 'CBT Center', DepartmentType::Unit],
        ];

        foreach ($departments as $order => [$name, $short, $type]) {
            Department::updateOrCreate(
                ['name' => $name],
                ['short_name' => $short, 'type' => $type, 'sort_order' => $order + 1],
            );
        }
    }
}
