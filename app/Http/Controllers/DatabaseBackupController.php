<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Support\DatabaseBackupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __invoke(Request $request, DatabaseBackupService $backupService): BinaryFileResponse
    {
        abort_unless(DatabaseBackupAccess::allowed(), 403);

        $company = (string) (Auth::user()?->company ?? '');

        abort_if($company === '', 403);

        $zipPath = $backupService->createBackupZip($company);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }
}
