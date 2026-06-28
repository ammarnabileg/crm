<?php

declare(strict_types=1);

use HaHireAI\Core\Database\Migrations\Migration;
use HaHireAI\Core\Database\Schema\Blueprint;
use HaHireAI\Core\Database\Schema\SchemaBuilder;

/** Offers + post-hire Employee context (STATE_DIAGRAMS §4, §6). */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('offers', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('application_id');
            $t->string('title')->nullable();
            $t->bigInteger('salary')->nullable();
            $t->string('currency', 8)->default('USD');
            $t->string('status', 32)->default('draft'); // draft|sent|accepted|declined|revoked
            $t->ulid('created_by')->nullable();
            $t->datetime('sent_at')->nullable();
            $t->datetime('decided_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'status'], 'offers_ws_status_idx');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('application_id', 'applications', 'id', 'CASCADE');
        });

        $schema->create('employees', static function (Blueprint $t): void {
            $t->ulidPrimary();
            $t->ulid('workspace_id');
            $t->ulid('user_id');
            $t->ulid('application_id')->nullable();
            $t->string('status', 32)->default('onboarding'); // onboarding|active|terminated
            $t->string('title')->nullable();
            $t->datetime('start_date')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['workspace_id', 'user_id'], 'employees_ws_user_uq');
            $t->foreign('workspace_id', 'workspaces', 'id', 'CASCADE');
            $t->foreign('user_id', 'users', 'id', 'CASCADE');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('employees');
        $schema->dropIfExists('offers');
    }
};
