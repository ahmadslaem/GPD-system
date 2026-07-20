<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $permissions = [

            'manage users',
            'manage camps',
            'manage families',

            'view dashboard',

            'local search',
            'global search',

            'manage vulnerability',

            'approve transfer',

            'export reports',

        ];



        foreach($permissions as $permission)
        {
            Permission::firstOrCreate([
                'name'=>$permission
            ]);
        }



        $admin = Role::firstOrCreate([
            'name'=>'admin'
        ]);


        $manager = Role::firstOrCreate([
            'name'=>'manager'
        ]);


        $dataEntry = Role::firstOrCreate([
            'name'=>'data_entry'
        ]);



        $admin->givePermissionTo(
            Permission::all()
        );



        $manager->givePermissionTo([

            'view dashboard',
            'global search',
            'approve transfer',
            'export reports',
            'manage families'

        ]);



        $dataEntry->givePermissionTo([

            'manage families',
            'local search',
            'manage vulnerability'

        ]);

    }
}
