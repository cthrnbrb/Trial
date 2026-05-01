<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin account (only if not exists)
        User::firstOrCreate(
            ['email' => 'romarybanez2005@gmail.com'],
            [
                'id' => (string) Str::uuid(),
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'first_name' => 'Romar',
                'middle_name' => 'Avelino',
                'last_name' => 'Ybanez',
                'contact_number' => '09381395140',
                'address' => 'Sample Address',
            ]
        );

        

    }
}
