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

namespace Espo\ORM;

use Espo\Core\Exceptions\Error;

use Espo\ORM\{
    Repositories\Findable,
    Collection,
    Entity,
    QueryParams\Select,
    QueryParams\SelectBuilder,
};

/**
 * Builds select parameters for an RDB repository. Contains 'find' methods.
 */
class RDBSelectBuilder implements Findable
{
    protected $entityManager;

    protected $builder;

    protected $repository = null;

    protected $entityType = null;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->builder = new SelectBuilder($entityManager->getQuery());
    }

    public function from(string $entityType) : self
    {
        $this->builder->from($entityType);

        $this->entityType = $entityType;

        $this->repository = $this->entityManager->getRepository($entityType);

        return $this;
    }

    protected function isExecutable() : bool
    {
        return (bool) $this->entityType;
    }

    protected function processExecutableCheck()
    {
        if (!$this->isExecutable()) {
            throw new Error("SelectBuilder: Method 'from' must be called.");
        }
    }

    public function find(?array $params = null) : Collection
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams($params);

        return $this->repository->find($params);
    }

    public function findOne(?array $params = null) : ?Entity
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams($params);

        return $this->repository->findOne($params);
    }

    public function count(?array $params = null) : int
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams($params);

        return $this->repository->count($params);
    }

    public function max(string $attribute)
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams();

        return $this->repository->max($attribute, $params);
    }

    public function min(string $attribute)
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams();

        return $this->repository->min($attribute, $params);
    }

    public function sum(string $attribute)
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams();

        return $this->repository->sum($attribute, $params);
    }

    /**
     * Add JOIN.
     *
     * @param string|array $relationName A relationName or table. A relationName is in camelCase, a table is in CamelCase.
     *
     * Usage options:
     * * `join(string $relationName)`
     * * `join(array $joinDefinitionList)`
     *
     * Usage examples:
     * ```
     * ->join($relationName)
     * ->join($relationName, $alias, $conditions)
     * ->join([$relationName1, $relationName2, ...])
     * ->join([[$relationName, $alias], ...])
     * ->join([[$relationName, $alias, $conditions], ...])
     * ```
     */
    public function join($relationName, ?string $alias = null, ?array $conditions = null) : self
    {
        $this->builder->join($relationName, $alias, $conditions);

        return $this;
    }

    /**
     * Add LEFT JOIN.
     *
     * @param string|array $relationName A relationName or table. A relationName is in camelCase, a table is in CamelCase.
     *
     * This method works the same way as `join` method.
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
     */
    public function sth() : self
    {
        $this->builder->sth();

        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * Two usage options:
     * * `where(array $whereClause)`
     * * `where(string $key, string $value)`
     */
    public function where($param1 = [], $param2 = null) : self
    {
        $this->builder->where($param1, $param2);

        return $this;
    }

    /**
     * Add a HAVING clause.
     *
     * Two usage options:
     * * `having(array $havingClause)`
     * * `having(string $key, string $value)`
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
     */
    public function select(array $select) : self
    {
        $this->builder->select($select);

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
     * Builds result select parameters.
     */
    public function build() : Select
    {
        $this->processExecutableCheck();

        $params = $this->getMergedParams();

        return Select::fromRaw($params);
    }

    protected function getMergedParams(?array $params = null) : array
    {
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

        return $params;
    }
}