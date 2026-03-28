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
}
