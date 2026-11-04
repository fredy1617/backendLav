<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CrearAdministrador extends Command
{
    protected $signature = 'app:crear-administrador';

    protected $description = 'Crear el primer administrador del sistema';

    public function handle()
    {
        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo electronico');
        $password = $this->secret('Contraseña');

        if (User::where('email', $email)->exists()) {
            $this->error('Ese correo ya está registrado.');
            return Command::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'administrador',
            'active' => true,
        ]);

        $this->info('Administrador creado correctamente.');

        return Command::SUCCESS;
    }
}