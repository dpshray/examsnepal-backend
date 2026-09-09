<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Previously a raw DB::table('users')->insert() referencing columns that
     * no longer exist on this table (role, created_by, email_verified_at,
     * created_at/updated_at - the users table has $timestamps = false and
     * uses role_id + added_by + created_date instead), so this seeder threw
     * a "column not found" error on every run. Rewritten against the User
     * model, which matches the current schema, and made safe to re-run:
     * firstOrCreate (keyed by email) rather than a plain insert, so a second
     * run doesn't throw a duplicate-entry error - and unlike updateOrCreate,
     * it only sets attributes (including the password) when actually
     * creating the row, so it won't silently reset an existing admin's
     * password back to the seeder default on every reseed.
     */
    public function run(): void
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');

        if (! $adminRoleId) {
            $this->command?->error('No "admin" role found - cannot seed the admin user.');
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@examsnepal.com'],
            [
                'username' => 'admin',
                'password' => 'Nepal123', // hashed automatically by the User model's 'password' => 'hashed' cast
                'fullname' => 'Administrator',
                'role_id' => $adminRoleId,
                'created_date' => now(),
            ],
        );

        $this->command?->info(
            $admin->wasRecentlyCreated
                ? 'Seeded admin user (admin@examsnepal.com).'
                : 'Admin user (admin@examsnepal.com) already exists - left untouched.',
        );
    }
}
