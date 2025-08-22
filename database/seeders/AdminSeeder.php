<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('users')->insert([
            'username'          => 'admin',
            'password'          => Hash::make('Nepal123'), // Change 'secret' with your preferred password.
            'fullname'          => 'Administrator',
            'role'              => 'admin',
            'created_by'        => null, // or set to an ID if you track creator users.
            'created_date'      => Carbon::now(),
            'user'              => null,
            'image'             => null,
            'about'             => null,
            'email'             => 'admin@examsnepal.com',
            'phone'             => null,
            'location'          => null,
            'facebook'          => null,
            'twitter'           => null,
            'linkedin'          => null,
            'org'               => null,
            'email_verified_at' => Carbon::now(),
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ]);
    }
}
