<?php

/*
 * This file is part of the Predis package.
 *
 * (c) 2009-2020 Daniele Alessandri
 * (c) 2021-2025 Till Krüss
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command\Redis;

use Predis\Command\Argument\Search\SchemaFields\TextField;
use Predis\Response\ServerException;

class ACL_Test extends PredisCommandTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function getExpectedCommand(): string
    {
        return ACL::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function getExpectedId(): string
    {
        return 'ACL';
    }

    /**
     * @group disconnected
     */
    public function testSetUserFilterArguments(): void
    {
        $arguments = ['SETUSER', 'username', 'rule1', 'rule2'];
        $expected = ['SETUSER', 'username', 'rule1', 'rule2'];

        $command = $this->getCommand();
        $command->setArguments($arguments);

        $this->assertSameValues($expected, $command->getArguments());
    }

    /**
     * @group disconnected
     */
    public function testDryRunFilterArguments(): void
    {
        $arguments = ['DRYRUN', 'username', 'command', 'arg1', 'arg2'];
        $expected = ['DRYRUN', 'username', 'command', 'arg1', 'arg2'];

        $command = $this->getCommand();
        $command->setArguments($arguments);

        $this->assertSameValues($expected, $command->getArguments());
    }

    /**
     * @group disconnected
     */
    public function testGetUserFilterArguments(): void
    {
        $arguments = ['GETUSER', 'username'];
        $expected = ['GETUSER', 'username'];

        $command = $this->getCommand();
        $command->setArguments($arguments);

        $this->assertSameValues($expected, $command->getArguments());
    }

    /**
     * @group connected
     * @return void
     * @requiresRedisVersion >= 6.0.0
     */
    public function testSetUserCreatesACLUser(): void
    {
        $redis = $this->getClient();

        $this->assertEquals('OK', $redis->acl->setUser('Test'));
    }

    /**
     * @group connected
     * @return void
     * @requiresRedisVersion >= 7.0.0
     */
    public function testDryRunSimulateExecutionOfGivenCommandByUser(): void
    {
        $redis = $this->getClient();

        $this->assertEquals('OK', $redis->acl->setUser('Test', '+SET', '~*'));
        $this->assertEquals(
            'OK',
            $redis->acl->dryRun('Test', 'SET', 'foo', 'bar')
        );
        $this->assertEquals(
            "User Test has no permissions to run the 'get' command",
            $redis->acl->dryRun('Test', 'GET', 'foo')
        );
    }

    /**
     * @group connected
     * @return void
     * @requiresRedisVersion >= 6.0.0
     */
    public function testGetUserReturnsUserDefinedRules(): void
    {
        $redis = $this->getClient();

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'alan',
                'allkeys',
                '+@string',
                '+@set',
                '-SADD',
                '>alanpassword'
            )
        );

        foreach (['flags', 'passwords', 'commands', 'keys', 'channels'] as $key) {
            $this->assertContains($key, $redis->acl->getUser('alan'));
        }
    }

    /**
     * @group connected
     * @return void
     * @requiresRedisVersion >= 7.9.0
     */
    public function testModuleCategoriesAppearsInListOfAllCategories(): void
    {
        $redis = $this->getClient();
        $allCategories = $redis->acl->cat();

        foreach (['bloom', 'cuckoo', 'cms', 'topk', 'tdigest', 'search', 'timeseries', 'json'] as $category) {
            $this->assertContains($category, $allCategories);
        }
    }

    /**
     * @group connected
     * @group relay-incompatible
     * @return void
     * @requiresRedisVersion >= 7.9.0
     */
    public function testSetModuleCommandPrivileges(): void
    {
        $redis = $this->getClient();

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                'reset',
                'nopass',
                'on'
            )
        );

        $this->assertEquals('OK', $redis->auth('testUser', ''));

        $this->expectException(ServerException::class);
        $redis->ftcreate('test', [new TextField('foo')]);

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+ft.create',
                '+ft.search'
            )
        );

        $this->assertEquals('OK', $redis->ftcreate('test', [new TextField('foo')]));
        $this->assertEmpty($redis->ftsearch('test', '*'));

        $this->expectException(ServerException::class);
        $redis->jsonset('test', '$', '{"key":"value"}');

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+json.set'
            )
        );

        $this->assertEquals('OK', $redis->jsonset('test', '$', '{"key":"value"}'));

        $this->expectException(ServerException::class);
        $redis->bfadd('test', 'value');

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+bf.add'
            )
        );

        $this->assertEquals(1, $redis->bfadd('test', 'value'));

        $this->expectException(ServerException::class);
        $redis->tsadd('test', time(), 0.01);

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+ts.add'
            )
        );

        $this->assertEquals(1, $redis->tsadd('test', time(), 0.01));
    }

    /**
     * @group connected
     * @group relay-incompatible
     * @return void
     * @requiresRedisVersion >= 7.9.0
     */
    public function testSetModuleCategoryPrivileges(): void
    {
        $redis = $this->getClient();

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                'reset',
                'nopass',
                'on'
            )
        );

        $this->assertEquals('OK', $redis->auth('testUser', ''));

        $this->expectException(ServerException::class);
        $redis->ftcreate('test', [new TextField('foo')]);

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+@search'
            )
        );

        $this->assertEquals('OK', $redis->ftcreate('test', [new TextField('foo')]));
        $this->assertEmpty($redis->ftsearch('test', '*'));

        $this->expectException(ServerException::class);
        $redis->jsonset('test', '$', '{"key":"value"}');

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+@json'
            )
        );

        $this->assertEquals('OK', $redis->jsonset('test', '$', '{"key":"value"}'));

        $this->expectException(ServerException::class);
        $redis->bfadd('test', 'value');

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+@bloom'
            )
        );

        $this->assertEquals(1, $redis->bfadd('test', 'value'));

        $this->expectException(ServerException::class);
        $redis->tsadd('test', time(), 0.01);

        $this->assertEquals(
            'OK',
            $redis->acl->setUser(
                'testUser',
                '+@timeseries'
            )
        );

        $this->assertEquals(1, $redis->tsadd('test', time(), 0.01));
    }

    /**
     * @group connected
     * @return void
     * @requiresRedisVersion >= 6.0.0
     */
    public function testSetUserThrowsExceptionOnIncorrectRuleProvided(): void
    {
        $redis = $this->getClient();

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage("ERR Error in ACL SETUSER modifier 'foobar'");

        $redis->acl->setUser('Test', 'foobar');
    }
}
