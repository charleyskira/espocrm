<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\Repository;

use Espo\ORM\{
    Collection,
    Entity,
    EntityManager,
    QueryParams\Select,
    QueryParams\SelectBuilder,
    Mapper\Mapper,
};

use RuntimeException;
use BadMethodCallException;

/**
 * Builds select parameters for related records for RDB repository.
 */
class RDBRelatedSelectBuilder implements Findable
{
    protected $entityManager;

    protected $entityType;

    protected $foreignEntityType = null;

    protected $relationName;

    protected $relationType = null;

    protected $builder = null;

    protected $noBuilder = false;

    private $isDirty = false;

    protected $additionalSelect = [];

    public function __construct(EntityManager $entityManager, string $entityType, string $relationName)
    {
        $this->entityManager = $entityManager;

        $this->entityType = $entityType;

        $this->relationName = $relationName;

        $this->relationType = $entity->getRelationType($relationName);

        if ($this->relationType === Entity::BELONGS_TO_PARENT) {
            $this->noBuilder = true;
        }

        if (!$this->noBuilder) {
            $this->foreignEntityType = $entity->getRelationParam($relationName, 'entity');

            $this->builder = $this->createSelectBuilder()->from($this->foreignEntityType);
        }
    }

    protected function createSelectBuilder() : SelectBuilder
    {
        return new SelectBuilder($this->entityManager->getQueryComposer());
    }

    /**
     * Clone a query.
     */
    public function clone(Select $query) : self
    {
        $this->processBuilderCheck();

        if ($query->getFrom() !== $this->foreignEntityType) {
            throw new RuntimeException("RealtedSelectBuilder: Passed query doesn't match the entity type.");
        }

        $this->builder = $this->createSelectBuilder()->clone($query);

        $this->setAsDirty();

        return $this;
    }

    protected function setAsDirty()
    {
        $this->isDirty = true;
    }

    protected function processBuilderCheck()
    {
        if ($this->noBuilder) {
            throw new RuntimeException("RealtedSelectBuilder: Can't use query builder for the '{$this->relationType}' relation type.");
        }

        if ($this->isDirty) {
            throw new RuntimeException("RealtedSelectBuilder: Can't modify a query.");
        }
    }

    protected function getMapper() : Mapper
    {
        return $this->entityManager->getMapper();
    }

    /**
     * Additional middle table columns. Only for many-to-many relationships.
     *
     * Usage:
     * `['columnName' => 'attributeName']`
     */
    public function columns(array $columns) : self
    {
        if (!count($columns)) {
            return $this;
        }

        if ($this->relationType !== Entity::MANY_MANY) {
            throw new RuntimeException("Can't select relation columns for not many-to-many relationship.");
        }

        $middleName = lcfirst(
            $this->entityManager->getMetadata()->get($this->entityType, ['relations', $this->relationName, 'relationName'])
        );

        foreach ($columns as $column => $alias) {
            $this->additionalSelect[] = [
                $middleName . '.' . $column,
                $alias,
            ];
        }

        return $this;
    }

    /**
     * @param $params @deprecated. Omit it.
     * @return ?Collection|Entity
     */
    public function find(?array $params = null)
    {
    }

    /**
     * @param $params @deprecated. Omit it.
     */
    public function count(?array $params = null) : int
    {

    }


    /**
     * Add JOIN.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::join()
     */
    public function join($relationName, ?string $alias = null, ?array $conditions = null) : self
    {
        if (!$this->builder) {
            throw new BadMethodCallException("Can't compose a query for a relation of '{$this->relationType}' type.");
        }

        $this->builder->join($relationName, $alias, $conditions);

        return $this;
    }

    /**
     * Add LEFT JOIN.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::leftJoin()
     */
    public function leftJoin($relationName, ?string $alias = null, ?array $conditions = null) : self
    {
        $this->builder->leftJoin($relationName, $alias, $conditions);

        return $this;
    }

    /**
     * Set DISTINCT parameter.
     */
    public function distinct() : self
    {
        $this->builder->distinct();

        return $this;
    }

    /**
     * Set to return STH collection. Recommended for fetching large number of records.
     *
     * @todo Remove.
     */
    public function sth() : self
    {
        $this->builder->sth();

        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::where()
     */
    public function where($param1 = [], $param2 = null) : self
    {
        $this->builder->where($param1, $param2);

        return $this;
    }

    /**
     * Add a HAVING clause.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::having()
     */
    public function having($param1 = [], $param2 = null) : self
    {
        $this->builder->having($param1, $params2);

        return $this;
    }

    /**
     * Apply ORDER.
     *
     * @param string|array $orderBy An attribute to order by or order definitions as an array.
     * @param bool|string $direction TRUE for DESC order.
     */
    public function order($orderBy = 'id', $direction = 'ASC') : self
    {
        $this->builder->order($orderBy, $direction);

        return $this;
    }

    /**
     * Apply OFFSET and LIMIT.
     */
    public function limit(?int $offset = null, ?int $limit = null) : self
    {
        $this->builder->limit($offset, $limit);

        return $this;
    }

    /**
     * Specify SELECT. Which attributes to select. All attributes are selected by default.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::select()
     *
     * @param array|string $select
     */
    public function select($select, ?string $alias = null) : self
    {
        $this->builder->select($select, $alias);

        return $this;
    }

    /**
     * Specify GROUP BY.
     */
    public function groupBy(array $groupBy) : self
    {
        $this->builder->groupBy($groupBy);

        return $this;
    }

    /**
     * For backward compatibility.
     * @todo Remove.
     */
    protected function getMergedParams(?array $params = null) : Select
    {
        if (!$params || empty($params)) {
            return $this->builder->build();
        }

        $params = $params ?? [];

        $builtParams = $this->builder->build()->getRawParams();

        $whereClause = $builtParams['whereClause'] ?? [];
        $havingClause = $builtParams['havingClause'] ?? [];
        $joins = $builtParams['joins'] ?? [];
        $leftJoins = $builtParams['leftJoins'] ?? [];

        if (!empty($params['whereClause'])) {
            unset($builtParams['whereClause']);
            if (count($whereClause)) {
                $params['whereClause'][] = $whereClause;
            }
        }

        if (!empty($params['havingClause'])) {
            unset($builtParams['havingClause']);
            if (count($havingClause)) {
                $params['havingClause'][] = $havingClause;
            }
        }

        if (empty($params['whereClause'])) {
            unset($params['whereClause']);
        }

        if (empty($params['havingClause'])) {
            unset($params['havingClause']);
        }

        if (!empty($params['leftJoins']) && !empty($leftJoins)) {
            foreach ($leftJoins as $j) {
                $params['leftJoins'][] = $j;
            }
        }

        if (!empty($params['joins']) && !empty($joins)) {
            foreach ($joins as $j) {
                $params['joins'][] = $j;
            }
        }

        $params = array_replace_recursive($builtParams, $params);

        return Select::fromRaw($params);
    }
}
