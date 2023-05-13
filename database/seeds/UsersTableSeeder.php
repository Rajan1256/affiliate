<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([

            [
                'first_name' => 'Royal',
                'last_name'=>'Company',
                'email' => 'royal@gmail.com',
                'password' => password_hash('123456', PASSWORD_BCRYPT),
                'user_type'=>1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

            [
                'first_name' => 'Rajan',
                'last_name'=>'Ghariya',
                'email' => 'rajanghariya@gmail.com',
                'password' => password_hash('123456', PASSWORD_BCRYPT),
                'user_type'=>2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],

        ]);

    }
}
