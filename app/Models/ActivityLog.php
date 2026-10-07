<?php
// FEATURE:audit-logging - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:admin-logs - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';
    protected $primaryKey = 'logs_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_id',
        'user_name',
        'user_role',
        'action',
        'table_name',
        'module',
        'outcome',
        'severity',
        'subject_type',
        'subject_id',
        'route_name',
        'http_method',
        'ip_address',
        'user_agent',
        'status_code',
        'description',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // @function user: Ibinabalik ang user Eloquent belongsTo relationship.
    // @useIn user: Eloquent relationship property at eager loading
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
