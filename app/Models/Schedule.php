<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
  use HasFactory;

  protected $table = 'schedules';
  protected $primaryKey = 'scheduled_id';

  protected $fillable = [
    'scheduled_id',
    'section_id',
    'subject_code',
    'weekdays',
    'time_start',
    'time_end',
    'room',
  ];

  public function panelSessions()
  {
    return $this->hasMany(RfidPanelSession::class, 'schedule_id', 'scheduled_id');
  }
}
