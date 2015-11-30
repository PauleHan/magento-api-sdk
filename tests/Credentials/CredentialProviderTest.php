<?php
namespace Triggmine\Test\Credentials;

use Triggmine\Credentials\CredentialProvider;
use Triggmine\Credentials\Credentials;
use Triggmine\LruArrayCache;
use GuzzleHttp\Promise;

/**
 * @covers \Triggmine\Credentials\CredentialProvider
 */

class CredentialProviderTest extends \PHPUnit_Framework_TestCase
{
    private $home, $homedrive, $homepath, $key, $secret, $profile;

    private function clearEnv()
    {
        putenv(CredentialProvider::ENV_KEY . '=');
        putenv(CredentialProvider::ENV_SECRET . '=');
        putenv(CredentialProvider::ENV_PROFILE . '=');

        $dir = sys_get_temp_dir() . '/.triggmine';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }


    public function setUp()
    {
        $this->home = getenv('HOME');
        $this->homedrive = getenv('HOMEDRIVE');
        $this->homepath = getenv('HOMEPATH');
        $this->key = getenv(CredentialProvider::ENV_KEY);
        $this->secret = getenv(CredentialProvider::ENV_SECRET);
        $this->profile = getenv(CredentialProvider::ENV_PROFILE);
    }

    public function tearDown()
    {
        putenv('HOME=' . $this->home);
        putenv('HOMEDRIVE=' . $this->homedrive);
        putenv('HOMEPATH=' . $this->homepath);
        putenv(CredentialProvider::ENV_KEY . '=' . $this->key);
        putenv(CredentialProvider::ENV_SECRET . '=' . $this->secret);
        putenv(CredentialProvider::ENV_PROFILE . '=' . $this->profile);
    }

    public function testCreatesFromCache()
    {
        $cache = new LruArrayCache;
        $key = __CLASS__ . 'credentialsCache';
        $saved = new Credentials('foo', 'bar', 'baz', PHP_INT_MAX);
        $cache->set($key, $saved, $saved->getExpiration() - time());
        $explodingProvider = function () {
            throw new \BadFunctionCallException('This should never be called');
        };
        $found = call_user_func(
            CredentialProvider::cache($explodingProvider, $cache, $key)
        )
            ->wait();
        $this->assertEquals($saved->getAccessKeyId(), $found->getAccessKeyId());
        $this->assertEquals($saved->getSecretKey(), $found->getSecretKey());
        $this->assertEquals($saved->getSecurityToken(), $found->getSecurityToken());
        $this->assertEquals($saved->getExpiration(), $found->getExpiration());
    }

    public function testRefreshesCacheWhenCredsExpired()
    {
        $cache = new LruArrayCache;
        $key = __CLASS__ . 'credentialsCache';
        $saved = new Credentials('foo', 'bar', 'baz', time() - 1);
        $cache->set($key, $saved);
        $timesCalled = 0;
        $recordKeepingProvider = function () use (&$timesCalled) {
            ++$timesCalled;
            return Promise\promise_for(new Credentials('foo', 'bar', 'baz', PHP_INT_MAX));
        };
        call_user_func(
            CredentialProvider::cache($recordKeepingProvider, $cache, $key)
        )
            ->wait();
        $this->assertEquals(1, $timesCalled);
    }

    public function testPersistsToCache()
    {
        $cache = new LruArrayCache;
        $key = __CLASS__ . 'credentialsCache';
        $creds = new Credentials('foo', 'bar', 'baz', PHP_INT_MAX);
        $timesCalled = 0;
        $volatileProvider = function () use ($creds, &$timesCalled) {
            if (0 === $timesCalled) {
                ++$timesCalled;
                return Promise\promise_for($creds);
            }
            throw new \BadFunctionCallException('I was called too many times!');
        };
        for ($i = 0; $i < 10; $i++) {
            $found = call_user_func(
                CredentialProvider::cache($volatileProvider, $cache, $key)
            )
                ->wait();
        }
        $this->assertEquals(1, $timesCalled);
        $this->assertEquals(1, count($cache));
        $this->assertEquals($creds->getAccessKeyId(), $found->getAccessKeyId());
        $this->assertEquals($creds->getSecretKey(), $found->getSecretKey());
        $this->assertEquals($creds->getSecurityToken(), $found->getSecurityToken());
        $this->assertEquals($creds->getExpiration(), $found->getExpiration());
    }


    public function testCreatesFromEnvironmentVariables()
    {
        $this->clearEnv();
        putenv(CredentialProvider::ENV_KEY . '=abc');
        putenv(CredentialProvider::ENV_SECRET . '=123');
        $creds = call_user_func(CredentialProvider::env())->wait();
        $this->assertEquals('abc', $creds->getAccessKeyId());
        $this->assertEquals('abc', $creds->getAccessKeyId());
    }

