<?php

namespace Database\Seeders;

use App\Jobs\DescargarActecos;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $user = new User();
        $user->name = "Jesus Moris";
        $user->email = "jesus@soluciontotal.cl";
        $user->password = bcrypt("Moris234");
        $user->certpass = "Moris234";
        $user->is_admin = true;
        $user->save();

        $this->call([
            ComunasSeeder::class,
        ]);

        DescargarActecos::dispatch()->onQueue('sincronizacion');
        // \App\Models\User::factory(10)->create();
    }
}
