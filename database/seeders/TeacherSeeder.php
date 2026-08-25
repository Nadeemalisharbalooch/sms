<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?int $instituteId = null, int $count = 50): void
    {
        $institutes = $instituteId !== null
            ? Institute::where('id', $instituteId)->get()
            : Institute::all();

        if ($institutes->isEmpty()) {
            $this->command?->warn('No institutes found. Please create an institute first.');
            return;
        }

        $names = [
            'Sir Tariq Mahmood', 'Sir Kamran Akmal', 'Sir Zahid Ali', 'Sir Bilal Ahmed',
            'Sir Imran Khan', 'Sir Rashid Minhas', 'Sir Adnan Sami', 'Sir Salman Butt',
            'Sir Farhan Saeed', 'Sir Naveed Ashraf', 'Sir Khurram Manzoor', 'Sir Waqas Younis',
            'Sir Asif Ali', 'Sir Usman Shinwari', 'Sir Hamza Tariq', 'Sir Saad Baig',
            'Sir Ali Zafar', 'Sir Ahmed Shehzad', 'Sir Hassan Ali', 'Sir Hussain Talat',
            'Sir Zubair Khan', 'Sir Shahid Afridi', 'Sir Babar Azam', 'Sir Mohammad Rizwan',
            'Sir Shoaib Akhtar', 'Sir Faisal Iqbal', 'Sir Mansoor Akhtar', 'Sir Junaid Khan',
            'Sir Yasir Shah', 'Sir Adeel Malik', 'Ms. Ayesha Siddiqa', 'Ms. Fatima Sana',
            'Ms. Zainab Abbas', 'Ms. Maryam Nawaz', 'Ms. Sana Mir', 'Ms. Hira Mani',
            'Ms. Sadia Khan', 'Ms. Noreen Aslam', 'Ms. Samina Baig', 'Ms. Rabia Anum',
            'Ms. Uzma Bukhari', 'Ms. Farah Khan', 'Ms. Bushra Ansari', 'Ms. Amna Ilyas',
            'Ms. Kiran Haq', 'Ms. Nida Yasir', 'Ms. Mehwish Hayat', 'Ms. Tahira Syed',
            'Ms. Shaista Lodhi', 'Ms. Shazia Manzoor',
        ];

        $defaultPassword = Hash::make('password');

        foreach ($institutes as $institute) {
            $this->command?->info("Seeding {$count} teachers for Institute [ID: {$institute->id} - {$institute->name}]...");

            // Ensure Teacher roles exist for this institute
            $roleCapital = Role::firstOrCreate([
                'name' => 'Teacher',
                'institute_id' => $institute->id,
                'guard_name' => 'web',
            ]);

            $roleLower = Role::firstOrCreate([
                'name' => 'teacher',
                'institute_id' => $institute->id,
                'guard_name' => 'web',
            ]);

            for ($i = 1; $i <= $count; $i++) {
                $name = $names[($i - 1) % count($names)] . ($i > count($names) ? " {$i}" : '');
                $email = "teacher{$i}.inst{$institute->id}@sms.local";

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => $defaultPassword,
                        'email_verified_at' => now(),
                        'phone' => '+9230' . str_pad((string)$i, 8, '0', STR_PAD_LEFT),
                        'is_active' => true,
                        'is_admin' => false,
                        'is_institute' => false,
                    ]
                );

                // Associate user to institute
                InstituteUser::updateOrCreate(
                    [
                        'institute_id' => $institute->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'is_active' => true,
                        'is_owner' => false,
                    ]
                );

                // Assign Teacher roles
                $user->roles()->syncWithoutDetaching([$roleCapital->id, $roleLower->id]);
            }

            $this->command?->info("Successfully created/updated {$count} teachers for Institute [ID: {$institute->id}].");
        }
    }
}
