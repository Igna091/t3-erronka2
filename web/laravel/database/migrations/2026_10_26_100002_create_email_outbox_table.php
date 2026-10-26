<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Emails waiting for the Python mailer (python/, #22). The site adds a row in the same
     * transaction as the change the email reports, holding only what the email shows; the
     * mailer sends it, records the outcome and empties the payload.
     */
    public function up(): void
    {
        Schema::create('email_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Which email: python/mailer/templates has a folder for each kind
            $table->string('kind', 40);
            // What the email shows (Order::confirmationEmail()); null once it has been sent
            $table->jsonb('payload')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            // When the mailer gave up: the address was refused, or every attempt failed
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // One email of each kind per order, however many times a payment is confirmed
            $table->unique(['order_id', 'kind']);
        });

        // The mailer may read what it sends and update only the delivery columns. It can't
        // see which order a row belongs to, add or delete rows, or read the orders.
        // Tests run on SQLite, which has no roles.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('GRANT SELECT (id, kind, payload, attempts, next_attempt_at, sent_at, failed_at, created_at) ON email_outbox TO mailer');
            DB::statement('GRANT UPDATE (payload, attempts, next_attempt_at, last_error, sent_at, failed_at, updated_at) ON email_outbox TO mailer');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_outbox');
    }
};
