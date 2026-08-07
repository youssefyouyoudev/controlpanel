<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOwnerCommand extends Command
{
    protected $signature = 'youpanel:create-owner';

    protected $description = 'Create the first YouPanel owner account without enabling public registration.';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Owner name'));
        $email = strtolower(trim((string) $this->ask('Owner email')));

        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Owner password');
        $confirm = (string) $this->secret('Confirm owner password');

        $passwordValidator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirm,
        ], [
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Owner,
            'is_active' => true,
            'timezone' => 'Africa/Casablanca',
        ]);

        $this->info('Owner created. The password was not printed or stored in logs.');

        return self::SUCCESS;
    }
}
