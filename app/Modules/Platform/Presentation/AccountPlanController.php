<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;

/**
 * Legacy account-plan endpoints. The product moved from predefined plan TIERS a
 * user picks to each workspace SELF-COMPOSING its plan (seats + features, paid
 * from the wallet) on /billing. Account workspace-capacity is governed by the
 * System Owner (see /users). These endpoints now redirect to the composer so old
 * links and bookmarks never dead-end.
 */
final class AccountPlanController
{
    public function index(): Response
    {
        return Response::redirect('/billing');
    }

    public function choose(Request $request): Response
    {
        return Response::redirect('/billing');
    }
}
