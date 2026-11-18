<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Server-side helper for the Vue <DataTable>:
 *   ?search=...            global search across $searchable columns
 *   ?filters[col]=...      per-column search (LIKE for $searchable, exact match for $exact)
 *   ?sort=col&dir=asc|desc ordering (only columns in $sortable)
 *   ?page=1&per_page=10    pagination (per_page=0 -> all rows, max 5000, used by exports)
 *
 * Column names may use "relation.column" (e.g. "user.name") for search.
 */
class TableQuery
{
    public static function respond(Builder $query, Request $request, array $searchable = [], array $sortable = [], string $defaultSort = 'id', string $defaultDir = 'desc', array $exact = [])
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '' && $searchable) {
            $query->where(function (Builder $q) use ($searchable, $search) {
                foreach ($searchable as $col) {
                    static::orWhereLike($q, $col, $search);
                }
            });
        }

        foreach ((array) $request->query('filters', []) as $col => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($col, $exact, true)) {
                // dropdown filters: exact match (e.g. user_id, is_esign)
                $query->where($query->getModel()->getTable().'.'.$col, $value);

                continue;
            }
            if (! in_array($col, $searchable, true)) {
                continue;
            }
            $query->where(function (Builder $q) use ($col, $value) {
                static::orWhereLike($q, $col, $value);
            });
        }

        $sort = $request->query('sort', $defaultSort);
        $dir = strtolower($request->query('dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, $sortable, true) || $sort === $defaultSort) {
            $query->orderBy($query->getModel()->getTable().'.'.$sort, $dir);
        }

        $perPage = (int) $request->query('per_page', 10);
        if ($perPage <= 0) {
            $rows = $query->limit(5000)->get();

            return response()->json(['data' => $rows, 'total' => $rows->count()]);
        }

        $page = $query->paginate(min($perPage, 500));

        return response()->json(['data' => $page->items(), 'total' => $page->total()]);
    }

    protected static function orWhereLike(Builder $q, string $col, string $value): void
    {
        if (str_contains($col, '.')) {
            [$relation, $column] = explode('.', $col, 2);
            $q->orWhereHas($relation, fn (Builder $r) => $r->where($column, 'like', "%{$value}%"));
        } else {
            $q->orWhere($q->getModel()->getTable().'.'.$col, 'like', "%{$value}%");
        }
    }
}
