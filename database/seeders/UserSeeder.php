<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const ROOT_ADMIN_EMAIL = 'pcshslaboratories@gmail.com';

    private const LEGACY_ROOT_ADMIN_EMAIL = 'root.admin@sample.com';

    // @function run: Pinapatakbo ang User Seeder task.
    // @useIn run: php artisan db:seed
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legacyRootAdmin = User::withTrashed()
            ->where('email', self::LEGACY_ROOT_ADMIN_EMAIL)
            ->first();
        $rootAdmin = User::withTrashed()
            ->where('email', self::ROOT_ADMIN_EMAIL)
            ->first() ?? $legacyRootAdmin ?? new User;

        $rootAdmin->forceFill([
            'name' => 'Root Admin',
            'email' => self::ROOT_ADMIN_EMAIL,
            'password' => Hash::make('sample'),
            'role' => 'admin',
            'is_root_admin' => true,
            'deleted_at' => null,
        ])->save();

        if ($legacyRootAdmin && ! $legacyRootAdmin->is($rootAdmin)) {
            $legacyRootAdmin->forceFill(['is_root_admin' => false])->save();
        }

        $users = [
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin3',
                'email' => 'jeromebernante@gmail.com',
                'password' => Hash::make('1234'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin2',
                'email' => 'vallecera@gmail.com',
                'password' => Hash::make('sample'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_root_admin' => false,
            ],
            [
                'name' => 'Sample Instructor',
                'email' => 'instructor@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'instructor',
                'rfid_tag' => 'RFID-INSTRUCTOR-SAMPLE',
            ],
            [
                'name' => 'Clinic Staff',
                'email' => 'clinic@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
            ],
            [
                'name' => 'Clinic Responder',
                'email' => 'clinic.responder@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
            ],
            [
                'name' => 'Registrar Staff',
                'email' => 'registrar@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'registrar',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user,
            );
        }
    }
}
