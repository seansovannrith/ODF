<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Add `use Auditable;` to a model to record every create / update / delete
 * in both the Audit Log and the User Activity log.
 */
trait Auditable
{
    protected static array $auditHidden = ['password', 'remember_token', 'two_factor_secret', 'updated_at'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => static::writeAudit('created', $m));
        static::updated(fn (Model $m) => static::writeAudit('updated', $m));
        static::deleted(fn (Model $m) => static::writeAudit('deleted', $m));
    }

    protected static function writeAudit(string $event, Model $model): void
    {
        if ($event === 'updated') {
            $changes = collect($model->getChanges())->except(static::$auditHidden);
            if ($changes->isEmpty()) {
                return; // only timestamps changed
            }
            $properties = [
                'old' => collect($model->getOriginal())->only($changes->keys())->all(),
                'new' => $changes->all(),
            ];
        } else {
            $properties = collect($model->getAttributes())->except(static::$auditHidden)->all();
        }

        $request = app()->runningInConsole() ? null : request();
        $userId = auth()->id();

        AuditLog::create([
            'description' => $event,
            'subject_id' => $model->getKey(),
            'subject_type' => get_class($model),
            'user_id' => $userId,
            'properties' => $properties,
            'host' => $request?->ip(),
        ]);

        UserLog::create([
            'user_id' => $userId,
            'log_type' => ['created' => 'create', 'updated' => 'edit', 'deleted' => 'delete'][$event],
            'table_name' => $model->getTable(),
            'subject_id' => $model->getKey(),
            'data' => $properties,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}
