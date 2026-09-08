<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfidPanelSession extends Model
{
  use HasFactory;

  protected $table = 'rfid_panel_sessions';
  protected $primaryKey = 'panel_session_id';

  protected $fillable = [
    'panel_id',
    'room',
    'status',
    'subject_code',
    'schedule_id',
    'academic_year_id',
    'subject_offering_id',
    'opened_by_user_id',
    'is_listening',
    'listening_started_at',
    'paused_at',
    'ended_at',
    'meta',
  ];

  protected $casts = [
    'is_listening' => 'boolean',
    'listening_started_at' => 'datetime',
    'paused_at' => 'datetime',
    'ended_at' => 'datetime',
    'meta' => 'array',
  ];

  public function schedule()
  {
    return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduled_id');
  }

  public function openedBy()
  {
    return $this->belongsTo(User::class, 'opened_by_user_id', 'user_id');
  }

  protected static function booted(): void
  {
    static::saving(function (RfidPanelSession $session) {
      $schedule = $session->schedule_id ? Schedule::query()->find($session->schedule_id) : null;
      $session->academic_year_id = $schedule?->academic_year_id;
      $session->subject_offering_id = $schedule?->subject_offering_id;
    });
  }
}
