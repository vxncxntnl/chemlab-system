<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'table_affected',
        'record_id',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // The whole point of an audit trail is accountability that survives the
    // actor being removed later — a deleted admin's past actions must still
    // show who did them, not silently show a blank/null actor.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public static function log(int $userId, string $action, ?string $table = null, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): self
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'table_affected' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
