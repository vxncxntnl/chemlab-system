<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_name',
        'address',
        'contact_info',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function equipmentInventory(): HasMany
    {
        return $this->hasMany(EquipmentInventory::class);
    }
}
