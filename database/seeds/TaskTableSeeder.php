<?php

use Illuminate\Database\Seeder;

class TaskTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = \Faker\Factory::create();

        for($i=0; $i<=5; $i++):
            DB::table('tasks')
                ->insert([
                    'name' => $faker->sentence(4),
                    'user_id' => 1,
                    'location' => $faker->address,
                    'details' => $faker->text,
                    'start_on' => $faker->dateTime,
                    'end_on' => $faker->dateTime,
                    'created_at' => $faker->dateTime('now'),
                    'updated_at' => $faker->dateTime('now')
                ]);
        endfor;
    }
}
