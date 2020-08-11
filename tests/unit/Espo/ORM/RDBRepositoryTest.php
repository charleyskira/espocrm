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

require_once 'tests/unit/testData/DB/Entities.php';

use Espo\ORM\{
    Mapper\MysqlMapper,
    Repository\RDBRepository as Repository,
    Repository\RDBRelation,
    Repository\RDBRelationSelectBuilder,
    EntityCollection,
    QueryParams\Select,
    QueryBuilder,
    Entity,
    EntityManager,
    EntityFactory,
    CollectionFactory,
};

use RuntimeException;

use tests\unit\testData\Entities\Test;

use Espo\Entities;

class RDBRepositoryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $entityManager = $this->entityManager =
            $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();

        $entityFactory = $this->entityFactory =
        $this->getMockBuilder(EntityFactory::class)->disableOriginalConstructor()->getMock();

        $this->collectionFactory = new CollectionFactory($this->entityManager);

        $this->mapper = $this->getMockBuilder(MysqlMapper::class)->disableOriginalConstructor()->getMock();

        $entityManager
            ->method('getMapper')
            ->will($this->returnValue($this->mapper));

        $this->queryBuilder = new QueryBuilder();

        $entityManager
            ->method('getQueryBuilder')
            ->will($this->returnValue($this->queryBuilder));

        $entityManager
            ->method('getQueryBuilder')
            ->will($this->returnValue($this->queryBuilder));

        $entityManager
            ->method('getCollectionFactory')
            ->will($this->returnValue($this->collectionFactory));

        $entity = $this->seed = $this->createEntity('Test', Test::class);

        $this->account = $this->createEntity('Account', Entities\Account::class);
        $this->team = $this->createEntity('Team', Entities\Team::class);

        $this->collection = $this->createCollectionMock();

        $entityFactory
            ->method('create')
            ->will(
                $this->returnCallback(
                    function (string $entityType) {
                        $className = 'Espo\\Entities\\' . ucfirst($entityType);

                        return $this->createEntity($entityType, $className);
                    }
                )
            );

        $this->repository = $this->createRepository('Test');
    }

    protected function createCollectionMock() : EntityCollection
    {
        return $this->getMockBuilder(EntityCollection::class)->disableOriginalConstructor()->getMock();
    }

    protected function createRepository(string $entityType)
    {
        $repository = new Repository($entityType, $this->entityManager, $this->entityFactory);

        $this->entityManager
            ->method('getRepository')
            ->will($this->returnValue($repository));

        return $repository;
    }

    protected function createEntity(string $entityType, string $className)
    {
        return new $className($entityType, [], $this->entityManager);
    }

    /**
     * @deprecated
     */
    public function testFind()
    {
        $params = [
            'whereClause' => [
                'name' => 'test',
            ],
        ];

        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                'name' => 'test',
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->find($params);
    }

    public function testFindOne1()
    {
        $params = [
            'whereClause' => [
                'name' => 'test',
            ],
        ];

        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                'name' => 'test',
            ],
            'offset' => 0,
            'limit' => 1,
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->findOne($params);
    }

    public function testFindOne2()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->where(['name' => 'test'])
            ->limit(0, 1)
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($select);

        $this->repository->where(['name' => 'test'])->findOne();
    }

    public function testFindOne3()
    {
        $select = $this->queryBuilder
            ->select()
            ->distinct()
            ->from('Test')
            ->where(['name' => 'test'])
            ->limit(0, 1)
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($select);

        $this->repository->distinct()->findOne([
            'whereClause' => ['name' => 'test'],
        ]);
    }

    /**
     * @deprecated
     */
    public function testCount1()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->where(['name' => 'test'])
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
            ->with($select);

        $this->repository->count([
            'whereClause' => ['name' => 'test'],
        ]);
    }

    public function testCount2()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->where(['name' => 'test'])
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
            ->with($select);

        $this->repository->where(['name' => 'test'])->count();
    }

    public function testCount3()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(1))
            ->with($select);

        $this->repository->count();
    }

    public function testMax1()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('max')
            ->will($this->returnValue(1))
            ->with($select, 'test');

        $this->repository->max('test');
    }

    public function testWhere1()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                'name' => 'test',
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->where(['name' => 'test'])->find();
    }

    public function testWhere2()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                ['name' => 'test'],
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->where('name', 'test')->find();
    }

    public function testWhereMerge()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                'name2' => 'test2',
                ['name1' => 'test1'],
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository
            ->where(['name1' => 'test1'])
            ->find([
                'whereClause' => ['name2' => 'test2'],
            ]);
    }

    public function testWhereFineOne()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'whereClause' => [
                ['name' => 'test'],
            ],
            'offset' => 0,
            'limit' => 1,
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->where('name', 'test')->findOne();
    }

    public function testJoin1()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'joins' => [
                'Test',
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->join('Test')->find();
    }

    public function testJoin2()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'joins' => [
                'Test1',
                'Test2',
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->join(['Test1', 'Test2'])->find();
    }

    public function testJoin3()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'joins' => [
                ['Test1', 'test1'],
                ['Test2', 'test2'],
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->join([['Test1', 'test1'], ['Test2', 'test2']])->find();
    }

    public function testJoin4()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'joins' => [
                ['Test1', 'test1', ['k' => 'v']],
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->join('Test1', 'test1', ['k' => 'v'])->find();
    }

    public function testLeftJoin1()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'leftJoins' => [
                'Test',
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->leftJoin('Test')->find();
    }

    public function testMultipleLeftJoins()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'leftJoins' => [
                'Test1',
                ['Test2', 'test2'],
            ],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->leftJoin('Test1')->leftJoin('Test2', 'test2')->find();
    }

    public function testDistinct()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'distinct' => true,
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->distinct()->find();
    }

    public function testSth()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'returnSthCollection' => true,
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->sth()->find();
    }

    public function testOrder1()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'orderBy' => 'name',
            'order' => 'ASC',
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->order('name')->find();
    }

    public function testSelect1()
    {
        $paramsExpected = Select::fromRaw([
            'from' => 'Test',
            'select' => ['name', 'date'],
        ]);

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($paramsExpected);

        $this->repository->select(['name', 'date'])->find();
    }

    public function testSelect2()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->select(['name'])
            ->select('date')
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue($this->collection))
            ->with($select);

        $this->repository
            ->select(['name'])
            ->select('date')
            ->find();
    }

    public function testFindRelated1()
    {
        $select = Select::fromRaw([
            'from' => 'Team',
        ]);

        $this->account->id = 'accountId';

        $this->mapper
            ->expects($this->once())
            ->method('selectRelated')
            ->will($this->returnValue(new EntityCollection()))
            ->with($this->account, 'teams', $select);

        $this->createRepository('Account')->findRelated($this->account, 'teams');
    }

    public function testCountRelated1()
    {
        $select = Select::fromRaw([
            'from' => 'Team',
        ]);

        $this->account->id = 'accountId';

        $this->mapper
            ->expects($this->once())
            ->method('countRelated')
            ->will($this->returnValue(1))
            ->with($this->account, 'teams', $select);

        $this->createRepository('Account')->countRelated($this->account, 'teams');
    }

    public function testAdditionalColumns()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Team')
            ->select(['*', ['entityTeam.deleted', 'teamDeleted']])
            ->build();

        $this->account->id = 'accountId';

        $this->mapper
            ->expects($this->once())
            ->method('selectRelated')
            ->will($this->returnValue(new EntityCollection()))
            ->with($this->account, 'teams', $select);

        $this->createRepository('Account')->findRelated($this->account, 'teams', [
            'additionalColumns' => [
                'deleted' => 'teamDeleted',
            ],
        ]);
    }

    public function testAdditionalColumnsConditions()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Team')
            ->where([
                ['entityTeam.teamId' => 'testId'],
            ])
            ->build();

        $this->account->id = 'accountId';

        $this->mapper
            ->expects($this->once())
            ->method('selectRelated')
            ->will($this->returnValue(new EntityCollection()))
            ->with($this->account, 'teams', $select);

        $this->createRepository('Account')->findRelated($this->account, 'teams', [
            'additionalColumnsConditions' => [
                'teamId' => 'testId',
            ],
        ]);
    }

    public function testClone1()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->build();

        $selectExpected = $this->queryBuilder
            ->select()
            ->from('Test')
            ->select('id')
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('select')
            ->will($this->returnValue(new EntityCollection()))
            ->with($selectExpected);

        $this->repository
            ->clone($select)
            ->select('id')
            ->find();
    }

    public function testGetById1()
    {
        $select = $this->queryBuilder
            ->select()
            ->from('Test')
            ->where(['id' => '1'])
            ->build();

        $entity = $this->getMockBuilder(Entity::class)->getMock();

        $this->mapper
            ->expects($this->once())
            ->method('selectOne')
            ->will($this->returnValue($entity))
            ->with($select);

        $this->repository->getById('1');
    }

    public function testRelationInstance()
    {
        $repository = $this->createRepository('Account');

        $account = $this->entityFactory->create('Account');
        $account->id = 'accountId';

        $relation = $repository->getRelation($account, 'teams');

        $this->assertInstanceOf(RDBRelation::class, $relation);
    }

    public function testRelationCloneInstance()
    {
        $repository = $this->createRepository('Account');

        $account = $this->entityFactory->create('Account');
        $account->id = 'accountId';

        $select = $this->queryBuilder
            ->select()
            ->from('Team')
            ->build();

        $relationSelectBuilder = $repository->getRelation($account, 'teams')->clone($select);

        $this->assertInstanceOf(RDBRelationSelectBuilder::class, $relationSelectBuilder);
    }

    public function testRelationCloneBelongsToParentException()
    {
        $repository = $this->createRepository('Note');

        $note = $this->entityFactory->create('Note');
        $note->id = 'noteId';

        $select = $this->queryBuilder
            ->select()
            ->from('Post')
            ->build();

        $this->expectException(RuntimeException::class);

        $relationSelectBuilder = $repository->getRelation($note, 'parent')->clone($select);
    }

    public function testRelationSelectBuilderFind1()
    {
        $repository = $this->createRepository('Post');

        $post = $this->entityFactory->create('Post');
        $post->id = 'postId';

        $collection = $this->createCollectionMock();

        $select = $this->queryBuilder
            ->select()
            ->from('Comment')
            ->select(['id'])
            ->distinct()
            ->where(['name' => 'test'])
            ->join('Test', 'test', ['id:' => 'id'])
            ->order('id', 'DESC')
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('selectRelated')
            ->will($this->returnValue($collection))
            ->with($post, 'comments', $select);

        $repository->getRelation($post, 'comments')
            ->select(['id'])
            ->distinct()
            ->where(['name' => 'test'])
            ->join('Test', 'test', ['id:' => 'id'])
            ->order('id', 'DESC')
            ->find();
    }

    public function testRelationSelectBuilderFindOne1()
    {
        $repository = $this->createRepository('Post');

        $post = $this->entityFactory->create('Post');
        $post->id = 'postId';

        $collection = $this->collectionFactory->create();

        $comment = $this->entityFactory->create('Comment');
        $comment->set('id', 'commentId');

        $collection[] = $comment;

        $select = $this->queryBuilder
            ->select()
            ->from('Comment')
            ->select(['id'])
            ->distinct()
            ->where(['name' => 'test'])
            ->join('Test', 'test', ['id:' => 'id'])
            ->order('id', 'DESC')
            ->limit(0, 1)
            ->build();

        $this->mapper
            ->expects($this->once())
            ->method('selectRelated')
            ->will($this->returnValue($collection))
            ->with($post, 'comments', $select);

        $result = $repository->getRelation($post, 'comments')
            ->select(['id'])
            ->distinct()
            ->where(['name' => 'test'])
            ->join('Test', 'test', ['id:' => 'id'])
            ->order('id', 'DESC')
            ->findOne();

        $this->assertEquals($comment, $result);
    }
}
