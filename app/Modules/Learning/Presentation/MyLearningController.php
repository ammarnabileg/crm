<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Learning\Application\CommentService;
use HaHireAI\Modules\Learning\Application\EnrollmentService;
use HaHireAI\Modules\Learning\Application\ProgramService;
use HaHireAI\Modules\Learning\Application\TodoService;
use HaHireAI\Modules\Learning\Domain\ItemType;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * The learner-facing side of the Learning module ("My Learning"): the programs a
 * member is enrolled in, a clean reader for working through a program, and the
 * one-click "mark complete" that drives progress. Available to any member who can
 * view learning (learning.view); each learner only ever sees their OWN enrollments.
 */
final class MyLearningController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ProgramService $programs,
        private readonly EnrollmentService $enrollments,
        private readonly TodoService $todos,
        private readonly CommentService $comments,
        private readonly Session $session,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        return $this->shell->render($this->context, 'learning.my', [
            'enrollments' => $this->enrollments->forUser($ws, $uid),
            'todos' => $this->todos->openForUser($ws, $uid),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $program = $this->programs->find($ws, $id);
        if ($program === null) {
            return Response::html('<h1>404</h1><p>Program not found.</p>', 404);
        }

        // Ensure the learner has an enrollment (self-directed learning auto-enrolls).
        if ($this->enrollments->enrollment($ws, $id, $uid) === null) {
            $this->enrollments->enroll($ws, $id, $uid);
        }
        $enrollment = $this->enrollments->enrollment($ws, $id, $uid);
        $statuses = [];
        foreach ($this->enrollments->itemProgress($ws, (string) ($enrollment['id'] ?? '')) as $p) {
            $statuses[(string) $p['item_id']] = (string) $p['status'];
        }

        return $this->shell->render($this->context, 'learning.learn', [
            'program' => $program,
            'structure' => $this->programs->structure($ws, $id),
            'enrollment' => $enrollment,
            'itemStatuses' => $statuses,
            'todos' => $this->todos->forProgram($ws, $id),
            'comments' => $this->comments->thread($ws, 'program', $id),
            'itemTypes' => ItemType::TYPES,
            'currentUserId' => $uid,
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function markItem(Request $request, string $id, string $itemId): Response
    {
        if (($r = $this->gate('learning.view', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $status = (string) $request->input('status', 'completed');
        $this->enrollments->setItemStatus($ws, $id, $uid, $itemId, $status, $uid);

        return Response::redirect('/my-learning/' . $id);
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
