<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Let the Python mailer (#22) read only the user columns it needs. The mailer role itself
     * is created by web/db/init; tests run on SQLite, which has no roles.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('GRANT SELECT (id, name, email, profile_image) ON users TO mailer');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('REVOKE SELECT (id, name, email, profile_image) ON users FROM mailer');
        }
    }
};
