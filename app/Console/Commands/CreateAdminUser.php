<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un utilisateur administrateur à partir des variables d\'environnement.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->info('Variables ADMIN_EMAIL ou ADMIN_PASSWORD manquantes. Saut de l\'étape.');

            return;
        }

        if (\App\Models\User::count() === 0) {
            \App\Models\User::create([
                'name' => 'Admin',
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->info("Utilisateur admin créé avec succès : {$email}");
        } else {
            $this->info('Des utilisateurs existent déjà en base de données.');
        }
    }
}
