<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The photo reminder (python/, #22) reads users through its own read-only role, reminder,
     * created by web/db/init/02-reminder-role.sh. The mailer no longer needs users at all: every
     * email it sends from the outbox carries its own recipient. Tests run on SQLite, which has
     * no roles.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('REVOKE SELECT (id, name, email, profile_image) ON users FROM mailer');

        // A database from before the role gets it, and this grant, from 02-reminder-role.sh
        if (DB::selectOne("SELECT 1 AS found FROM pg_roles WHERE rolname = 'reminder'") !== null) {
            DB::statement('GRANT SELECT (id, name, email, profile_image, email_verified_at) ON users TO reminder');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::selectOne("SELECT 1 AS found FROM pg_roles WHERE rolname = 'reminder'") !== null) {
            DB::statement('REVOKE SELECT (id, name, email, profile_image, email_verified_at) ON users FROM reminder');
        }
        DB::statement('GRANT SELECT (id, name, email, profile_image) ON users TO mailer');
    }
};
