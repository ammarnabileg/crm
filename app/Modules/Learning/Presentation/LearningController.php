<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Learning\Application\CommentService;
use HaHireAI\Modules\Learning\Application\EnrollmentService;
use HaHireAI\Modules\Learning\Application\ProgramService;
use HaHireAI\Modules\Learning\Application\QuizService;
use HaHireAI\Modules\Learning\Application\TodoService;
use HaHireAI\Modules\Learning\Domain\ItemType;
use HaHireAI\Modules\Learning\Domain\ProgramStatus;
use HaHireAI\Modules\Learning\Domain\TodoStatus;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * The Learning authoring & management surface: the program catalog, the program
 * builder (sections → items), assignment + roster, to-dos, comments and version
 * history. Every action is permission-gated and tenant-scoped; the learner-facing
 * side lives in {@see MyLearningController}.
 */
final class LearningController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly ProgramService $programs,
        private readonly EnrollmentService $enrollments,
        private readonly TodoService $todos,
        private readonly CommentService $comments,
        private readonly QuizService $quizzes,
        private readonly MemberDirectory $members,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $status = (string) $request->input('status', '');
        $filters = [
            'status' => ProgramStatus::isValid($status) ? $status : '',
            'q' => trim((string) $request->input('q', '')),
            'category' => trim((string) $request->input('category', '')),
        ];

        return $this->shell->render($this->context, 'learning.index', [
            'programs' => $this->programs->listForWorkspace($ws, $filters),
            'filters' => $filters,
            'statuses' => ProgramStatus::STATUSES,
            'difficulties' => ProgramStatus::DIFFICULTIES,
            'canManage' => $this->context->can('learning.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $id = $this->programs->create($ws, (string) $this->context->userId(), [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'category' => (string) $request->input('category', ''),
            'difficulty' => (string) $request->input('difficulty', 'beginner'),
            'estimated_minutes' => (int) $request->input('estimated_minutes', 0),
            'tags' => (string) $request->input('tags', ''),
        ]);
        $this->audit->record('learning.program.created', [
            'workspace_id' => $ws, 'actor_user_id' => $this->context->userId(),
            'entity_type' => 'learning_program', 'entity_id' => $id,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);
        $this->session->flash('status', 'Program created — start adding sections and content.');

        return Response::redirect('/learning/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $program = $this->programs->find($ws, $id);
        if ($program === null) {
            return Response::html('<h1>404</h1><p>Program not found.</p>', 404);
        }

        $roster = $this->enrollments->roster($ws, $id);
        $structure = $this->programs->structure($ws, $id);

        return $this->shell->render($this->context, 'learning.show', [
            'program' => $program,
            'structure' => $structure,
            'quizzes' => $this->quizMap($ws, $structure),
            'tags' => $this->programs->tagsFor($ws, $id),
            'editors' => $this->programs->editorsFor($ws, $id),
            'todos' => $this->todos->forProgram($ws, $id),
            'roster' => $roster['enrollments'],
            'stats' => $roster['stats'],
            'assignments' => $this->enrollments->assignmentsFor($ws, $id),
            'activity' => $this->programs->recentActivity($ws, $id),
            'comments' => $this->comments->thread($ws, 'program', $id),
            'members' => $this->members->membersForWorkspace($ws),
            'itemTypes' => ItemType::TYPES,
            'difficulties' => ProgramStatus::DIFFICULTIES,
            'completionRules' => ProgramStatus::COMPLETION_RULES,
            'todoModes' => TodoStatus::MODES,
            'priorities' => TodoStatus::PRIORITIES,
            'canManage' => $this->context->can('learning.manage'),
            'canPublish' => $this->context->can('learning.publish'),
            'canAssign' => $this->context->can('learning.assign'),
            'currentUserId' => (string) $this->context->userId(),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->programs->update($ws, $id, (string) $this->context->userId(), [
            'title' => (string) $request->input('title', ''),
            'summary' => (string) $request->input('summary', ''),
            'description' => (string) $request->input('description', ''),
            'category' => (string) $request->input('category', ''),
            'difficulty' => (string) $request->input('difficulty', 'beginner'),
            'estimated_minutes' => (int) $request->input('estimated_minutes', 0),
            'completion_rule' => (string) $request->input('completion_rule', 'required_items'),
            'completion_threshold' => (int) $request->input('completion_threshold', 100),
            'tags' => (string) $request->input('tags', ''),
        ]);
        $this->session->flash('status', 'Program updated.');

        return Response::redirect('/learning/' . $id);
    }

    public function setStatus(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.publish', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $status = (string) $request->input('status', '');
        if ($this->programs->setStatus($ws, $id, $status, (string) $this->context->userId())) {
            $this->audit->record('learning.program.status_changed', [
                'workspace_id' => $ws, 'actor_user_id' => $this->context->userId(),
                'entity_type' => 'learning_program', 'entity_id' => $id,
                'changes' => ['status' => $status],
            ]);
            $this->session->flash('status', 'Program is now ' . ProgramStatus::label($status) . '.');
        }

        return Response::redirect('/learning/' . $id);
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->programs->delete($ws, $id, (string) $this->context->userId());
        $this->audit->record('learning.program.deleted', [
            'workspace_id' => $ws, 'actor_user_id' => $this->context->userId(),
            'entity_type' => 'learning_program', 'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Program deleted.');

        return Response::redirect('/learning');
    }

    public function snapshot(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.publish', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->programs->snapshotVersion($ws, $id, (string) $this->context->userId(), trim((string) $request->input('label', '')) ?: null);
        $this->session->flash('status', 'Version snapshot saved.');

        return Response::redirect('/learning/' . $id);
    }

    // --- Sections ----------------------------------------------------------

    public function addSection(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->programs->addSection(
            $ws, $id, (string) $this->context->userId(),
            (string) $request->input('title', 'Untitled section'),
            (string) $request->input('description', '') ?: null,
            (string) $request->input('is_required', '1') === '1',
        );
        $this->session->flash('status', 'Section added.');

        return Response::redirect('/learning/' . $id);
    }

    public function deleteSection(Request $request, string $id, string $sectionId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->programs->deleteSection((string) $this->context->workspaceId(), $sectionId);
        $this->session->flash('status', 'Section removed.');

        return Response::redirect('/learning/' . $id);
    }

    // --- Items -------------------------------------------------------------

    public function addItem(Request $request, string $id, string $sectionId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->programs->addItem($ws, $id, $sectionId, (string) $this->context->userId(), [
            'type' => (string) $request->input('type', ItemType::LESSON),
            'title' => (string) $request->input('title', ''),
            'body' => (string) $request->input('body', '') ?: null,
            'url' => (string) $request->input('url', '') ?: null,
            'duration_minutes' => (int) $request->input('duration_minutes', 0),
            'is_required' => (string) $request->input('is_required', '1') === '1',
        ]);
        $this->session->flash('status', 'Content added.');

        return Response::redirect('/learning/' . $id);
    }

    public function deleteItem(Request $request, string $id, string $itemId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->programs->deleteItem((string) $this->context->workspaceId(), $itemId);
        $this->session->flash('status', 'Content removed.');

        return Response::redirect('/learning/' . $id);
    }

    // --- Assignment --------------------------------------------------------

    public function assign(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.assign', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $count = $this->enrollments->assign(
            $ws, $id,
            (string) $request->input('assignee_type', 'user'),
            (string) $request->input('assignee_id', ''),
            (string) $this->context->userId(),
            trim((string) $request->input('due_date', '')) ?: null,
        );
        $this->session->flash('status', $count > 0 ? "Assigned — {$count} learner(s) enrolled." : 'Assignment recorded (no direct members to enroll yet).');

        return Response::redirect('/learning/' . $id);
    }

    // --- To-dos ------------------------------------------------------------

    public function addTodo(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->todos->create($ws, $id, (string) $this->context->userId(), [
            'title' => (string) $request->input('title', ''),
            'description' => (string) $request->input('description', ''),
            'completion_mode' => (string) $request->input('completion_mode', TodoStatus::MODE_SELF),
            'priority' => (string) $request->input('priority', 'normal'),
            'due_date' => (string) $request->input('due_date', ''),
            'assignee_user_id' => (string) $request->input('assignee_user_id', ''),
            'item_id' => (string) $request->input('item_id', ''),
            'section_id' => (string) $request->input('section_id', ''),
        ]);
        $this->session->flash('status', 'To-do added.');

        return Response::redirect('/learning/' . $id);
    }

    public function todoStatus(Request $request, string $id, string $todoId): Response
    {
        if (($r = $this->gate('learning.view', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $result = $this->todos->changeStatus(
            $ws, $todoId,
            (string) $request->input('status', 'open'),
            (string) $this->context->userId(),
            $this->context->can('learning.todo.manage'),
            (string) $request->input('note', '') ?: null,
        );
        $this->session->flash('status', $result['ok'] ? 'To-do updated.' : ($result['error'] ?? 'Could not update.'));

        return Response::redirect('/learning/' . $id);
    }

    public function deleteTodo(Request $request, string $id, string $todoId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->todos->delete((string) $this->context->workspaceId(), $todoId);
        $this->session->flash('status', 'To-do removed.');

        return Response::redirect('/learning/' . $id);
    }

    // --- Comments ----------------------------------------------------------

    public function addComment(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.view', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $body = (string) $request->input('body', '');
        $this->comments->add(
            $ws, $id,
            (string) $request->input('entity_type', 'program'),
            (string) $request->input('entity_id', $id),
            (string) $this->context->userId(),
            $body,
            (string) $request->input('parent_id', '') ?: null,
            $this->resolveMentions($ws, $body),
        );

        return Response::redirect('/learning/' . $id . '#discussion');
    }

    public function deleteComment(Request $request, string $id, string $commentId): Response
    {
        if (($r = $this->gate('learning.view', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->comments->delete($ws, $commentId, (string) $this->context->userId(), $this->context->can('learning.manage'));

        return Response::redirect('/learning/' . $id . '#discussion');
    }

    // --- Quizzes -----------------------------------------------------------

    public function addQuestion(Request $request, string $id, string $itemId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $qid = $this->quizzes->addQuestion($ws, $itemId, (string) $request->input('question', ''), (string) $request->input('type', 'single'));
        // Options arrive as parallel arrays: option_label[] + correct[] (index list).
        $labels = (array) $request->input('option_label', []);
        $correct = array_map('strval', (array) $request->input('correct', []));
        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $this->quizzes->addOption($ws, $qid, $label, in_array((string) $i, $correct, true));
        }
        $this->session->flash('status', 'Question added.');

        return Response::redirect('/learning/' . $id);
    }

    public function deleteQuestion(Request $request, string $id, string $questionId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->quizzes->deleteQuestion((string) $this->context->workspaceId(), $questionId);
        $this->session->flash('status', 'Question removed.');

        return Response::redirect('/learning/' . $id);
    }

    /**
     * Build a map of quiz item_id => questions(+options) for the builder view.
     *
     * @param  list<array<string,mixed>>  $structure
     * @return array<string, list<array<string,mixed>>>
     */
    private function quizMap(string $ws, array $structure): array
    {
        $map = [];
        foreach ($structure as $section) {
            foreach ((array) ($section['items'] ?? []) as $item) {
                if ((string) $item['type'] === ItemType::QUIZ) {
                    $map[(string) $item['id']] = $this->quizzes->questionsFor($ws, (string) $item['id']);
                }
            }
        }

        return $map;
    }

    /**
     * Map @handles in a comment body to member user ids (best-effort, name match).
     *
     * @return list<string>
     */
    private function resolveMentions(string $ws, string $body): array
    {
        if (! str_contains($body, '@')) {
            return [];
        }
        $members = $this->members->membersForWorkspace($ws);
        $needle = strtolower(str_replace(' ', '', $body));
        $ids = [];
        foreach ($members as $m) {
            $handle = '@' . strtolower(str_replace(' ', '', (string) $m['name']));
            if (str_contains($needle, $handle)) {
                $ids[] = (string) $m['user_id'];
            }
        }

        return $ids;
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
