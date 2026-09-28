<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'active' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }
}
