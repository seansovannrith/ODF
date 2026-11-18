<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Creates any missing permissions and the default roles.
 * Safe to run many times: existing roles keep the permissions you gave them,
 * except "Admin" which always gets every permission.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::all() as $title) {
            Permission::firstOrCreate(['title' => $title]);
        }
        $ids = Permission::pluck('id', 'title');

        foreach (Permissions::defaultRoles() as $title => $perms) {
            $role = Role::firstOrCreate(['title' => $title]);
            if ($role->wasRecentlyCreated || $title === 'Admin') {
                $role->permissions()->sync($ids->only($perms)->values());
            }
        }
    }
}
