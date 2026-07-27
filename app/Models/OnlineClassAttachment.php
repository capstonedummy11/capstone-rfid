<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassAttachment extends Model
{
    protected $primaryKey = 'online_class_attachment_id';

    protected $fillable = [
        'online_class_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class, 'online_class_id', 'online_class_id');
    }
}
