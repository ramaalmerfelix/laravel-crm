<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Resolve Admin (owner/context)
        $admin = DB::table('users')->where('email', 'admin@example.com')->first();
        $adminId = $admin->id ?? 1;

        // Define a conservative permission set (menus + basic actions)
        // Adjust this list to tighten/loosen access as needed.
        $permissions = [
            // Dashboard
            'dashboard',

            // Leads
            'leads',
            'leads.create',
            'leads.view',
            'leads.edit',

            // Quotes (no delete, allow print)
            'quotes',
            'quotes.create',
            'quotes.edit',
            'quotes.print',

            // Contacts list view only
            'contacts',
            'contacts.persons',
            'contacts.persons.view',
            'contacts.persons.create',
            'contacts.persons.edit',
            'contacts.persons.delete',
            'contacts.organizations',
            'contacts.organizations.view',
            'contacts.organizations.create',
            'contacts.organizations.edit',
            'contacts.organizations.delete',

            // Products (CRUD)
            'products',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            // Analytics (Apriori UI)
            'analytics',
            'analytics.market_basket',
            'analytics.market_basket.view',

            // No Settings / Configuration keys included intentionally
        ];

        // Upsert role "Demo (Restricted)"
        $roleName = 'Demo (Restricted)';

        $existingRole = DB::table('roles')->where('name', $roleName)->first();

        if ($existingRole) {
            DB::table('roles')->where('id', $existingRole->id)->update([
                'description'     => 'Demo role with limited menu access',
                'permission_type' => 'custom',
                'permissions'     => json_encode($permissions),
                'updated_at'      => $now,
            ]);

            $roleId = $existingRole->id;
        } else {
            $roleId = DB::table('roles')->insertGetId([
                'name'            => $roleName,
                'description'     => 'Demo role with limited menu access',
                'permission_type' => 'custom',
                'permissions'     => json_encode($permissions),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // Create or update demo user (credentials configurable via env)
        $demoEmail = env('DEMO_USER_EMAIL', 'demo@example.com');
        $demoPass  = env('DEMO_USER_PASSWORD', 'demo123');
        $demoName  = env('DEMO_USER_NAME', 'Demo User');

        $existingUser = DB::table('users')->where('email', $demoEmail)->first();

        if ($existingUser) {
            DB::table('users')->where('id', $existingUser->id)->update([
                'name'            => $demoName,
                'password'        => $demoPass ? Hash::make($demoPass) : $existingUser->password,
                'role_id'         => $roleId,
                'status'          => 1,
                'view_permission' => 'global', // keep simple for demo dataset visibility
                'updated_at'      => $now,
            ]);
        } else {
            DB::table('users')->insert([
                'name'            => $demoName,
                'email'           => $demoEmail,
                'password'        => Hash::make($demoPass),
                'status'          => 1,
                'role_id'         => $roleId,
                'view_permission' => 'global',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }
}
