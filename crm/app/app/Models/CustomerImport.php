<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One run of "Import customers": the running totals and the rows that were skipped. */
class CustomerImport extends Model
{
    protected $fillable = [
        'user_id', 'token', 'file_name', 'status', 'total_rows', 'processed', 'created', 'updated',
        'unchanged', 'cars_added', 'skipped', 'problems', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['problems' => 'array', 'finished_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function percent(): int
    {
        return $this->total_rows > 0 ? (int) floor($this->processed * 100 / $this->total_rows) : 100;
    }
}
