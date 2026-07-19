<?php

namespace Database\Seeders;
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
         User::updateOrCreate(
            ['email' => 'admin@gpd.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('ahmad-123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
