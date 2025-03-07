<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Plugins\Auth;

use PhpMyAdmin\Config;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Exceptions\ExitException;
use PhpMyAdmin\Plugins\Auth\AuthenticationHttp;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer as ResponseRendererStub;
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionProperty;
use Throwable;

use function base64_encode;
use function ob_get_clean;
use function ob_start;

#[CoversClass(AuthenticationHttp::class)]
#[Medium]
class AuthenticationHttpTest extends AbstractTestCase
{
    protected AuthenticationHttp $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        parent::setGlobalConfig();

        parent::setTheme();

        DatabaseInterface::$instance = $this->createDatabaseInterface();
        Config::getInstance()->settings['Servers'] = [];
        $GLOBALS['server'] = 0;
        $GLOBALS['db'] = 'db';
        $GLOBALS['table'] = 'table';
        $GLOBALS['lang'] = 'en';
        $GLOBALS['text_dir'] = 'ltr';
        $GLOBALS['token_provided'] = true;
        $GLOBALS['token_mismatch'] = false;
        $this->object = new AuthenticationHttp();
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    #[BackupStaticProperties(true)]
    public function testAuthLogoutUrl(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['auth_type'] = 'http';
        $config->selectedServer['LogoutURL'] = 'https://example.com/logout';

        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        $this->object->logOut();

        $response = $responseStub->getResponse();
        $this->assertSame(['https://example.com/logout'], $response->getHeader('Location'));
        $this->assertSame(302, $response->getStatusCode());
    }

    #[BackupStaticProperties(true)]
    public function testAuthVerbose(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['auth_type'] = 'http';
        $config->selectedServer['verbose'] = 'verboseMessagê';

        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        try {
            $this->object->showLoginForm();
        } catch (Throwable $throwable) {
        }

        $this->assertInstanceOf(ExitException::class, $throwable);
        $response = $responseStub->getResponse();
        $this->assertSame(['Basic realm="phpMyAdmin verboseMessag"'], $response->getHeader('WWW-Authenticate'));
        $this->assertSame(401, $response->getStatusCode());
    }

    #[BackupStaticProperties(true)]
    public function testAuthHost(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['auth_type'] = 'http';
        $config->selectedServer['verbose'] = '';
        $config->selectedServer['host'] = 'hòst';

        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        try {
            $this->object->showLoginForm();
        } catch (Throwable $throwable) {
        }

        $this->assertInstanceOf(ExitException::class, $throwable);
        $response = $responseStub->getResponse();
        $this->assertSame(['Basic realm="phpMyAdmin hst"'], $response->getHeader('WWW-Authenticate'));
        $this->assertSame(401, $response->getStatusCode());
    }

