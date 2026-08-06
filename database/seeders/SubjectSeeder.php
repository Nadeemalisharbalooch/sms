<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed common subjects for every institute.
     */
    public function run(): void
    {
        $institutes = Institute::all();

        if ($institutes->isEmpty()) {
            $this->command->warn('No institutes found. Skipping SubjectSeeder.');

            return;
        }

       $subjects = [
    ['name' => 'English', 'code' => 'ENG', 'description' => 'English language and literature'],
    ['name' => 'Urdu', 'code' => 'URD', 'description' => 'Urdu language and literature'],
    ['name' => 'Sindhi', 'code' => 'SND', 'description' => 'Sindhi language'],
    ['name' => 'Punjabi', 'code' => 'PNJ', 'description' => 'Punjabi language'],
    ['name' => 'Pashto', 'code' => 'PAS', 'description' => 'Pashto language'],
    ['name' => 'Balochi', 'code' => 'BAL', 'description' => 'Balochi language'],
    ['name' => 'Arabic', 'code' => 'ARB', 'description' => 'Arabic language'],
    ['name' => 'Persian', 'code' => 'PER', 'description' => 'Persian language'],

    ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Mathematics'],
    ['name' => 'General Science', 'code' => 'GS', 'description' => 'General Science'],
    ['name' => 'Physics', 'code' => 'PHY', 'description' => 'Physics'],
    ['name' => 'Chemistry', 'code' => 'CHE', 'description' => 'Chemistry'],
    ['name' => 'Biology', 'code' => 'BIO', 'description' => 'Biology'],
    ['name' => 'Computer', 'code' => 'COMP', 'description' => 'Computer Studies'],
    ['name' => 'Computer Science', 'code' => 'CS', 'description' => 'Computer Science'],
    ['name' => 'Statistics', 'code' => 'STAT', 'description' => 'Statistics'],

    ['name' => 'Islamiyat', 'code' => 'ISL', 'description' => 'Islamic Studies'],
    ['name' => 'Ethics', 'code' => 'ETH', 'description' => 'Ethics'],
    ['name' => 'Pakistan Studies', 'code' => 'PST', 'description' => 'Pakistan Studies'],
    ['name' => 'Social Studies', 'code' => 'SST', 'description' => 'Social Studies'],
    ['name' => 'General Knowledge', 'code' => 'GK', 'description' => 'General Knowledge'],

    ['name' => 'Accounting', 'code' => 'ACC', 'description' => 'Accounting'],
    ['name' => 'Commerce', 'code' => 'COM', 'description' => 'Commerce'],
    ['name' => 'Economics', 'code' => 'ECO', 'description' => 'Economics'],
    ['name' => 'Business Mathematics', 'code' => 'BM', 'description' => 'Business Mathematics'],
    ['name' => 'Business Statistics', 'code' => 'BST', 'description' => 'Business Statistics'],
    ['name' => 'Principles of Accounting', 'code' => 'POA', 'description' => 'Principles of Accounting'],
    ['name' => 'Principles of Commerce', 'code' => 'POC', 'description' => 'Principles of Commerce'],

    ['name' => 'Civics', 'code' => 'CIV', 'description' => 'Civics'],
    ['name' => 'Education', 'code' => 'EDU', 'description' => 'Education'],
    ['name' => 'Psychology', 'code' => 'PSY', 'description' => 'Psychology'],
    ['name' => 'Sociology', 'code' => 'SOC', 'description' => 'Sociology'],
    ['name' => 'Home Economics', 'code' => 'HE', 'description' => 'Home Economics'],

    ['name' => 'Art', 'code' => 'ART', 'description' => 'Art'],
    ['name' => 'Drawing', 'code' => 'DRW', 'description' => 'Drawing'],
    ['name' => 'Physical Education', 'code' => 'PE', 'description' => 'Physical Education'],
];

        foreach ($institutes as $institute) {
            foreach ($subjects as $subject) {
                Subject::updateOrCreate(
                    [
                        'institute_id' => $institute->id,
                        'code' => $subject['code'],
                    ],
                    [
                        'name' => $subject['name'],
                        'description' => $subject['description'],
                        'is_active' => true,
                    ]
                );
            }

            $this->command->info("Seeded ".count($subjects)." subjects for institute: {$institute->name}");
        }
    }
}