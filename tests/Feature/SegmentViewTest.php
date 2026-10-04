<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Kernel;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentField;
use PHPUnit\Framework\TestCase;

/**
 * The Smart-Segment views render through the real container (so csrf_field() and
 * the other helpers resolve) and reflect their data / permission gating.
 */
final class SegmentViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $kernel = (new Kernel(new Container(), dirname(__DIR__, 2)))->boot();
        $this->view = $kernel->container()->make(View::class);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public function test_segments_index_lists_segments_and_the_builder(): void
    {
        $html = $this->view->render('recruitment.talent.segments', [
            'segments' => [
                ['id' => '01ID', 'name' => 'Senior React', 'match_type' => 'all', 'rules' => 3, 'created_at' => '2026-07-01'],
            ],
            'fields' => SegmentField::catalog(),
            'canManage' => true,
            'status' => null,
        ]);

        $this->assertStringContainsString('Smart Segments', $html);
        $this->assertStringContainsString('Senior React', $html);
        $this->assertStringContainsString('/talent-pool/segments/01ID', $html);
        $this->assertStringContainsString('name="rule_field[]"', $html);   // builder present
        $this->assertStringContainsString('name="match_type"', $html);
    }

    public function test_segment_show_renders_matches_and_rule_editor(): void
    {
        $html = $this->view->render('recruitment.talent.segment', [
            'segment' => ['id' => '01SEG', 'name' => 'React + English', 'match_type' => 'all'],
            'rules' => [
                ['id' => 'r1', 'field' => 'skill', 'operator' => 'like', 'value' => 'React', 'position' => 0],
            ],
            'matches' => [
                ['user_id' => '01USR', 'name' => 'Ada Lovelace', 'email' => 'ada@x.co', 'reasons' => ['Skill: React'], 'reason' => 'Skill: React'],
            ],
            'pools' => [['id' => '01POOL', 'name' => 'Future hires']],
            'fields' => SegmentField::catalog(),
            'canManage' => true,
            'status' => null,
        ]);

        $this->assertStringContainsString('React + English', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString('Skill: React', $html);            // match reason shown
        $this->assertStringContainsString('/talent-pool/segments/01SEG/bulk-add', $html);
        $this->assertStringContainsString('Future hires', $html);            // pool option for bulk-add
        $this->assertStringContainsString('/talent-pool/segments/01SEG/delete', $html);
    }

    public function test_read_only_viewer_sees_no_management_controls(): void
    {
        $html = $this->view->render('recruitment.talent.segment', [
            'segment' => ['id' => '01SEG', 'name' => 'React', 'match_type' => 'any'],
            'rules' => [],
            'matches' => [['user_id' => '01USR', 'name' => 'Ada', 'email' => 'ada@x.co', 'reasons' => [], 'reason' => '']],
            'pools' => [],
            'fields' => SegmentField::catalog(),
            'canManage' => false,
            'status' => null,
        ]);

        $this->assertStringNotContainsString('Edit rules', $html);
        $this->assertStringNotContainsString('Delete segment', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html); // no bulk-select without manage
    }
}
