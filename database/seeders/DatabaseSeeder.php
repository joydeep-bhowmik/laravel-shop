<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Roles and the admin account always; demo catalog only outside production.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Demo catalog and customers are for local development and tests only.
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
