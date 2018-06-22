<?php

namespace Doctrine\Tests\Common\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\LegacyMongoDBCache;
use Doctrine\Common\Cache\MongoDBCache;
use MongoClient;
use MongoCollection;
use MongoConnectionException;

/**
 * @requires extension mongodb
 */
class LegacyMongoDBCacheTest extends CacheTest
{
    /**
     * @var MongoCollection
     */
    private $collection;

    protected function setUp() : void
    {
        try {
            $mongo = new MongoClient();
            $mongo->listDBs();
        } catch (MongoConnectionException $e) {
            $this->markTestSkipped('Cannot connect to MongoDB because of: ' . $e);
        }

        $this->collection = $mongo->selectCollection('doctrine_common_cache', 'test');
    }

    protected function tearDown() : void
    {
        if ($this->collection instanceof MongoCollection) {
            $this->collection->drop();
        }
    }

    public function testGetStats() : void
    {
        $cache = $this->_getCacheDriver();
        $stats = $cache->getStats();

        $this->assertNull($stats[Cache::STATS_HITS]);
        $this->assertNull($stats[Cache::STATS_MISSES]);
        $this->assertGreaterThan(0, $stats[Cache::STATS_UPTIME]);
        $this->assertEquals(0, $stats[Cache::STATS_MEMORY_USAGE]);
        $this->assertNull($stats[Cache::STATS_MEMORY_AVAILABLE]);
    }

    /**
     * @group 108
     */
    public function testMongoCursorExceptionsDoNotBubbleUp() : void
    {
        /* @var $collection \MongoCollection|\PHPUnit_Framework_MockObject_MockObject */
        $collection = $this
            ->getMockBuilder(\MongoCollection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection->expects(self::once())->method('update')->willThrowException(new \MongoCursorException());

        $cache = new LegacyMongoDBCache($collection);

        self::assertFalse($cache->save('foo', 'bar'));
    }

    public function testLifetime() : void
    {
        $cache = $this->_getCacheDriver();
        $cache->save('expire', 'value', 1);
        $this->assertCount(1, $this->collection->getIndexInfo());
        $this->assertTrue($cache->contains('expire'), 'Data should not be expired yet');
        sleep(2);
        $this->assertFalse($cache->contains('expire'), 'Data should be expired');
        $this->assertCount(2, $this->collection->getIndexInfo());
    }

    protected function _getCacheDriver() : CacheProvider
    {
        return new MongoDBCache($this->collection);
    }
}
