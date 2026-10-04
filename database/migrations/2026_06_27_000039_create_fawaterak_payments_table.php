<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Fawaterak top-up sessions (docs/WALLET_AND_BILLING.md §9). The platform is the
 * merchant selling credits; money enters only here. A paid, signature-verified
 * webhook credits the wallet exactly once (idempotent on provider_invoice_id).
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('fawaterak_payments', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('provider_invoice_id', 128)->nullable();
            $t->integer('amount_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->string('status', 16)->default('pending'); // pending | paid | failed | refunded
            $t->boolean('signature_verified')->default(false);
            $t->boolean('credited')->default(false);       // wallet credited (idempotency guard)
            $t->longText('raw_payload')->nullable();
            $t->ulid('actor_user_id')->nullable();
            $t->datetime('created_at')->nullable();
            $t->datetime('updated_at')->nullable();
            $t->unique('provider_invoice_id', 'fawaterak_payments_invoice_uq');
            $t->index(['workspace_id', 'created_at'], 'fawaterak_payments_ws_created_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('fawaterak_payments');
    }
};
