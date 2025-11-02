<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as KrayinDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(KrayinDatabaseSeeder::class);

        // Seed Organizations (from provided dataset) then Persons (PIC per org)
        $this->call(OrganizationsTableSeeder::class);
        $this->call(PersonsFromOrganizationsSeeder::class);

        // Seed Warehouses (Krayin core schema)
        $this->call(WarehousesTableSeeder::class);
        $this->call(WarehouseLocationsTableSeeder::class);

        // Seed Products
        $this->call(ProductsTableSeeder::class);

        // Seed initial Product Inventories for Gudang Bandung / Lantai 1
        $this->call(ProductInventoriesTableSeeder::class);

        // Seed demo Leads & Quotes dataset (300 pairs)
        $this->call(LeadQuoteDemoSeeder::class);

        // Seed application branding (admin logo from repo asset)
        $this->call(AppLogoSeeder::class);

        // Seed footer (configuration text under general.settings.footer.label)
        $this->call(AppFooterSeeder::class);

        // Seed UI tweaks (remove logo+version row from admin profile dropdown)
        $this->call(AppUiTweaksSeeder::class);

        // Seed demo user & restricted role
        $this->call(DemoUserSeeder::class);
    }
}
