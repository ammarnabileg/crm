<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Every charge attempt against a member account — successes and failures — so the
 * System Owner can audit billing and diagnose payment-gateway problems. Failures
 * capture the provider error code/message; the report maps the code to a cause
 * and a remedy (docs/BILLING_PLATFORM.md).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('payment_attempts')) {
            return;
        }

        $schema->create('payment_attempts', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id')->nullable();
            $t->ulid('plan_id')->nullable();
            $t->integer('amount_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('status', 16);          // success | failed
            $t->string('provider', 32);        // gateway key
            $t->string('reference', 191)->nullable();    // provider ref on success
            $t->string('error_code', 64)->nullable();    // machine code on failure
            $t->text('error_message')->nullable();       // human message on failure
            $t->datetime('created_at')->nullable();
            $t->index('status', 'payment_attempts_status_idx');
            $t->index('created_at', 'payment_attempts_created_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'SET NULL');
            $t->foreign('plan_id', 'plans', 'id', 'SET NULL');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('payment_attempts');
    }
};
