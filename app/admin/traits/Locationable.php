<?php

namespace Admin\Traits;

use AdminLocation;
use Igniter\Flame\Exception\ApplicationException;

trait Locationable
{
    /*
     * You can change the location relation name:
     *
     * const LOCATIONABLE_RELATION = 'location';
     */

    /**
     * @var bool Flag for arbitrarily enabling location scope.
     */
    public $locationScopeEnabled = FALSE;

    /**
     * Boot the locationable trait for a model.
     *
     * @return void
     */
    public static function bootLocationable()
    {
        static::deleting(function (self $model) {
            $model->detachLocationsOnDelete();
        });
    }

    public function locationableScopeEnabled()
    {
        if ($this->locationScopeEnabled)
            return TRUE;

        return AdminLocation::check();
    }

    public function locationableGetUserLocation()
    {
        return AdminLocation::getId();
    }

    //
    //
    //

    public function scopeWhereHasLocation($query, $locationId)
    {
        return $this->applyLocationScope($query, $locationId);
    }

    public function scopeWhereHasOrDoesntHaveLocation($query, $locationId)
    {
        return $query->whereHasLocation($locationId)
            ->orDoesntHave($this->locationableRelationName());
    }

    /**
     * Apply the Location scope query.
     *
     * @param \Igniter\Flame\Database\Builder $builder
     * @param \Igniter\Flame\Database\Model|array|int $userLocation
     */
    protected function applyLocationScope($builder, $userLocation)
    {
        $locationId = is_object($userLocation)
            ? $userLocation->getKey()
            : $userLocation;

        if (!is_array($locationId))
            $locationId = [$locationId];

        $relationName = $this->locationableRelationName();
        $relationObject = $this->getLocationableRelationObject();
        $locationModel = $relationObject->getRelated();

        if ($this->locationableIsSingleRelationType()) {
            $builder->whereIn($locationModel->getKeyName(), $locationId);
        }
        else {
            $qualifiedColumnName = $relationObject->getTable().'.'.$locationModel->getKeyName();
            $builder->whereHas($relationName, function ($query) use ($qualifiedColumnName, $locationId) {
                $query->whereIn($qualifiedColumnName, $locationId);
            });
        }
    }

    //
    //
    //

    protected function detachLocationsOnDelete()
    {
        if ($this->locationableIsSingleRelationType())
            return;

        $locationable = $this->getLocationableRelationObject();

        if (!app('admin.auth')->isSuperUser() AND $locationable->count() > 1) {
            throw new ApplicationException(lang('admin::lang.alert_warning_locationable_delete'));
        }

        $locationable->detach();
    }

    //
    //
    //

    protected function getLocationableRelationObject()
    {
        $relationName = $this->locationableRelationName();

        return $this->{$relationName}();
    }

    protected function locationableIsSingleRelationType()
    {
        $relationType = $this->getRelationType($this->locationableRelationName());

        return in_array($relationType, ['hasOne', 'belongsTo']);
    }

    protected function locationableRelationName()
    {
        return defined('static::LOCATIONABLE_RELATION') ? static::LOCATIONABLE_RELATION : 'location';
    }

    protected function locationableRelationExists()
    {
        $relationName = $this->locationableRelationName();

        if ($this->locationableIsSingleRelationType()) {
            return !is_null($this->{$relationName});
        }

        return count($this->{$relationName}) > 0;
    }
}
