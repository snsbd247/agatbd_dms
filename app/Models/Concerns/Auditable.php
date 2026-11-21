<?php

namespace App\Models\Concerns;

use App\Models\Settings\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes an audit_logs row whenever the model is created, updated or deleted.
 *
 * The action is "<snake_model>.created|updated|deleted", e.g.
 * "sales_order.updated". Updates store only the fields that changed (old and
 * new), and are skipped when nothing but timestamps changed. Console runs
 * without a logged-in user (seeders, scheduled backups) are not logged, so a
 * fresh install does not start with thousands of rows.
 */
trait Auditable
{
    /** Fields never written to the log. */
    protected static array $auditExclude = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', null, $model->auditAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = $model->auditAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->auditAttributes($model->getOriginal()), $changes);
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditAttributes($model->getOriginal()), null));
    }

    public function auditAttributes(array $attributes): array
    {
        return collect($attributes)
            ->except(array_merge(static::$auditExclude, $this->hidden ?? []))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)
            ->all();
    }

    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        if (app()->runningInConsole() && ! Auth::check()) {
            return;
        }

        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => Str::snake(class_basename($this)).'.'.$event,
                'auditable_type' => $this->getMorphClass(),
                'auditable_id' => $this->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the business action itself.
            report($e);
        }
    }
}
