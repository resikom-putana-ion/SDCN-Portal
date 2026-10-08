<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolRecord extends Model
{
    protected $fillable = ['kind', 'code', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    public function value(string $key, mixed $default = ''): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
