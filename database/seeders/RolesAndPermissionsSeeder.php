<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define roles using firstOrCreate
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $technician = Role::firstOrCreate(['name' => 'technician']);
        $coordinator = Role::firstOrCreate(['name' => 'coordinator']);

        $permissions = [
            'manage_users',
            'create_crops',
            'update_crops',
            'delete_crops',
            'view_reports',
            'create_farmers',
            'update_farmers',
            'delete_farmers',
            'view_farmers',
            'create_associations',
            'update_associations',
            'delete_associations',
            'view_associations',
            'view_crop_planting',
            'manage_crop_planting',
            'view_inspections',
            'create_inspections',
            'update_inspections',
            'delete_inspections',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to admin
        $admin->syncPermissions(Permission::whereNotIn('name', ['manage_crop_planting', 'create_inspections'])->get());

        // Assign specific permissions to technician
        $technician->syncPermissions([
            'create_crops', 'update_crops', 'view_reports',
            'create_farmers', 'update_farmers', 'view_farmers', 'view_associations',
            'view_crop_planting', 'manage_crop_planting', 'create_inspections', 'update_inspections', 'view_inspections'
        ]);

        // Assign specific permissions to coordinator
        $coordinator->syncPermissions([
            'view_reports', 'view_farmers',
            'view_crop_planting',
            'view_inspections'
        ]);

        // Create users for each role
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            ]
        )->assignRole('admin');

        $technicianUser = User::firstOrCreate(
            ['email' => 'technician@example.com'],
            [
            'name' => 'Technician User',
            'password' => Hash::make('password'),
            ]
        )->assignRole('technician');

        $coordinatorUser = User::firstOrCreate(
            ['email' => 'coordinator@example.com'],
            [
            'name' => 'Coordinator User',
            'password' => Hash::make('password'),
            ]
        )->assignRole('coordinator');

        // Add categories for crops
        $categories = ['Rice', 'Corn', 'High Value'];
        foreach ($categories as $categoryName) {
            Category::firstOrCreate(['name' => $categoryName]);
        }
    }
}
