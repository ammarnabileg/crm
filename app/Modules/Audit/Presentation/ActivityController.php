<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Audit\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Modules\Audit\Application\ActivityFeed;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Workspace activity timeline (read from the audit log). */
final class ActivityController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ActivityFeed $feed,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('audit.view')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        return $this->shell->render($this->context, 'activity.index', [
            'items' => $this->feed->forWorkspace((string) $this->context->workspaceId(), 100),
        ]);
    }
}