    #[BackupStaticProperties(true)]
    public function testAuthRealm(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['auth_type'] = 'http';
        $config->selectedServer['host'] = '';
        $config->selectedServer['auth_http_realm'] = 'rêäealmmessage';

        $responseStub = new ResponseRendererStub();
        (new ReflectionProperty(ResponseRenderer::class, 'instance'))->setValue(null, $responseStub);

        try {
            $this->object->showLoginForm();
        } catch (Throwable $throwable) {
        }

        $this->assertInstanceOf(ExitException::class, $throwable);
        $response = $responseStub->getResponse();
        $this->assertSame(['Basic realm="realmmessage"'], $response->getHeader('WWW-Authenticate'));
        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * @param string      $user           test username
     * @param string      $pass           test password
     * @param string      $userIndex      index to test username against
     * @param string      $passIndex      index to test username against
     * @param string|bool $expectedReturn expected return value from test
     * @param string      $expectedUser   expected username to be set
     * @param string|bool $expectedPass   expected password to be set
     * @param string|bool $oldUsr         value for $_REQUEST['old_usr']
     */
    #[DataProvider('readCredentialsProvider')]
    public function testAuthCheck(
        string $user,
        string $pass,
        string $userIndex,
        string $passIndex,
        string|bool $expectedReturn,
        string $expectedUser,
        string|bool $expectedPass,
        string|bool $oldUsr = '',
    ): void {
        $_SERVER[$userIndex] = $user;
        $_SERVER[$passIndex] = $pass;

        $_REQUEST['old_usr'] = $oldUsr;

        $this->assertEquals(
            $expectedReturn,
            $this->object->readCredentials(),
        );

        $this->assertEquals($expectedUser, $this->object->user);

        $this->assertEquals($expectedPass, $this->object->password);

        unset($_SERVER[$userIndex]);
        unset($_SERVER[$passIndex]);
    }

    /**
     * @return array<array{
     *     0: string, 1: string, 2: string, 3: string, 4: string|bool, 5: string, 6: string|bool, 7?: string|bool
     * }>
     */
    public static function readCredentialsProvider(): array
    {
        return [
            ['Basic ' . base64_encode('foo:bar'), 'pswd', 'PHP_AUTH_USER', 'PHP_AUTH_PW', false, '', 'bar', 'foo'],
            [
                'Basic ' . base64_encode('foobar'),
                'pswd',
                'REMOTE_USER',
                'REMOTE_PASSWORD',
                true,
                'Basic Zm9vYmFy',
                'pswd',
            ],
            ['Basic ' . base64_encode('foobar:'), 'pswd', 'AUTH_USER', 'AUTH_PASSWORD', true, 'foobar', false],
            [
                'Basic ' . base64_encode(':foobar'),
                'pswd',
                'HTTP_AUTHORIZATION',
                'AUTH_PASSWORD',
                true,
                'Basic OmZvb2Jhcg==',
                'pswd',
            ],
            ['BasicTest', 'pswd', 'Authorization', 'AUTH_PASSWORD', true, 'BasicTest', 'pswd'],
        ];
    }

    public function testAuthSetUser(): void
    {
        // case 1

        $this->object->user = 'testUser';
        $this->object->password = 'testPass';
        $GLOBALS['server'] = 2;
        $config = Config::getInstance();
        $config->selectedServer['user'] = 'testUser';

        $this->assertTrue(
            $this->object->storeCredentials(),
        );

        $this->assertEquals('testUser', $config->selectedServer['user']);

        $this->assertEquals('testPass', $config->selectedServer['password']);

        $this->assertArrayNotHasKey('PHP_AUTH_PW', $_SERVER);

        $this->assertEquals(2, $GLOBALS['server']);

        // case 2
        $this->object->user = 'testUser';
        $this->object->password = 'testPass';
        $config->settings['Servers'][1] = ['host' => 'a', 'user' => 'testUser', 'foo' => 'bar'];

        $config->selectedServer = ['host' => 'a', 'user' => 'user2'];

        $this->assertTrue(
            $this->object->storeCredentials(),
        );

        $this->assertEquals(
            ['user' => 'testUser', 'password' => 'testPass', 'host' => 'a'],
            $config->selectedServer,
        );

        $this->assertEquals(2, $GLOBALS['server']);

        // case 3
        $GLOBALS['server'] = 3;
        $this->object->user = 'testUser';
        $this->object->password = 'testPass';
        $config->settings['Servers'][1] = ['host' => 'a', 'user' => 'testUsers', 'foo' => 'bar'];

        $config->selectedServer = ['host' => 'a', 'user' => 'user2'];

        $this->assertTrue(
            $this->object->storeCredentials(),
        );

        $this->assertEquals(
            ['user' => 'testUser', 'password' => 'testPass', 'host' => 'a'],
            $config->selectedServer,
        );

        $this->assertEquals(3, $GLOBALS['server']);
    }

    #[Group('medium')]
    #[RunInSeparateProcess]
    public function testAuthFails(): void
    {
        $config = Config::getInstance();
        $config->selectedServer['host'] = '';
        $_REQUEST = [];
        ResponseRenderer::getInstance()->setAjax(false);

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dbi->expects($this->exactly(3))
            ->method('getError')
            ->willReturn('error 123', 'error 321', '');

        DatabaseInterface::$instance = $dbi;
        $GLOBALS['errno'] = 31;

        ob_start();
        try {
            $this->object->showFailure('');
        } catch (Throwable $throwable) {
        }

        $result = ob_get_clean();

        $this->assertInstanceOf(ExitException::class, $throwable);

        $this->assertIsString($result);

        $this->assertStringContainsString('<p>error 123</p>', $result);

        $this->object = $this->getMockBuilder(AuthenticationHttp::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['authForm'])
            ->getMock();

        $this->object->expects($this->exactly(2))
            ->method('authForm')
            ->willThrowException(new ExitException());
        // case 2
        $config->selectedServer['host'] = 'host';
        $GLOBALS['errno'] = 1045;

        try {
            $this->object->showFailure('');
        } catch (ExitException) {
        }

        // case 3
        $GLOBALS['errno'] = 1043;
        $this->expectException(ExitException::class);
        $this->object->showFailure('');
    }
}
