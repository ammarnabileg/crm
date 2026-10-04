<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\CandidateProfileFields;
use PHPUnit\Framework\TestCase;

/** The shared flatten used by the details→normalised dual-write + backfill. */
final class CandidateProfileFieldsTest extends TestCase
{
    public function test_flattens_scalars_and_arrays(): void
    {
        $rows = CandidateProfileFields::flatten([
            'email' => 'a@b.co',
            'skills' => ['PHP', 'Laravel', 'MySQL'],
            'current_salary' => 5000,
            'available' => true,
            'empty' => '',
            'nested' => [['x' => 1]], // array members that are arrays are skipped
        ]);

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['key']][] = $r['value'];
        }

        $this->assertSame(['a@b.co'], $byKey['email']);
        $this->assertSame(['PHP', 'Laravel', 'MySQL'], $byKey['skills']);
        $this->assertSame(['5000'], $byKey['current_salary']);
        $this->assertSame(['1'], $byKey['available']);
        $this->assertArrayNotHasKey('empty', $byKey);
        $this->assertArrayNotHasKey('nested', $byKey);
        // Array members carry an incrementing position.
        $skillPositions = array_values(array_map(
            static fn (array $r): int => $r['position'],
            array_filter($rows, static fn (array $r): bool => $r['key'] === 'skills'),
        ));
        $this->assertSame([0, 1, 2], $skillPositions);
    }

    public function test_empty_details_is_empty(): void
    {
        $this->assertSame([], CandidateProfileFields::flatten([]));
    }
}
