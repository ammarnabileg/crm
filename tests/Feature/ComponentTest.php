<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Request;
use Tests\TestCase;

/**
 * Design System (docs/30) — the component() library renders correct, accessible
 * markup. These lock the component contracts (classes, ARIA, data-hooks) so
 * refactors can't silently break the shared UI. The authenticated app shell
 * itself (dark-mode toggle, toast region) is covered by AppShellTest.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $db = app('db');
        tenant()->setById((int) $db->table('workspaces')->orderBy('id')->value('id'));
        // Bind a request: components that default a URL fall back to request()->path(),
        // exactly as they do under a real HTTP request.
        app()->instance('request', new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'], [], []));
    }

    /**
     * Rendering the catalog touches auth() (resolved once + cached) and the
     * AccessControl per-request cache. Reset them on the existing singletons so
     * later session-auth tests re-resolve cleanly — same isolation as AtsWebTest.
     */
    public function tearDown(): void
    {
        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(array $query = []): Request
    {
        $req = new Request($query, [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'], [], []);
        app()->instance('request', $req); // the layout + some components call request()

        return $req;
    }

    // --- attrs() helper -----------------------------------------------------

    public function test_attrs_helper_escapes_and_handles_bool(): void
    {
        $out = attrs(['data-x' => 'a"b', 'disabled' => true, 'hidden' => false, 'skip' => null]);
        $this->assertTrue(str_contains($out, 'data-x="a&quot;b"'));
        $this->assertTrue(str_contains($out, ' disabled'));
        $this->assertFalse(str_contains($out, 'hidden'));
        $this->assertFalse(str_contains($out, 'skip'));
    }

    // --- Individual components ---------------------------------------------

    public function test_button_renders_variant_and_escapes_label(): void
    {
        $html = component('button', ['label' => '<x>', 'variant' => 'danger', 'type' => 'submit']);
        $this->assertTrue(str_contains($html, 'btn-danger'));
        $this->assertTrue(str_contains($html, 'type="submit"'));
        $this->assertTrue(str_contains($html, '&lt;x&gt;'));
        $this->assertFalse(str_contains($html, '<x>'));
    }

    public function test_button_as_link_when_href_given(): void
    {
        $html = component('button', ['label' => 'Go', 'href' => '/jobs']);
        $this->assertTrue(str_contains($html, '<a href="/jobs"'));
    }

    public function test_input_forwards_name_and_marks_error(): void
    {
        $html = component('input', ['name' => 'title', 'error' => true, 'placeholder' => 'Title']);
        $this->assertTrue(str_contains($html, 'name="title"'));
        $this->assertTrue(str_contains($html, 'input-error'));
        $this->assertTrue(str_contains($html, 'aria-invalid="true"'));
    }

    public function test_field_wires_label_hint_and_error(): void
    {
        $html = component('field', [
            'label' => 'Email', 'for' => 'email', 'error' => 'Required.',
            'control' => component('input', ['name' => 'email', 'id' => 'email']),
        ]);
        $this->assertTrue(str_contains($html, 'for="email"'));
        $this->assertTrue(str_contains($html, 'form-error'));
        $this->assertTrue(str_contains($html, 'Required.'));
    }

    public function test_select_marks_selected_option(): void
    {
        $html = component('select', ['name' => 's', 'selected' => 'b', 'options' => ['a' => 'A', 'b' => 'B']]);
        $this->assertTrue(str_contains($html, 'value="b" selected'));
    }

    public function test_switch_is_accessible_and_has_hidden_input(): void
    {
        $html = component('switch', ['name' => 'notify', 'on' => true]);
        $this->assertTrue(str_contains($html, 'role="switch"'));
        $this->assertTrue(str_contains($html, 'aria-checked="true"'));
        $this->assertTrue(str_contains($html, 'name="notify"'));
        $this->assertTrue(str_contains($html, 'data-switch'));
    }

    public function test_badge_variant_class(): void
    {
        $this->assertTrue(str_contains(component('badge', ['label' => 'Open', 'variant' => 'green']), 'badge-green'));
    }

    public function test_alert_has_role_and_variant(): void
    {
        $html = component('alert', ['variant' => 'success', 'message' => 'Saved', 'dismissible' => true]);
        $this->assertTrue(str_contains($html, 'role="alert"'));
        $this->assertTrue(str_contains($html, 'alert-success'));
        $this->assertTrue(str_contains($html, 'data-alert-dismiss'));
    }

    public function test_modal_is_a_dialog_with_id_and_hidden(): void
    {
        $html = component('modal', ['id' => 'm1', 'title' => 'Hi', 'slot' => '<p>x</p>']);
        $this->assertTrue(str_contains($html, 'id="m1"'));
        $this->assertTrue(str_contains($html, 'role="dialog"'));
        $this->assertTrue(str_contains($html, 'aria-modal="true"'));
        $this->assertTrue(str_contains($html, 'data-modal'));
        $this->assertTrue(str_contains($html, 'hidden'));
    }

    public function test_tabs_render_aria_roles(): void
    {
        $html = component('tabs', ['id' => 't1', 'tabs' => [
            ['label' => 'One', 'panel' => '<p>1</p>'],
            ['label' => 'Two', 'panel' => '<p>2</p>'],
        ]]);
        $this->assertTrue(str_contains($html, 'role="tablist"'));
        $this->assertTrue(str_contains($html, 'role="tab"'));
        $this->assertTrue(str_contains($html, 'role="tabpanel"'));
        $this->assertTrue(str_contains($html, 'aria-selected="true"'));
        $this->assertTrue(str_contains($html, 'data-tab-target="t1-panel-0"'));
    }

    public function test_table_renders_rows_and_empty_state(): void
    {
        $with = component('table', ['columns' => ['Name'], 'rows' => [['<b>Sara</b>']]]);
        $this->assertTrue(str_contains($with, 'table-base'));
        $this->assertTrue(str_contains($with, '<b>Sara</b>'));

        $empty = component('table', ['columns' => ['Name'], 'rows' => [], 'empty' => 'Nothing here']);
        $this->assertTrue(str_contains($empty, 'Nothing here'));
        $this->assertTrue(str_contains($empty, 'colspan="1"'));
    }

    public function test_pagination_links_and_current(): void
    {
        $html = component('pagination', ['current' => 2, 'last' => 5, 'base' => '/jobs']);
        $this->assertTrue(str_contains($html, 'aria-current="page"'));
        $this->assertTrue(str_contains($html, 'href="/jobs?page=3"'));
        $this->assertTrue(str_contains($html, 'rel="next"'));
    }

    public function test_state_variants_render(): void
    {
        foreach (['empty', 'error', 'permission', 'offline', 'loading'] as $variant) {
            $html = component('state', ['variant' => $variant]);
            $this->assertTrue(str_contains($html, 'class="state'), "state {$variant} renders");
        }
        $this->assertTrue(str_contains(component('state', ['variant' => 'permission']), 'role="alert"'));
    }

    public function test_progress_is_accessible(): void
    {
        $html = component('progress', ['value' => 150, 'label' => 'X']); // clamps to 100
        $this->assertTrue(str_contains($html, 'role="progressbar"'));
        $this->assertTrue(str_contains($html, 'aria-valuenow="100"'));
    }

    public function test_autocomplete_is_a_combobox(): void
    {
        $html = component('autocomplete', ['name' => 'skill', 'options' => ['PHP', 'MySQL']]);
        $this->assertTrue(str_contains($html, 'role="combobox"'));
        $this->assertTrue(str_contains($html, 'role="listbox"'));
        $this->assertTrue(str_contains($html, 'data-autocomplete'));
        $this->assertTrue(str_contains($html, 'data-value="PHP"'));
    }

    public function test_datepicker_is_native_date(): void
    {
        $html = component('datepicker', ['name' => 'start', 'min' => '2026-01-01']);
        $this->assertTrue(str_contains($html, 'type="date"'));
        $this->assertTrue(str_contains($html, 'min="2026-01-01"'));
    }

    public function test_popover_uses_details(): void
    {
        $html = component('popover', ['label' => 'Info', 'slot' => '<p>hi</p>']);
        $this->assertTrue(str_contains($html, 'data-popover'));
        $this->assertTrue(str_contains($html, '<p>hi</p>'));
    }

    public function test_timeline_renders_items(): void
    {
        $html = component('timeline', ['items' => [['title' => 'Applied', 'time' => '2d'], ['title' => 'Hired']]]);
        $this->assertTrue(str_contains($html, '<ol'));
        $this->assertTrue(str_contains($html, 'Applied'));
        $this->assertTrue(str_contains($html, 'Hired'));
    }

    public function test_calendar_renders_month_grid(): void
    {
        $html = component('calendar', ['year' => 2026, 'month' => 6, 'events' => ['2026-06-15' => 'Interview'], 'base' => '/design']);
        $this->assertTrue(str_contains($html, 'June 2026'));
        $this->assertTrue(str_contains($html, 'grid-cols-7'));
        $this->assertTrue(str_contains($html, 'month=7')); // next-month link
    }

    public function test_file_upload_is_a_dropzone(): void
    {
        $html = component('file-upload', ['name' => 'cv', 'accept' => '.pdf', 'multiple' => true]);
        $this->assertTrue(str_contains($html, 'data-dropzone'));
        $this->assertTrue(str_contains($html, 'type="file"'));
        $this->assertTrue(str_contains($html, 'accept=".pdf"'));
        $this->assertTrue(str_contains($html, 'multiple'));
    }

    public function test_data_grid_sortable_selectable_and_empty(): void
    {
        $html = component('data-grid', [
            'columns' => [['label' => 'Name', 'sort' => 'name'], ['label' => 'Stage']],
            'rows' => [[e('Sara'), e('Offer')]], 'sort' => 'name', 'dir' => 'asc', 'base' => '/jobs', 'selectable' => true,
        ]);
        $this->assertTrue(str_contains($html, 'aria-sort="ascending"'));
        $this->assertTrue(str_contains($html, 'sort=name&amp;dir=desc')); // & escaped by e(); toggles asc -> desc
        $this->assertTrue(str_contains($html, 'data-grid-select-all'));

        $empty = component('data-grid', ['columns' => [['label' => 'Name']], 'rows' => [], 'empty' => 'Nothing']);
        $this->assertTrue(str_contains($empty, 'Nothing'));
    }

    public function test_notification_center_shows_unread_badge(): void
    {
        $html = component('notification-center', ['items' => [
            ['title' => 'A', 'read' => false], ['title' => 'B', 'read' => true],
        ]]);
        $this->assertTrue(str_contains($html, 'data-popover'));
        $this->assertTrue(str_contains($html, 'Notifications'));
        $this->assertTrue(str_contains($html, '>1<')); // one unread
    }

    public function test_search_is_a_search_form(): void
    {
        $html = component('search', ['action' => '/jobs', 'placeholder' => 'Find']);
        $this->assertTrue(str_contains($html, 'role="search"'));
        $this->assertTrue(str_contains($html, 'type="search"'));
        $this->assertTrue(str_contains($html, 'action="/jobs"'));
    }

};
