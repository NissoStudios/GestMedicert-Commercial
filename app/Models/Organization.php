<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }
}
