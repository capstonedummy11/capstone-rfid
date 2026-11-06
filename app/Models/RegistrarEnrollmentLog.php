<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrarEnrollmentLog extends Model
{
    protected $fillable = [
        'registrar_user_id',
        'action',
        'person_type',
        'person_id',
        'person_name',
        'identifier',
    ];

    // @function registrar: Ibinabalik ang registrar Eloquent belongsTo relationship.
    // @useIn registrar: Eloquent relationship property at eager loading
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrar_user_id', 'user_id');
    }
}
