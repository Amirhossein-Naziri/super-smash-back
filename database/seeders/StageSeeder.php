<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stage;
use App\Models\Story;

class StageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample stages
        $stages = [
            [
                'stage_number' => 1,
                'points' => 10,
                'stories' => [
                    [
                        'title' => 'ماریو و لوییجی',
                        'description' => 'ماریو و لوییجی برادران معروف بازی هستند که در ماجراجویی‌های مختلف شرکت می‌کنند.',
                        'image_path' => 'stories/sample/mario_luigi.jpg',
                        'is_correct' => true,
                        'order' => 1
                    ],
                    [
                        'title' => 'پیکاچو و چارمندر',
                        'description' => 'پیکاچو و چارمندر دو پوکمون محبوب هستند که در جنگ‌های پوکمون شرکت می‌کنند.',
                        'image_path' => 'stories/sample/pikachu_charmander.jpg',
                        'is_correct' => false,
                        'order' => 2
                    ],
                    [
                        'title' => 'لینک و زلدا',
                        'description' => 'لینک و زلدا شخصیت‌های اصلی سری بازی‌های Legend of Zelda هستند.',
                        'image_path' => 'stories/sample/link_zelda.jpg',
                        'is_correct' => false,
                        'order' => 3
                    ]
                ]
            ],
            [
                'stage_number' => 2,
                'points' => 15,
                'stories' => [
                    [
                        'title' => 'ساموس آران',
                        'description' => 'ساموس آران یک شکارچی فضایی است که در سیاره‌های مختلف ماجراجویی می‌کند.',
                        'image_path' => 'stories/sample/samus_aran.jpg',
                        'is_correct' => true,
                        'order' => 1
                    ],
                    [
                        'title' => 'کیربی و متا نایت',
                        'description' => 'کیربی و متا نایت دو شخصیت از سری بازی‌های Kirby هستند.',
                        'image_path' => 'stories/sample/kirby_meta_knight.jpg',
                        'is_correct' => false,
                        'order' => 2
                    ],
                    [
                        'title' => 'دونکی کونگ و دیزی کونگ',
                        'description' => 'دونکی کونگ و دیزی کونگ دو گوریل هستند که در جنگل زندگی می‌کنند.',
                        'image_path' => 'stories/sample/donkey_daisy_kong.jpg',
                        'is_correct' => false,
                        'order' => 3
                    ]
                ]
            ]
        ];

        foreach ($stages as $stageData) {
            $stories = $stageData['stories'];
            unset($stageData['stories']);
            
            $stage = Stage::create($stageData);
            
            foreach ($stories as $storyData) {
                Story::create([
                    'stage_id' => $stage->id,
                    'title' => $storyData['title'],
                    'description' => $storyData['description'],
                    'image_path' => $storyData['image_path'],
                    'is_correct' => $storyData['is_correct'],
                    'order' => $storyData['order']
                ]);
            }
        }
    }
} 