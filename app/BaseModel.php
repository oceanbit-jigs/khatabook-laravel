<?php

namespace App;

use App\Traits\Constant;
use Closure;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use Constant;

    public $timestamps = true;

    /**
     * @param $column name of column on which serach perform
     * @param $searchText  keyword of search
     * This Method is Use for Search list with give ColumnName And Search Keyword
     */
    public function scopeSearch($query, $column, $searchText)
    {
        if (isset($searchText) && trim($searchText !== '')) {
            if (is_array($column)) {
                foreach ($column as $i => $field) {
                    if ($i == 0) {
                        $query->where($field, 'LIKE', '%' . $searchText . '%');
                    } else {
                        $query->orWhere($field, 'LIKE', '%' . $searchText . '%');
                    }
                }
            } else {
                $query->where($column, 'LIKE', '%' . $searchText . '%');
            }
        }
        return $query;
    }

    /**
     * @param $query
     * @param $reletionMethod
     * @param Closure|null $callback
     *
     * Check below link for more detail
     * https://stackoverflow.com/a/30232227/14344727
     * @return
     */
    public function scopeFilterWithRelation($query, $reletionMethod, Closure $callback = null)
    {
        return $query->whereHas($reletionMethod, $callback);
    }

    /**
     * @param $query
     * @param $reletionMethod
     * @param Closure|null $callback
     *
     * Check below link for more detail
     * https://stackoverflow.com/a/30232227/14344727
     *
     * @return
     */
    public function scopeFilterWithRelationOR($query, $reletionMethod, Closure $callback = null)
    {
        return $query->orWhereHas($reletionMethod, $callback);
    }

    /**
     * @param $reletionMethod
     * @param $tableName
     * @param $column
     * @param $searchText
     * this Method perform search $query with given $tableName $reletionMethod on give $column with $searchText
     *
     *
     * Check below link for more detail
     * https://stackoverflow.com/a/30232227/14344727
     */
    public function scopeSearchWithRelationOR($query, $reletionMethod, $tableName, $column, $searchText)
    {
        if (isset($searchText) && trim($searchText !== '')) {
            return $query->orWhereHas($reletionMethod, function ($query) use ($searchText, $tableName, $column) {
                $query->where($tableName . '.' . $column, 'LIKE', "%$searchText%");
            });
        }
        return $query;
    }

    /**
     * @param $reletionMethod
     * @param $tableName
     * @param $column
     * @param $searchText
     * this Method perform search $query with given $tableName $reletionMethod on give $column with $searchText
     */
    public function scopeSearchWithRelationAND($query, $reletionMethod, $tableName, $column, $searchText)
    {
        if (isset($searchText) && trim($searchText !== '')) {
            return $query->whereHas($reletionMethod, function ($query) use ($searchText, $tableName, $column) {
                $query->where($tableName . '.' . $column, 'LIKE', "%$searchText%");
            });
        }
        return $query;
    }

    /*
     *  Search with Relation Model Column Name
     * @param $query db query
     * @param protected $relationMehod can be array Or single
     *
     *
     * */
    public function scopeSearchWithRelation($query, $reletionMethod, $tableName, $column, $searchText)
    {
        if (isset($searchText) && trim($searchText !== '')) {

            if (is_array($reletionMethod)) {

                if (count($reletionMethod) !== count($tableName) || count($reletionMethod) !== count($column)) {
                    return $query;
                }

                foreach ($reletionMethod as $i => $relation) {
                    if ($i == 0) {
                        $query->whereHas($relation, function ($query) use ($i, $searchText, $tableName, $column) {
                            $query->where($tableName[$i] . '.' . $column[$i], 'LIKE', "%$searchText%");
                        });
                    } else {
                        $query->orWhereHas($relation, function ($query) use ($i, $searchText, $tableName, $column) {
                            $query->where($tableName[$i] . '.' . $column[$i], 'LIKE', "%$searchText%");
                        });
                    }
                }

            } else {
                $query->whereHas($reletionMethod, function ($query) use ($searchText, $tableName, $column) {
                    $query->where($tableName . '.' . $column, 'LIKE', "%$searchText%");
                });
            }
        }
        return $query;
    }

    public function scopeWithAndWhereHas($query, $relation, $constraint)
    {
        return $query->whereHas($relation, $constraint)
            ->with([$relation => $constraint]);
    }

    public function scopeWithOrWhereHas($query, $relation, $constraint)
    {
        return $query->orWhereHas($relation, $constraint)
            ->with([$relation => $constraint]);
    }

    /**
     * @param $reletionMethod
     * @param $tableName
     * @param $column
     * @param $searchText
     *                  this Method perform search $query on list Of relaation Model on give $column with $searchText
     *                  Check https://laravel.com/docs/7.x/eloquent-relationships#constraining-eager-loads
     */
    public function scopeSearchInRelation($query, $reletionMethod, $column, $searchText)
    {
        if (isset($searchText) && trim($searchText !== '')) {
            $query->with([$reletionMethod => function ($query) use ($searchText, $column) {
                $query->where($column, 'LIKE', "%$searchText%");
            }]);
        }
        return $query;
    }

    /**
     * Sort List in <b>ASC</b> Order
     * Must Be use After All <b>where</b> related Query
     */
    public function scopeSort($query, $column)
    {
        $query->orderBy($column, 'ASC');
        return $query;
    }

    /**
     * Sort List in <b>DESC</b> Order
     * Must Be use After All <b>where</b> related Query
     */
    public function scopeReverse($query, $column)
    {
        $query->orderBy($column, 'DESC');
        return $query;
    }
}
