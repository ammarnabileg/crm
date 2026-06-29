<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/**
 * Workspace wallet + append-only ledger (docs/WALLET_AND_BILLING.md §10).
 * Each workspace (a company) prepays credits in USD cents; every movement is a
 * ledger row carrying the resulting balance for auditability.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // One prepaid wallet per workspace (the company's credit balance).
        $schema->create('wallets', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->integer('balance_cents')->default(0);
            $t->string('currency', 8)->default('USD');
            $t->timestamps();
            $t->unique('workspace_id', 'wallets_ws_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });

        // Append-only ledger: never updated or deleted (created_at only).
        $schema->create('wallet_transactions', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('type', 16);                 // topup | charge | refund | adjustment
            $t->integer('amount_cents');            // signed: +credit / -debit
            $t->integer('balance_after_cents');     // wallet balance after this row
            $t->string('source', 24);               // fawaterak | plan | addon | seat | renewal | system
            $t->ulid('reference_id')->nullable();   // e.g. fawaterak_payment / invoice / plan id
            $t->string('description')->nullable();
            $t->ulid('actor_user_id')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'created_at'], 'wallet_tx_ws_created_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['wallet_transactions', 'wallets'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
