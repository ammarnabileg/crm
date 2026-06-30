<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\LearningCatalog;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Learning\Application\LearningCatalogAdapter;
use HaHireAI\Modules\Learning\Presentation\LearningController;
use HaHireAI\Modules\Learning\Presentation\MyLearningController;

/**
 * Learning — workspace-owned learning, training & onboarding programs
 * (docs/LEARNING_PROGRAMS.md). Programs are trees of sections → items, with
 * assignment, enrollment + progress, manager/self to-dos, polymorphic comments
 * and collaboration. Depends on Workspaces (tenancy + shell), Memberships
 * (assignment fan-out via MemberDirectory) and Files (document items/attachments).
 */
final class LearningModule implements Module
{
    public function name(): string
    {
        return 'Learning';
    }

    public function dependencies(): array
    {
        return ['Workspaces', 'Memberships', 'Files'];
    }

    public function register(Container $container): void
    {
        // The sanctioned read/enroll surface other modules depend on
        // (future candidate/employee onboarding) — never the Learning tables.
        $container->singleton(LearningCatalog::class, LearningCatalogAdapter::class);
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        // --- Authoring & management (staff) ---
        $router->get('/learning', [LearningController::class, 'index']);
        $router->post('/learning', [LearningController::class, 'create']);
        $router->get('/learning/{id}', [LearningController::class, 'show']);
        $router->post('/learning/{id}', [LearningController::class, 'update']);
        $router->post('/learning/{id}/status', [LearningController::class, 'setStatus']);
        $router->post('/learning/{id}/delete', [LearningController::class, 'delete']);
        $router->post('/learning/{id}/snapshot', [LearningController::class, 'snapshot']);

        $router->post('/learning/{id}/sections', [LearningController::class, 'addSection']);
        $router->post('/learning/{id}/sections/{sectionId}/delete', [LearningController::class, 'deleteSection']);
        $router->post('/learning/{id}/sections/{sectionId}/items', [LearningController::class, 'addItem']);
        $router->post('/learning/{id}/items/{itemId}/delete', [LearningController::class, 'deleteItem']);

        $router->post('/learning/{id}/assign', [LearningController::class, 'assign']);

        $router->post('/learning/{id}/todos', [LearningController::class, 'addTodo']);
        $router->post('/learning/{id}/todos/{todoId}/status', [LearningController::class, 'todoStatus']);
        $router->post('/learning/{id}/todos/{todoId}/delete', [LearningController::class, 'deleteTodo']);

        $router->post('/learning/{id}/comments', [LearningController::class, 'addComment']);
        $router->post('/learning/{id}/comments/{commentId}/delete', [LearningController::class, 'deleteComment']);

        // --- Learner ("My Learning") ---
        $router->get('/my-learning', [MyLearningController::class, 'index']);
        $router->get('/my-learning/{id}', [MyLearningController::class, 'show']);
        $router->post('/my-learning/{id}/items/{itemId}', [MyLearningController::class, 'markItem']);
    }
}
