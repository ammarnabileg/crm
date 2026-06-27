<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlueprintVersion;
use App\Services\Blueprint\BlueprintEngine;
use App\Services\Blueprint\BlueprintLibrary;
use RuntimeException;
use Tests\TestCase;

/**
 * AI Interview Engine — Blueprint Library + Engine (docs/51 §6, §11).
 *
 * Covers the role-family starter library, instantiation into an editable
 * blueprint + sections + typed rules, and immutable version snapshotting
 * (publish → version 1 active; publish again → version 2 supersedes).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        // DB-backed tests need an active tenant.
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function library(): BlueprintLibrary
    {
        return new BlueprintLibrary();
    }

    private function engine(): BlueprintEngine
    {
        return new BlueprintEngine();
    }

    // --- Library ------------------------------------------------------------

    public function test_library_has_twenty_one_entries(): void
    {
        $keys = $this->library()->keys();
        $this->assertSame(21, count($keys));
        $this->assertTrue(in_array('senior_backend_developer', $keys, true));
        $this->assertTrue(in_array('call_center', $keys, true));
    }

    public function test_definition_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->library()->definition('does_not_exist'));
    }

    // --- Instantiation (DB) -------------------------------------------------

    public function test_instantiate_creates_blueprint_with_sections_and_rules(): void
    {
        $blueprint = $this->library()->instantiateFromLibrary('senior_backend_developer');

        $this->assertSame('Senior Backend Developer', (string) $blueprint->name);
        $this->assertSame('engineering', (string) $blueprint->role_family);

        $sections = $blueprint->sections();
        $this->assertTrue(count($sections) >= 4);

        // At least one section must have materialized rules.
        $ruleCount = 0;
        foreach ($sections as $section) {
            $rows = app('db')->table('blueprint_section_rules')
                ->where('section_id', '=', (int) $section['id'])
                ->get();
            $ruleCount += count($rows);
        }
        $this->assertTrue($ruleCount > 0);
    }

    public function test_unknown_key_throws(): void
    {
        $threw = false;
        try {
            $this->library()->instantiateFromLibrary('not_a_real_blueprint');
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    // --- Publishing / versioning (DB) ---------------------------------------

    public function test_publish_creates_active_version_one_with_section_snapshot(): void
    {
        $blueprint = $this->library()->instantiateFromLibrary('senior_backend_developer');
        $blueprintId = (int) $blueprint->getKey();

        $v1 = $this->engine()->publish($blueprintId, null, 'first');
        $this->assertSame(1, (int) $v1->version);
        $this->assertTrue((bool) $v1->is_active);

        $snapshot = $this->engine()->activeSnapshot($blueprintId);
        $this->assertNotNull($snapshot);
        $this->assertTrue(isset($snapshot['sections']));
        $this->assertTrue(count($snapshot['sections']) >= 4);
    }

    public function test_publishing_twice_supersedes_prior_version(): void
    {
        $blueprint = $this->library()->instantiateFromLibrary('frontend_developer');
        $blueprintId = (int) $blueprint->getKey();

        $this->engine()->publish($blueprintId, null, 'first');
        $v2 = $this->engine()->publish($blueprintId, null, 'second');

        $this->assertSame(2, (int) $v2->version);

        $active = BlueprintVersion::activeFor($blueprintId);
        $this->assertNotNull($active);
        $this->assertSame(2, (int) $active->version);

        $total = BlueprintVersion::query()
            ->where('blueprint_id', '=', $blueprintId)
            ->count();
        $this->assertSame(2, $total);
    }
};
