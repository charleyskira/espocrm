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

namespace Espo\ORM\QueryParams;

class SelectBuilder implements Builder
{
    use SelectingBuilderTrait {
        getMergedRawParams as protected getMergedRawParamsSelecting;
        isEmpty as protected isEmptySelecting;
    }

    protected $havingClause = [];

    /**
     * Build a SELECT query.
     */
    public function build() : Select
    {
        $params = $this->getMergedRawParams();

        return Select::fromRaw($params);
    }

    /**
     * Clone an existing query for a subsequent modifying and building.
     */
    public function clone(Select $query) : self
    {
        $this->cloneInternalSelecting($query);

        $this->havingClause = $this->params['havingClause'] ?? [];

        unset($this->params['havingClause']);

        return $this;
    }

    protected function isEmpty() : bool
    {
        return empty($this->params) && empty($this->whereClause) && empty($this->havingClause);
    }

    /**
     * Set to return STH collection. Recommended for fetching large number of records.
     *
     * @todo Remove.
     */
    public function sth() : self
    {
        $this->params['returnSthCollection'] = true;

        return $this;
    }

    /**
     * Apply OFFSET and LIMIT.
     */
    public function limit(?int $offset = null, ?int $limit = null) : self
    {
        $this->params['offset'] = $offset;
        $this->params['limit'] = $limit;

        return $this;
    }

    /**
     * Specify SELECT. Columns and expressions to be selected. If omitted, then all entity attributes will be selected.
     */
    public function select(array $select) : self
    {
        $this->params['select'] = $select;

        return $this;
    }

    /**
     * Specify GROUP BY.
     */
    public function groupBy(array $groupBy) : self
    {
        $this->params['groupBy'] = $groupBy;

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
        if (is_array($param1)) {
            $this->havingClause = $param1 + $this->havingClause;

            return $this;
        }

        if (!is_null($param2)) {
            $this->havingClause[] = [$param1 => $param2];

            return $this;
        }

        throw new BadMethodCallException();
    }

    /**
     * @todo Remove?
     */
    public function withDeleted() : self
    {
        $this->params['withDeleted'] = true;

        return $this;
    }

    protected function getMergedRawParams() : array
    {
        $params = [];

        $params['whereClause'] = $this->whereClause;
        $params['havingClause'] = $this->havingClause;

        if (empty($params['whereClause'])) {
            unset($params['whereClause']);
        }

        if (empty($params['havingClause'])) {
            unset($params['havingClause']);
        }

        $params = array_replace_recursive($this->params, $params);

        return $params;
    }
}
