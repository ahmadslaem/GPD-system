<?php

namespace Database\Seeders;
use App\Models\Camp;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminPassword = $this->seedPassword('SEED_ADMIN_PASSWORD', 'ahmad-123');
        $managerPassword = $this->seedPassword('SEED_MANAGER_PASSWORD', 'manager-123');
        $staffPassword = $this->seedPassword('SEED_DATA_PASSWORD', 'data-12345');

        $camps = [
            ['name' => 'مخيم جباليا', 'location' => 'شمال غزة', 'capacity' => 5000],
            ['name' => 'مخيم خان يونس', 'location' => 'خان يونس', 'capacity' => 4200],
            ['name' => 'مواصي رفح', 'location' => 'رفح', 'capacity' => 2500],
            ['name' => 'مخيم النصيرات', 'location' => 'الوسطى', 'capacity' => 3600],
            ['name' => 'مخيم البريج', 'location' => 'الوسطى', 'capacity' => 3000],
        ];

        foreach ($camps as $camp) {
            Camp::firstOrCreate(
                ['name' => $camp['name']],
                $camp + ['current_population' => 0, 'is_active' => true]
            );
        }

        $jabalia = Camp::where('name', 'مخيم جباليا')->first();

        $admin = User::updateOrCreate(
            ['email' => 'admin@gpd.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        $manager = User::updateOrCreate(
            ['email' => 'manager@gpd.com'],
            [
                'name' => 'Organization Manager',
                'password' => Hash::make($managerPassword),
                'role' => 'manager',
                'is_active' => true,
            ]
        );
        $manager->syncRoles(['manager']);

        $staff = User::updateOrCreate(
            ['email' => 'data@gpd.com'],
            [
                'name' => 'Data Entry User',
                'password' => Hash::make($staffPassword),
                'role' => 'data_entry',
                'camp_id' => $jabalia->id,
                'is_active' => true,
            ]
        );
        $staff->syncRoles(['data_entry']);
    }

    private function seedPassword(string $key, string $localDefault): string
    {
        $password = env($key);

        if ($password) {
            return $password;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException("Missing required production seed password: {$key}");
        }

        return $localDefault;
    }
}
