<?php

namespace Tests\Afeefa\Component\Settings;

use Afeefa\Component\Settings\ConfigCache;
use Afeefa\Component\Settings\Environment;
use Afeefa\Component\Settings\Test\ConfigTestTrait;
use Afeefa\Component\TestingUtils\FileSystem;
use Kollektiv\Utils\BenchmarkUtils;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class ConfigCacheTest extends TestCase
{
    use ConfigTestTrait;

    protected function setUp(): void
    {
        FileSystem::emptyDirectory(Path::join(__DIR__, 'fixtures', 'cache'));
    }

    public function test_load()
    {
        $pathCache = Path::join(__DIR__, 'fixtures', 'cache', 'config.php');

        $this->assertFileDoesNotExist($pathCache);

        $cache = new ConfigCache($pathCache);
        $this->addPaths($cache);

        $cache->purge();
        $config = $cache->load(Environment::PRODUCTION);

        $this->assertConfig($config, Environment::PRODUCTION, false);

        $cache->purge();
        $config = $cache->load(Environment::DEVELOPMENT);

        $this->assertConfig($config, Environment::DEVELOPMENT, false);

        $cache->purge();
        $config = $cache->load(Environment::TEST);

        $this->assertConfig($config, Environment::TEST, false);
    }

    public function test_load_from_cache()
    {
        $pathCache = Path::join(__DIR__, 'fixtures', 'cache', 'config.php');

        $this->assertFileDoesNotExist($pathCache);

        $cache = new ConfigCache($pathCache);
        $cache->purge();
        $this->assertFileDoesNotExist($pathCache);

        $cache = $this->createCacheMock($pathCache);
        $this->addPaths($cache);
        $config = $cache->load(Environment::PRODUCTION);
        $cache->shouldHaveReceived('loadFromFilesystem');
        $this->assertConfig($config, Environment::PRODUCTION, false);

        $this->assertFileExists($pathCache);

        $cache = $this->createCacheMock($pathCache);
        $this->addPaths($cache);
        $config = $cache->load(Environment::PRODUCTION);
        $cache->shouldNotHaveReceived('loadFromFilesystem');
        $this->assertConfig($config, Environment::PRODUCTION, false);

        $cache->purge();
        $this->assertFileDoesNotExist($pathCache);
    }

    public function test_write_cache_only_if_not_exists()
    {
        $pathCache = Path::join(__DIR__, 'fixtures', 'cache', 'config.php');

        $this->assertFileDoesNotExist($pathCache);

        $cache = new ConfigCache($pathCache);
        $cache->purge();
        $this->assertFileDoesNotExist($pathCache);

        $cache = $this->createCacheMock($pathCache);
        $this->addPaths($cache);
        $config = $cache->load(Environment::PRODUCTION);
        $cache->shouldHaveReceived('loadFromFilesystem');
        $cache->shouldHaveReceived('writeToFilesystem');
        $this->assertConfig($config, Environment::PRODUCTION, false);

        $this->assertFileExists($pathCache);

        $cache = $this->createCacheMock($pathCache);
        $this->addPaths($cache);
        $config = $cache->load(Environment::PRODUCTION);
        $cache->shouldNotHaveReceived('loadFromFilesystem');
        $cache->shouldNotHaveReceived('writeToFilesystem');
        $this->assertConfig($config, Environment::PRODUCTION, false);
    }

    public function _test_performance()
    {
        $pathCache = Path::join(__DIR__, 'fixtures', 'cache', 'config.php');

        $this->assertFileDoesNotExist($pathCache);

        $cache = new ConfigCache($pathCache);
        $cache->addPaths(
            $cache->path(Path::join(__DIR__, 'fixtures', 'performancetest'))
                ->files('config1', 'config2', 'config3')
        );

        $cache->purge();

        $benchmark = BenchmarkUtils::startBenchmark();
        debug_dump('START', $benchmark->getDiff());

        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERLOAD', $benchmark->getDiff());

        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERCACHE', $benchmark->getDiff());

        $cache->purge();
        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERLOAD', $benchmark->getDiff());

        $cache->purge();
        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERLOAD', $benchmark->getDiff());

        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERCACHE', $benchmark->getDiff());

        $config = $cache->load(Environment::PRODUCTION);

        debug_dump('AFTERCACHE', $benchmark->getDiff());
    }

    protected function getTestAppPlugins(): array
    {
        $pathTestApp = $this->getPathTestApp();

        return [
            Path::join($pathTestApp, 'vendor', 'author', 'plugin'),
            Path::join($pathTestApp, 'vendor', 'author2', 'multiplugin', 'plugins', '*'),
            Path::join($pathTestApp, 'localplugin')
        ];
    }

    protected function getPathTestApp()
    {
        return Path::join(__DIR__, '..', 'fixtures', 'testapp');
    }

    /**
     * @return ConfigCache|MockInterface
     */
    private function createCacheMock($pathCache)
    {
        /** @var MockInterface */
        $mock = \Mockery::mock(ConfigCache::class, [$pathCache]);
        return $mock->makePartial();
    }
}
