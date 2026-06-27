<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\LogReader;

/**
 * Dashboard log viewer (Setup Bible: "Log Viewer"). Lists the application log
 * files, tails the selected one, and — for every error/warning line — shows a
 * plain-English suggested fix so an admin can triage problems and clear a log
 * without ever opening a terminal.
 */
final class LogViewerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $reader = $this->reader();
        $files = $reader->files();

        // Pick the requested file, falling back to the newest one. Anything the
        // reader refuses to resolve (traversal, unknown name) yields an empty
        // tail and we treat it as "no file selected".
        $requested = trim((string) $request->query('file', ''));
        $selected = null;
        foreach ($files as $file) {
            if ($requested !== '' && $file['name'] === $requested) {
                $selected = $file['name'];
                break;
            }
        }
        if ($selected === null && $requested === '' && $files !== []) {
            $selected = $files[0]['name'];
        }

        $lines = [];
        if ($selected !== null) {
            foreach ($reader->tail($selected, 300) as $entry) {
                // Annotate error/warning lines with a suggested fix when we have one.
                $entry['fix'] = in_array($entry['level'], ['error', 'warning'], true)
                    ? $reader->classify($entry['message'])
                    : null;
                $lines[] = $entry;
            }
        }

        return $this->view('system.logs', [
            'title'    => 'Log viewer',
            'files'    => $files,
            'selected' => $selected,
            'lines'    => $lines,
        ]);
    }

    public function clear(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $name = trim((string) $request->input('file', ''));

        if ($name === '') {
            $this->withError('Choose a log file to clear first.');

            return $this->redirect(url('system/logs'));
        }

        $reader = $this->reader();

        // Only clear a file we can actually see in the directory; this also means
        // an unresolved/traversal name is silently rejected here.
        $known = array_column($reader->files(), 'name');
        if (! in_array($name, $known, true)) {
            $this->withError('That log file could not be found.');

            return $this->redirect(url('system/logs'));
        }

        $reader->clear($name);
        $this->withSuccess('Cleared “' . $name . '”.');

        return $this->redirect(url('system/logs?file=' . rawurlencode($name)));
    }

    private function reader(): LogReader
    {
        return new LogReader();
    }
}
