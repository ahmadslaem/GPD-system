<?php

namespace App\Observers;

use App\Http\Controllers\Api\DashboardController;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * توثيق تلقائي لكل عمليات الإنشاء والتعديل والحذف (FR-SYS-06).
 * يُسجَّل على الموديلات عبر Model::observe() في AppServiceProvider.
 */
class AuditObserver
{
    /** الحقول التي لا قيمة توثيقية لها */
    private array $ignored = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        $this->log('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->changedAttributes($model);

        if ($changes === []) {
            return; // لا تعديل فعلي — لا توثيق
        }

        $this->log('updated', $model, $changes['old'], $changes['new']);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, $this->sanitize($model->getAttributes()), null);
    }

    private function changedAttributes(Model $model): array
    {
        $old = [];
        $new = [];

        foreach ($model->getChanges() as $field => $value) {
            if (in_array($field, $this->ignored, true)) {
                continue;
            }

            $original = $model->getOriginal($field);

            // توحيد الأنواع للمقارنة (int/string من الداتابيز)
            $same = is_bool($original) || is_bool($value)
                ? (bool) $original === (bool) $value
                : $original == $value;

            $old[$field] = $original;
            $new[$field] = $value;

            if ($same) {
                unset($old[$field], $new[$field]);
            }
        }

        return ['old' => $old ?: null, 'new' => $new ?: null];
    }

    private function log(string $action, Model $model, ?array $old, ?array $new): void
    {
        DashboardController::flushCache(); // الكتابة تلغى كاش الداشبورد فوراً

        AuditLog::create([
            'user_id'      => Auth::id(),
            'action'       => $action,
            'auditable_id'   => $model->getKey(),
            'auditable_type' => $model->getMorphClass(),
            'changes'      => $action === 'updated'
                ? ['old' => $old, 'new' => $new]
                : null,
            'ip_address'   => request()?->ip(),
        ]);
    }

    private function sanitize(array $attributes): array
    {
        return collect($attributes)
            ->except($this->ignored)
            ->all();
    }
}
