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
            Permission::create([
                'name'=>$permission
            ]);
        }



        $admin = Role::create([
            'name'=>'admin'
        ]);


        $manager = Role::create([
            'name'=>'manager'
        ]);


        $dataEntry = Role::create([
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
