<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Idempotent saves (audit M4 / L2). Forms send a random `client_key`; the table has a unique
 * (branch_id, client_key) index. A repeat of the same submit gets the first row back.
 */
final class ClientKey
{
    /** Validation rule for the hidden field. */
    public const RULE = ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'];

    /** A fresh key for a form. */
    public static function make(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Run $create once per key. Call it outside the DB transaction: on a race the losing insert
     * hits the unique index, its transaction rolls back, and the winner's row is returned.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @param  Closure(): T  $create
     * @return T
     */
    public static function once(string $model, ?int $branchId, ?string $key, Closure $create): Model
    {
        if ($key === null || $key === '') {
            return $create();
        }
        if ($found = self::find($model, $branchId, $key)) {
            return $found;
        }

        try {
            return $create();
        } catch (UniqueConstraintViolationException $e) {
            return self::find($model, $branchId, $key) ?? throw $e;
        }
    }

    /** @param class-string<Model> $model */
    public static function find(string $model, ?int $branchId, string $key): ?Model
    {
        return $model::query()->where('branch_id', $branchId)->where('client_key', $key)->first();
    }
}
