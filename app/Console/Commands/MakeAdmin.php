<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a platform operator.
 *
 * There is deliberately no self-service signup for admins and no seeded
 * default credentials — a well-known default admin account is how platforms
 * get owned on day one. The first operator is created here, on the server, by
 * someone with shell access.
 */
class MakeAdmin extends Command
{
    protected $signature = 'elitesender:make-admin
                            {--name= : Full name}
                            {--email= : Email address}
                            {--role=super_admin : super_admin|admin|support}
                            {--password= : Password (prompted when omitted)}';

    protected $description = 'Create a platform operator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = strtolower(trim($this->option('email') ?: text('Email address', required: true)));

        $role = $this->option('role');

        if (! in_array($role, Admin::ROLES, true)) {
            $role = select('Role', Admin::ROLES, Admin::ROLE_SUPER_ADMIN);
        }

        if (Admin::withTrashed()->where('email', $email)->exists()) {
            $this->components->error("An operator already exists with {$email}.");

            return self::FAILURE;
        }

        // Passing --password leaks the credential into shell history and the
        // process list, so prompting is the default.
        $password = $this->option('password') ?: promptPassword('Password', required: true);

        try {
            validator(
                ['password' => $password],
                ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]],
            )->validate();
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
        ]);

        $this->components->info("Operator created: {$admin->email} ({$admin->role})");
        $this->components->warn('Sign in at '.route('admin.login').' — enable two-factor authentication immediately.');

        return self::SUCCESS;
    }
}
