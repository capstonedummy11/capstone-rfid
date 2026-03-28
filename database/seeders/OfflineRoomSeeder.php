<?php

namespace Database\Seeders;

use App\Models\RfidPanelSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OfflineRoomSeeder extends Seeder
{
  public function run(): void
  {
    if (!Schema::hasTable('rfid_panel_sessions')) {
      throw new RuntimeException('The rfid_panel_sessions table does not exist yet. Run php artisan migrate first.');
    }

    $rooms = [
      'ComLab 1',
      'ComLab 2',
      'ComLab 3',
      'ComLab 4',
      'ComLab 5',
    ];

    foreach ($rooms as $index => $room) {
      RfidPanelSession::updateOrCreate(
        ['room' => $room],
        [
          'panel_id' => 'panel-comlab-' . ($index + 1),
          'status' => 'offline',
          'is_listening' => false,
          'subject_code' => null,
          'schedule_id' => null,
          'opened_by_user_id' => null,
          'listening_started_at' => null,
          'paused_at' => null,
          'ended_at' => null,
          'meta' => [
            'seeded' => true,
            'display_label' => 'Not in use',
          ],
        ]
      );
    }
  }
}
