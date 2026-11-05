<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsSchema;
use Illuminate\Database\Seeder;

/** Writes default values for settings that don't exist yet. Never overwrites saved values. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsSchema::fields() as $key => $field) {
            Setting::query()->firstOrCreate(
                ['branch_id' => null, 'key' => $key],
                ['value' => $field['default']],
            );
        }
    }
}
