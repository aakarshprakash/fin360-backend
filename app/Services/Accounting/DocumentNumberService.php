<?php

namespace App\Services\Accounting;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generates the next sequential document number (invoice, bill, quotation,
 * proforma invoice, etc.) safely.
 *
 * Deliberately does NOT use `Model::withTrashed()->count() + 1` — that scheme
 * collides as soon as any row is force-deleted (the count drops, so a future
 * insert recomputes a number that's already taken by an existing, possibly
 * soft-deleted, row) and throws a unique-constraint SQL error. Instead this
 * looks at the highest numeric suffix ever used for the given prefix —
 * including trashed rows, since a number must never be reused — and
 * increments from there, so gaps from deletions never cause a collision.
 */
class DocumentNumberService
{
    /**
     * @param class-string $modelClass Eloquent model class (trashed rows count when it uses SoftDeletes).
     * @param string $column The column holding the formatted number (e.g. 'invoice_number').
     * @param string $prefix The literal prefix to match and prepend (e.g. 'INV-').
     * @param int $padLength Zero-padding width for the numeric suffix.
     */
    public function next(string $modelClass, string $column, string $prefix, int $padLength = 5): string
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        $maxNum = $query
            ->where($column, 'like', $prefix . '%')
            ->pluck($column)
            ->map(function ($value) use ($prefix) {
                $suffix = substr($value, strlen($prefix));
                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad($maxNum + 1, $padLength, '0', STR_PAD_LEFT);
    }
}
