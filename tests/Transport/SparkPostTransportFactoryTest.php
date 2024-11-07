<?php declare(strict_types=1);

namespace Gam6itko\Symfony\Mailer\SparkPost\Test\Transport;

use Gam6itko\Symfony\Mailer\SparkPost\Transport\SparkPostApiTransport;
use Gam6itko\Symfony\Mailer\SparkPost\Transport\SparkPostSmtpTransport;
use Gam6itko\Symfony\Mailer\SparkPost\Transport\SparkPostTransportFactory;
use PHPUnit\Framework\MockObject\Generator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @coversDefaultClass \Gam6itko\Symfony\Mailer\SparkPost\Transport\SparkPostTransportFactory
 */
class SparkPostTransportFactoryTest extends TransportFactoryTestCase
{
    private static $mocks = [];

    public function getFactory(): TransportFactoryInterface
    {
        $client = self::staticMock(HttpClientInterface::class);
        $dispatcher = self::staticMock(EventDispatcherInterface::class);
        $logger = self::staticMock(LoggerInterface::class);
        return new SparkPostTransportFactory($dispatcher, $client, $logger);
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sparkpost', 'default'),
            true,
        ];

        yield [
            new Dsn('sparkpost', 'example.com'),
            true,
        ];

        yield [
            new Dsn('sparkpost+api', 'default'),
            true,
        ];

        yield [
            new Dsn('sparkpost+api', 'example.com'),
            true,
        ];

        yield [
            new Dsn('sparkpost+https', 'default'),
            false,
        ];

        yield [
            new Dsn('sparkpost+https', 'example.com'),
            false,
        ];

        yield [
            new Dsn('sparkpost+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sparkpost+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('sparkpost+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $client = self::staticMock(HttpClientInterface::class);
        $dispatcher = self::staticMock(EventDispatcherInterface::class);
        $logger = self::staticMock(LoggerInterface::class);

        yield [
            new Dsn('sparkpost+api', 'default', self::USER),
            new SparkPostApiTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('sparkpost+api', 'default', self::USER, null, null, ['region' => 'eu']),
            new SparkPostApiTransport(self::USER, $client, $dispatcher, $logger, 'eu'),
        ];

        yield [
            new Dsn('sparkpost+api', 'example.com', self::USER),
            new SparkPostApiTransport(self::USER, $client, $dispatcher, $logger, null, 'example.com'),
        ];

        yield [
            new Dsn('sparkpost+api', 'example.com', self::USER, null, null, ['region' => 'eu']),
            new SparkPostApiTransport(self::USER, $client, $dispatcher, $logger, 'eu', 'example.com'),
        ];

        yield [
            new Dsn('sparkpost', 'default', self::USER, self::PASSWORD),
            new SparkPostSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger),
        ];

        foreach (['sparkpost', 'sparkpost+smtp', 'sparkpost+smtps'] as $scheme) {
            yield [
                new Dsn($scheme, 'default', self::USER, self::PASSWORD, null, ['region' => 'eu']),
                new SparkPostSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger, 'eu'),
            ];

            yield [
                new Dsn($scheme, 'example.com', self::USER, self::PASSWORD),
                new SparkPostSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger, null, 'example.com'),
            ];

            yield [
                new Dsn($scheme, 'example.com', self::USER, self::PASSWORD, null, ['region' => 'eu']),
                new SparkPostSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger, 'eu', 'example.com'),
            ];

            yield [
                new Dsn($scheme, 'example.com', self::USER, self::PASSWORD, 1111, ['region' => 'eu']),
                new SparkPostSmtpTransport(self::USER, self::PASSWORD, 1111, $dispatcher, $logger, 'eu', 'example.com'),
            ];
        }
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sparkpost+http', 'default', self::USER),
            'The "sparkpost+http" scheme is not supported; supported schemes for mailer "sparkpost" are: "sparkpost", "sparkpost+api", "sparkpost+smtp", "sparkpost+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sparkpost+api', 'default')];

        yield [new Dsn('sparkpost+smtp', 'default')];

        yield [new Dsn('sparkpost+smtp', 'default', self::USER)];
    }

    private static function staticMock(string $className): MockObject
    {
        if (isset(self::$mocks[$className])) {
            return self::$mocks[$className];
        }
        $generator = new Generator();
        return $generator->getMock($className, [], [], '', false);
    }
}