    public function iniFileProvider()
    {
        $credentials = new Credentials('foo', 'bar', 'baz');
        $standardIni = <<<EOT
            [default]
            triggmine_access_key_id = foo
            triggmine_secret_access_key = bar
            triggmine_session_token = baz
EOT;
                    $oldIni = <<<EOT
            [default]
            triggmine_access_key_id = foo
            triggmine_secret_access_key = bar
            triggmine_security_token = baz
EOT;
                    $mixedIni = <<<EOT
            [default]
            triggmine_access_key_id = foo
            triggmine_secret_access_key = bar
            triggmine_session_token = baz
            triggmine_security_token = fizz
EOT;
        return [
            [$standardIni, $credentials],
            [$oldIni, $credentials],
            [$mixedIni, $credentials],
        ];
    }

    /**
     * @expectedException \Triggmine\Exception\CredentialsException
     * @expectedExceptionMessage Invalid credentials file:
     */
    public function testEnsuresIniFileIsValid()
    {
        $dir = $this->clearEnv();
        file_put_contents($dir . '/credentials', "wef \n=\nwef");
        putenv('HOME=' . dirname($dir));
        try {
            @call_user_func(CredentialProvider::ini())->wait();
        } catch (\Exception $e) {
            unlink($dir . '/credentials');
            throw $e;
        }
    }

    /**
     * @expectedException \Triggmine\Exception\CredentialsException
     */
    public function testEnsuresIniFileExists()
    {
        $this->clearEnv();
        putenv('HOME=/does/not/exist');
        call_user_func(CredentialProvider::ini())->wait();
    }

    /**
     * @expectedException \Triggmine\Exception\CredentialsException
     */
    public function testEnsuresProfileIsNotEmpty()
    {
        $dir = $this->clearEnv();
        $ini = "[default]\ntriggmine_access_key_id = foo\n"
            . "triggmine_secret_access_key = baz\n[foo]";
        file_put_contents($dir . '/credentials', $ini);
        putenv('HOME=' . dirname($dir));
        try {
            call_user_func(CredentialProvider::ini('foo'))->wait();
        } catch (\Exception $e) {
            unlink($dir . '/credentials');
            throw $e;
        }
    }

    /**
     * @expectedException \Triggmine\Exception\CredentialsException
     * @expectedExceptionMessage 'foo' not found in credentials file
     */
    public function testEnsuresFileIsNotEmpty()
    {
        $dir = $this->clearEnv();
        file_put_contents($dir . '/credentials', '');
        putenv('HOME=' . dirname($dir));
        try {
            call_user_func(CredentialProvider::ini('foo'))->wait();
        } catch (\Exception $e) {
            unlink($dir . '/credentials');
            throw $e;
        }
    }

    public function testCreatesFromInstanceProfileProvider()
    {
        $p = CredentialProvider::instanceProfile();
        $this->assertInstanceOf('Triggmine\Credentials\InstanceProfileProvider', $p);
    }

    public function testGetsHomeDirectoryForWindowsUsers()
    {
        putenv('HOME=');
        putenv('HOMEDRIVE=C:');
        putenv('HOMEPATH=\\Michael\\Home');
        $ref = new \ReflectionClass('Triggmine\Credentials\CredentialProvider');
        $meth = $ref->getMethod('getHomeDir');
        $meth->setAccessible(true);
        $this->assertEquals('C:\\Michael\\Home', $meth->invoke(null));
    }

    public function testMemoizes()
    {
        $called = 0;
        $creds = new Credentials('foo', 'bar');
        $f = function () use (&$called, $creds) {
            $called++;
            return \GuzzleHttp\Promise\promise_for($creds);
        };
        $p = CredentialProvider::memoize($f);
        $this->assertSame($creds, $p()->wait());
        $this->assertEquals(1, $called);
        $this->assertSame($creds, $p()->wait());
        $this->assertEquals(1, $called);
    }

    public function testCallsDefaultsCreds()
    {
        $k = getenv(CredentialProvider::ENV_KEY);
        $s = getenv(CredentialProvider::ENV_SECRET);
        putenv(CredentialProvider::ENV_KEY . '=abc');
        putenv(CredentialProvider::ENV_SECRET . '=123');
        $provider = CredentialProvider::defaultProvider();
        $creds = $provider()->wait();
        putenv(CredentialProvider::ENV_KEY . "={$k}");
        putenv(CredentialProvider::ENV_SECRET . "={$s}");
        $this->assertEquals('abc', $creds->getAccessKeyId());
        $this->assertEquals('123', $creds->getSecretKey());
    }
}