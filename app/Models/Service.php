<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'duration_minutes', 'price_cents'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price_cents' => 'integer'];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
