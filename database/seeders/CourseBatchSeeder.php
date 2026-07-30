<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Course;

class CourseBatchSeeder extends Seeder
{
    public function run()
    {
        $courses = Course::pluck('id');

        $daysList = [
            ["Mon","Tue","Wed","Thu"],
            ["Mon","Tue","Wed","Thu","Fri"],
            ["Tue","Wed","Thu","Fri","Sat"],
            ["Mon","Wed","Fri"],
            ["Sat","Sun"]
        ];

        $batchTitles = [
            "Morning Batch",
            "Evening Batch",
            "Weekend Batch",
            "Night Batch"
        ];

        foreach ($courses as $courseId) {

            $batchCount = rand(3,4); // 3 or 4 batches per course

            for ($i = 0; $i < $batchCount; $i++) {

                $startDate = Carbon::now()->addDays(rand(1,30));
                $endDate = (clone $startDate)->addDays(rand(30,90));

                $startTime = Carbon::createFromTime(rand(6,18),0);
                $endTime = (clone $startTime)->addHours(rand(1,3));

                DB::table('course_batches')->insert([
                    'course_id' => $courseId,
                    'title' => $batchTitles[array_rand($batchTitles)],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'days' => json_encode($daysList[array_rand($daysList)]),
                    'capacity' => rand(50,500),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}