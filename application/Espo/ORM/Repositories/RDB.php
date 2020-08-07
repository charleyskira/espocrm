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

namespace Espo\ORM\Repositories;

use Espo\ORM\{
    EntityManager,
    EntityFactory,
    Collection,
    Entity,
    Repository,
    DB\Mapper,
    RDBSelectBuilder as RDBSelectBuilder,
    QueryParams\Select,
};

use StdClass;
use RuntimeException;

class RDB extends Repository implements Findable, Relatable, Removable
{
    protected $mapper;

    private $isTableLocked = false;

    public function __construct(string $entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        $this->entityType = $entityType;
        $this->entityName = $entityType;

        $this->entityFactory = $entityFactory;
        $this->seed = $this->entityFactory->create($entityType);
        $this->entityClassName = get_class($this->seed);
        $this->entityManager = $entityManager;
    }

    protected function getMapper() : Mapper
    {
        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper('RDB');
        }
        return $this->mapper;
    }

    /**
     * @deprecated
     */
    public function handleSelectParams(&$params)
    {
    }

    /**
     * Get a new entity.
     */
    public function getNew() : ?Entity
    {
        $entity = $this->entityFactory->create($this->entityType);

        if ($entity) {
            $entity->setIsNew(true);
            $entity->populateDefaults();

            return $entity;
        }

        return null;
    }

    /**
     * Fetch an entity by ID.
     */
    public function getById(string $id, ?array $params = null) : ?Entity
    {
        $params = $params ?? [];

        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $builder = $this->getQueryBuilder()
            ->select()
            ->where([
                'id' => $id,
            ]);

        if (!empty($params['withDeleted'])) {
            $builder->withDeleted();
        }

        $select = $builder->build();

        return $this->getMapper()->selectOne($select);
    }

    public function get(?string $id = null) : ?Entity
    {
        if (is_null($id)) {
            return $this->getNew();
        }
        return $this->getById($id);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
    }

    public function save(Entity $entity, array $options = [])
    {
        $entity->setAsBeingSaved();

        if (empty($options['skipBeforeSave']) && empty($options['skipAll'])) {
            $this->beforeSave($entity, $options);
        }
        if ($entity->isNew() && !$entity->isSaved()) {
            $this->getMapper()->insert($entity);
        } else {
            $this->getMapper()->update($entity);
        }

        $entity->setIsSaved(true);

        if (empty($options['skipAfterSave']) && empty($options['skipAll'])) {
            $this->afterSave($entity, $options);
        }

        if ($entity->isNew()) {
            if (empty($options['keepNew'])) {
                $entity->setIsNew(false);
            }
        } else {
            if ($entity->isFetched()) {
                $entity->updateFetchedValues();
            }
        }

        $entity->setAsNotBeingSaved();
    }

    /**
     * Restore a record flagged as deleted.
     */
    public function restoreDeleted(string $id)
    {
        return $this->getMapper()->restoreDeleted($this->entityType, $id);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);
        $this->getMapper()->delete($entity);
        $this->afterRemove($entity, $options);
    }

    public function deleteFromDb(string $id, bool $onlyDeleted = false)
    {
        $this->getMapper()->deleteFromDb($this->entityType, $id, $onlyDeleted);
    }

    public function find(?array $params = null) : Collection
    {
        $params = $params ?? [];

        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $params['from'] = $this->entityType;

        $select = Select::fromRaw($params);

        $collection = $this->getMapper()->select($select);

        return $collection;
    }

    public function findOne(?array $params = null) : ?Entity
    {
        $params = $params ?? [];

        $collection = $this->limit(0, 1)->find($params);

        foreach ($collection as $entity) {
            return $entity;
        }

        return null;
    }

    /**
     * @todo Add QueryParams support?
     */
    public function findByQuery(string $sql) : Collection
    {
        return $this->getMapper()->selectByQuery($this->entityType, $sql);
    }

    public function findRelated(Entity $entity, string $relationName, ?array $params = null)
    {
        $params = $params ?? [];

        if (!$entity->id) {
            return null;
        }

        $type = $entity->getRelationType($relationName);
        $entityType = $entity->getRelationParam($relationName, 'entity');

        if ($entityType && empty($params['skipAdditionalSelectParams'])) {
            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
        }

        $select = null;

        if ($entityType) {
            $params['from'] = $entityType;
            $select = Select::fromRaw($params);
        }

        $result = $this->getMapper()->selectRelated($entity, $relationName, $select);

        return $result;
    }

    public function countRelated(Entity $entity, string $relationName, ?array $params = null) : int
    {
        $params = $params ?? [];

        if (!$entity->id) {
            return 0;
        }

        $type = $entity->getRelationType($relationName);
        $entityType = $entity->getRelationParam($relationName, 'entity');

        if ($entityType && empty($params['skipAdditionalSelectParams'])) {
            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
        }

        $select = null;

        if ($entityType) {
            $params['from'] = $entityType;
            $select = Select::fromRaw($params);
        }

        return (int) $this->getMapper()->countRelated($entity, $relationName, $select);
    }

    public function isRelated(Entity $entity, string $relationName, $foreign) : bool
    {
        if (!$entity->id) {
            return false;
        }

        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else if (is_string($foreign)) {
            $id = $foreign;
        } else {
            throw new RuntimeException("Bad 'foreign' value.");
        }

        if (!$id) {
            return false;
        }

        if ($entity->getRelationType($relationName) === Entity::BELONGS_TO) {
            $foreignEntityType = $entity->getRelationParam($relationName, 'entity');

            if (!$foreignEntityType) {
                return false;
            }

            $foreignId = $entity->get($relationName . 'Id');

            if (!$foreignId) {
                $e = $this->select([$relationName . 'Id'])->where(['id' => $entity->id])->findOne();
                if ($e) {
                    $foreignId = $e->get($relationName . 'Id');
                }
            }

            if (!$foreignId) {
                return false;
            }

            $foreignEntity = $this->getEntityManager()->getRepository($foreignEntityType)
                ->select(['id'])
                ->where(['id' => $foreignId])
                ->findOne();

            if (!$foreignEntity) {
                return false;
            }

            return $foreignEntity->id === $id;
        }

        // @todo Use related builder.
        return (bool) $this->countRelated($entity, $relationName, [
            'whereClause' => [
                'id' => $id,
            ],
        ]);
    }

    public function relate(Entity $entity, string $relationName, $foreign, $columnData = null, array $options = [])
    {
        if (!$entity->id) {
            throw new RuntimeException("Can't relate an entity w/o ID.");
        }

        if (! $foreign instanceof Entity && !is_string($foreign)) {
            throw new RuntimeException("Bad 'foreign' value.");
        }

        $this->beforeRelate($entity, $relationName, $foreign, $columnData, $options);

        $beforeMethodName = 'beforeRelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $columnData, $options);
        }

        $result = false;

        $methodName = 'relate' . ucfirst($relationName);

        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign, $columnData, $options);
        } else {
            $data = $columnData;

            if ($columnData instanceof StdClass) {
                $data = get_object_vars($columnData);
            }

            if ($foreign instanceof Entity) {
                $id = $foreign->id;
            } else {
                $id = $foreign;
            }

            $result = $this->getMapper()->relateById($entity, $relationName, $id, $data);
        }

        if ($result) {
            $this->afterRelate($entity, $relationName, $foreign, $columnData, $options);
            $afterMethodName = 'afterRelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $columnData, $options);
            }
        }

        return $result;
    }

    public function unrelate(Entity $entity, string $relationName, $foreign, array $options = [])
    {
        if (!$entity->id) {
            throw new RuntimeException("Can't unrelate an entity w/o ID.");
        }

        if (! $foreign instanceof Entity && !is_string($foreign)) {
            throw new RuntimeException("Bad foreign value.");
        }

        $this->beforeUnrelate($entity, $relationName, $foreign, $options);

        $beforeMethodName = 'beforeUnrelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $options);
        }

        $result = false;

        $methodName = 'unrelate' . ucfirst($relationName);

        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign);
        } else {
            if ($foreign instanceof Entity) {
                $id = $foreign->id;
            } else {
                $id = $foreign;
            }

            $result = $this->getMapper()->unrelateById($entity, $relationName, $id);
        }

        if ($result) {
            $this->afterUnrelate($entity, $relationName, $foreign, $options);

            $afterMethodName = 'afterUnrelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $options);
            }
        }

        return $result;
    }

    public function getRelationColumn(Entity $entity, string $relationName, string $foreignId, string $column)
    {
        return $this->getMapper()->getRelationColumn($entity, $relationName, $foreignId, $column);
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
    }

    protected function beforeMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
    }

    protected function afterMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
    }

    /**
     * Update relationship columns.
     */
    public function updateRelation(Entity $entity, string $relationName, $foreign, $columnData)
    {
        if (!$entity->id) {
            throw new RuntimeException("Can't update a relation for an entity w/o ID.");
        }

        if (! $foreign instanceof Entity && !is_string($foreign)) {
            throw new RuntimeException("Bad foreign value.");
        }

        if ($columnData instanceof StdClass) {
            $columnData = get_object_vars($columnData);
        }

        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else {
            $id = $foreign;
        }

        if (!is_string($id)) {
            throw new RuntimeException("Bad foreign value.");
        }

        return $this->getMapper()->updateRelation($entity, $relationName, $id, $columnData);
    }

    public function massRelate(Entity $entity, string $relationName, array $params = [], array $options = [])
    {
        if (!$entity->id) {
            throw new RuntimeException("Can't related an entity w/o ID.");
        }

        $this->beforeMassRelate($entity, $relationName, $params, $options);

        $select = Select::fromRaw($params);

        $this->getMapper()->massRelate($entity, $relationName, $select);

        $this->afterMassRelate($entity, $relationName, $params, $options);
    }

    public function count(?array $params = null) : int
    {
        $params = $params ?? [];

        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $select = Select::fromRaw($params);

        $count = $this->getMapper()->count($select);

        return (int) $count;
    }

    public function max(string $attribute, ?array $params = null)
    {
        $params = $params ?? [];

        $select = Select::fromRaw($params);

        return $this->getMapper()->max($select, $attribute);
    }

    public function min(string $attribute, ?array $params = null)
    {
        $params = $params ?? [];

        $select = Select::fromRaw($params);

        return $this->getMapper()->min($select, $attribute);
    }

    public function sum(string $attribute, ?array $params = null)
    {
        $params = $params ?? [];

        $select = Select::fromRaw($params);

        return $this->getMapper()->sum($select, $attribute);
    }

    /**
     * Add JOIN.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::join()
     */
    public function join($relationName, ?string $alias = null, ?array $conditions = null) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->join($relationName, $alias, $conditions);
    }

    /**
     * Add LEFT JOIN.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::leftJoin()
     */
    public function leftJoin($relationName, ?string $alias = null, ?array $conditions = null) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->leftJoin($relationName, $alias, $conditions);
    }

    /**
     * Set DISTINCT parameter.
     */
    public function distinct() : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->distinct();
    }

    /**
     * Set to return STH collection. Recommended fetching large number of records.
     *
     * @todo Remove.
     */
    public function sth() : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->sth();
    }

    /**
     * Add a WHERE clause.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::where()
     */
    public function where($param1 = [], $param2 = null) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->where($param1, $param2);
    }

    /**
     * Add a HAVING clause.
     *
     * @see Espo\ORM\QueryParams\SelectBuilder::having()
     */
    public function having($param1 = [], $param2 = null) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->having($param1, $param2);
    }

    /**
     * Apply ORDER.
     *
     * @param string|array $attribute An attribute to order by or order definitions as an array.
     * @param bool|string $direction TRUE for DESC order.
     */
    public function order($attribute = 'id', $direction = 'ASC') : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->order($attribute, $direction);
    }

    /**
     * Apply OFFSET and LIMIT.
     */
    public function limit(?int $offset = null, ?int $limit = null) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->limit($offset, $limit);
    }

    /**
     * Specify SELECT. Which attributes to select. All attributes are selected by default.
     */
    public function select(array $select = []) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->select($select);
    }

    /**
     * Specify GROUP BY.
     */
    public function groupBy(array $groupBy) : RDBSelectBuilder
    {
        return $this->createSelectBuilder()->groupBy($groupBy);
    }

    protected function getPDO()
    {
        return $this->getEntityManager()->getPDO();
    }

    protected function lockTable()
    {
        $tableName = $this->getEntityManager()->getQuery()->toDb($this->entityType);

        // @todo Use Query to get SQL. Transaction query params.
        $this->getPDO()->query("LOCK TABLES `{$tableName}` WRITE");
        $this->isTableLocked = true;
    }

    protected function unlockTable()
    {
        // @todo Use Query to get SQL.
        $this->getPDO()->query("UNLOCK TABLES");
        $this->isTableLocked = false;
    }

    protected function isTableLocked()
    {
        return $this->isTableLocked;
    }

    protected function createSelectBuilder() : RDBSelectBuilder
    {
        $builder = new RDBSelectBuilder($this->getEntityManager());
        $builder->from($this->getEntityType());

        return $builder;
    }
}
