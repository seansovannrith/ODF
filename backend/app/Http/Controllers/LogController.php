<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\UserLog;
use App\Support\TableQuery;
use Illuminate\Http\Request;

/** Audit Logs + User Logs (user activity) screens. */
class LogController extends Controller
{
    public function auditIndex(Request $request)
    {
        return TableQuery::respond(
            AuditLog::with('user:id,name'),
            $request,
            ['description', 'subject_type', 'host'],
            ['id', 'description', 'subject_id', 'subject_type', 'user_id', 'host', 'created_at'],
            exact: ['subject_id', 'user_id', 'description'],
        );
    }

    public function auditShow(AuditLog $auditLog)
    {
        return $auditLog->load('user:id,name,email');
    }

    /**
     * ?user=name-or-id &log_type=login|logout|create|edit|delete &table=documents
     * &from=2026-11-01 &to=2026-11-13 &page=1&per_page=10
     */
    public function userLogs(Request $request)
    {
        $query = UserLog::with('user:id,name,email')->latest('id');

        if ($u = trim((string) $request->query('user'))) {
            $query->where(function ($q) use ($u) {
                if (ctype_digit($u)) {
                    $q->where('user_id', (int) $u);
                }
                $q->orWhereHas('user', fn ($w) => $w->where('name', 'like', "%{$u}%")->orWhere('email', 'like', "%{$u}%"));
            });
        }
        if ($type = $request->query('log_type')) {
            $query->where('log_type', $type);
        }
        if ($table = $request->query('table')) {
            $query->where('table_name', $table);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $page = $query->paginate(min((int) $request->query('per_page', 10), 100));

        return [
            'data' => $page->items(),
            'total' => $page->total(),
            'tables' => UserLog::whereNotNull('table_name')->distinct()->orderBy('table_name')->pluck('table_name'),
        ];
    }

    public function userLogShow(UserLog $userLog)
    {
        return $userLog->load('user:id,name,email');
    }

    /** "Delete data older than 30 days" */
    public function purgeUserLogs(Request $request)
    {
        $days = max(1, (int) $request->input('days', 30));
        $deleted = UserLog::where('created_at', '<', now()->subDays($days))->delete();

        return ['deleted' => $deleted];
    }
}
