<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'users:make-admin {email : Email of an existing registered user}';

    protected $description = 'Grant admin access to an existing customer account';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account exists for that email. Register the account first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info('Admin access granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
