<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Integration Platform (docs/INTEGRATION_PLATFORM.md): API tokens, rate limits, webhooks. */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        // Workspace-scoped API tokens. Stored as a SHA-256 hash; the plaintext is
        // shown exactly once at issue time (never persisted).
        $schema->create('api_tokens', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');                 // the token acts as this member
            $t->string('name');
            $t->string('token_hash', 64);        // sha256 hex
            $t->string('token_prefix', 16);      // shown as a hint, e.g. "hh_3f9a"
            $t->string('last_four', 8);
            $t->datetime('last_used_at')->nullable();
            $t->datetime('expires_at')->nullable();
            $t->datetime('revoked_at')->nullable();
            $t->timestamps();
            $t->unique('token_hash', 'api_tokens_hash_uq');
            $t->index(['workspace_id'], 'api_tokens_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });

        // Fixed-window rate-limit counters, keyed by an opaque bucket string.
        $schema->create('rate_limits', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->string('bucket', 191);           // e.g. "token:<id>:<window-epoch>"
            $t->integer('hits')->default(0);
            $t->datetime('window_start');
            $t->datetime('created_at')->nullable();
            $t->unique('bucket', 'rate_limits_bucket_uq');
        });

        // Outbound webhook endpoints (subscriptions to domain events).
        $schema->create('webhook_endpoints', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->string('url', 1024);
            $t->string('secret', 64);            // HMAC signing secret (hex)
            $t->json('events');                  // ["application.submitted", ...]
            $t->string('description')->nullable();
            $t->boolean('enabled')->default(true);
            $t->integer('failure_count')->default(0);
            $t->datetime('last_delivered_at')->nullable();
            $t->ulid('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id'], 'webhook_endpoints_ws_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('created_by', 'users', 'id', 'SET NULL');
        });

        // Per-attempt delivery records (audit + retry source of truth).
        $schema->create('webhook_deliveries', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('endpoint_id');
            $t->string('event', 128);
            $t->json('payload')->nullable();
            $t->string('status', 32)->default('pending'); // pending|delivered|failed
            $t->integer('response_status')->nullable();
            $t->text('error')->nullable();
            $t->integer('attempts')->default(0);
            $t->string('signature', 80)->nullable();
            $t->datetime('delivered_at')->nullable();
            $t->datetime('created_at')->nullable();
            $t->index(['workspace_id', 'created_at'], 'webhook_deliveries_ws_created_idx');
            $t->index(['endpoint_id'], 'webhook_deliveries_endpoint_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('endpoint_id', 'webhook_endpoints', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['webhook_deliveries', 'webhook_endpoints', 'rate_limits', 'api_tokens'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
