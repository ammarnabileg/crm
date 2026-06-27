<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\BackupManager;
use Throwable;

/**
 * Dashboard-driven Backup & Restore (Setup Bible: "Backup" / "Restore").
 *
 * Create database (.sql) and file (.zip) backups, download or delete them, and
 * restore the database from a chosen .sql backup — all from the browser, with
 * no terminal and no mysqldump. Every action is gated by system.manage and
 * wrapped defensively so a failure flashes a message rather than 500-ing.
 */
final class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $backups = [];
        $listError = null;
        try {
            $backups = $this->backups()->list();
        } catch (Throwable $e) {
            $listError = 'Could not read the backups folder: ' . $e->getMessage();
        }

        return $this->view('system.backup', [
            'title'     => 'Backup & restore',
            'backups'   => $backups,
            'listError' => $listError,
        ]);
    }

    public function createDatabase(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        try {
            $path = $this->backups()->createDatabaseBackup();
            $this->withSuccess('Database backup created: ' . basename($path));
        } catch (Throwable $e) {
            $this->withError('Database backup failed: ' . $e->getMessage());
        }

        return $this->redirect(url('system/backups'));
    }

    public function createFiles(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        try {
            $path = $this->backups()->createFilesBackup();
            $this->withSuccess('Files backup created: ' . basename($path));
        } catch (Throwable $e) {
            $this->withError('Files backup failed: ' . $e->getMessage());
        }

        return $this->redirect(url('system/backups'));
    }

    public function download(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $name = (string) ($request->route('name') ?? $request->input('name', ''));
        $path = $this->backups()->path($name);

        if ($path === null) {
            $this->withError('That backup could not be found.');

            return $this->redirect(url('system/backups'));
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            $this->withError('Could not read the backup file for download.');

            return $this->redirect(url('system/backups'));
        }

        $filename = basename($path);
        $isSql = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'sql';

        return Response::make($contents, 200, [
            'Content-Type'        => $isSql ? 'application/sql' : 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($contents),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    public function destroy(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $name = (string) ($request->route('name') ?? $request->input('name', ''));

        try {
            $this->backups()->delete($name);
            $this->withSuccess('Backup deleted.');
        } catch (Throwable $e) {
            $this->withError('Could not delete backup: ' . $e->getMessage());
        }

        return $this->redirect(url('system/backups'));
    }

    public function restore(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $data = $this->validate($request, [
            'name'    => 'required|string|max:255',
            'confirm' => 'required',
        ]);

        try {
            $this->backups()->restoreDatabase((string) $data['name']);
            $this->withSuccess('Database restored from ' . basename((string) $data['name']) . '.');
        } catch (Throwable $e) {
            $this->withError('Restore failed: ' . $e->getMessage());
        }

        return $this->redirect(url('system/backups'));
    }

    private function backups(): BackupManager
    {
        return new BackupManager();
    }
}
