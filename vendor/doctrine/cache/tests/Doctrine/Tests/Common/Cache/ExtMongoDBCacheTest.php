<?php

namespace Doctrine\Tests\Common\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\MongoDBCache;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception;

/**
 * @requires extension mongodb
 */
class ExtMongoDBCacheTest extends CacheTest
{
    /**
     * @var Collection
     */
    private $collection;

    protected function setUp(): void
    {
        try {
            $mongo = new Client();
            $mongo->listDatabases();
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to MongoDB because of: ' . $e);
        }

        $this->collection = $mongo->selectCollection('doctrine_common_cache', 'test');
    }

    protected function tearDown(): void
    {
        if ($this->collection instanceof Collection) {
            $this->collection->drop();
        }
    }

    public function testGetStats(): void
    {
        $cache = $this->_getCacheDriver();
        // Run a query to create the collection
        $this->collection->find([]);
        $stats = $cache->getStats();

        $this->assertNull($stats[Cache::STATS_HITS]);
        $this->assertNull($stats[Cache::STATS_MISSES]);
        $this->assertGreaterThan(0, $stats[Cache::STATS_UPTIME]);
        $this->assertEquals(0, $stats[Cache::STATS_MEMORY_USAGE]);
        $this->assertNull($stats[Cache::STATS_MEMORY_AVAILABLE]);
    }

    public function testLifetime() : void
    {
        $cache = $this->_getCacheDriver();
        $cache->save('expire', 'value', 1);
        $this->assertCount(1, $this->collection->listIndexes());
        $this->assertTrue($cache->contains('expire'), 'Data should not be expired yet');
        sleep(2);
        $this->assertFalse($cache->contains('expire'), 'Data should be expired');
        $this->assertCount(2, $this->collection->listIndexes());
    }

    protected function _getCacheDriver(): CacheProvider
    {
        return new MongoDBCache($this->collection);
    }
}
