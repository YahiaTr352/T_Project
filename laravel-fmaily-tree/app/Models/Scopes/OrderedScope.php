<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrderedScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        // تأهيل الأعمدة باسم الجدول لتجنب Ambiguous عند JOIN (مثل marriages مع wives())
        $table = $model->getTable();

        $builder->orderByRaw("CASE WHEN {$table}.display_order = 0 THEN 1 ELSE 0 END ASC")
            ->orderBy("{$table}.display_order", 'asc')
            ->orderBy("{$table}.birth_date", 'asc');
    }
}
