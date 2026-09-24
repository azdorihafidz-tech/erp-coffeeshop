<?php

namespace Database\Seeders;

use App\Models\RoastingProfile;
use Illuminate\Database\Seeder;

class RoastingProfileSeeder extends Seeder
{
    public function run(): void
    {
        RoastingProfile::seedDefault();
        $this->command?->info('RoastingProfileSeeder: 3 profile default (Light/Medium/Dark) di-seed (idempotent).');
    }
}
