<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\StudentLifecycleController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\NotificationController;
use App\Models\SchoolRecord;
use App\Support\CloudData;
use App\Support\SchoolAccess;
use Closure;
use Illuminate\Http\Request;

class SchoolRoleAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (SchoolAccess::hasFullAccess($request->user())) {
            return $next($request);
        }

        abort_unless($this->allowed($request), 403, 'Role akun Anda tidak memiliki akses ke bagian ini.');

        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        $route = $request->route();
        $controller = $route?->getControllerClass();
        $method = $route?->getActionMethod();
        $user = $request->user();

        if ($controller === AdminProfileController::class) {
            return in_array($method, ['save', 'photo'], true);
        }

        if ($controller === NotificationController::class) {
            return $method === 'feed';
        }

        if ($controller === AdminController::class) {
            return match ($method) {
                'dashboard' => true,
                'page' => SchoolAccess::allowsScreen($user, (string) $route->parameter('screen'), $route->parameter('id'), $route->parameter('tab'), $request),
                'save', 'export' => SchoolAccess::allowsKind($user, (string) $route->parameter('kind')),
                'studentPhoto', 'staffPhoto' => $user?->role === 'akademik',
                default => false,
            };
        }

        if ($controller === WorkflowController::class) {
            if ($user?->role === 'keuangan') {
                return in_array($method, ['payment', 'verifyPayment', 'receipt', 'receiptPdf', 'inspectPayment'], true);
            }

            if ($user?->role !== 'akademik') {
                return false;
            }

            return match ($method) {
                'reportPdf', 'identity', 'uploadDocument' => $this->isAcademicRecord($route->parameter('record')),
                'archivedReportPdf' => $this->isArchivedStudent($route->parameter('record')),
                'document' => $this->isAcademicDocument((string) $route->parameter('id')),
                'promotion' => true,
                default => false,
            };
        }

        if ($controller === StudentLifecycleController::class) {
            return false;
        }

        return false;
    }

    private function isAcademicRecord(mixed $record): bool
    {
        return $record instanceof SchoolRecord && in_array($record->kind, ['students', 'staff'], true);
    }

    private function isArchivedStudent(mixed $record): bool
    {
        return $record instanceof SchoolRecord && $record->kind === 'student_deletions';
    }

    private function isAcademicDocument(string $id): bool
    {
        $document = CloudData::table('school_documents')->find($id);
        $record = $document ? SchoolRecord::find($document->record_id) : null;

        return $record && in_array($record->kind, ['students', 'staff'], true);
    }
}
