<?php
namespace Couchdb\Test;

use Couchdb\Client;
use Couchdb\Test\Traits\VisibilityTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    use VisibilityTrait;

    public function testConstructorWithCookieAuth()
    {
        $handler = MockHandler::createWithMiddleware([
            new Response(200, ['Set-Cookie' => 'auth cookie']),
        ]);

        $config = [
            'handler' => $handler,
            'headers' => [
                'User-Agent' => 'CouchDB PHP consumer'
            ],
        ];

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_COOKIE, $config);

        /** @var \GuzzleHttp\Client $http */
        $http = $this->getPrivateProperty($client, 'http');

        /** @var \GuzzleHttp\Psr7\Uri $uri */
        $uri = $http->getConfig('base_uri');

        /** @var array $headers */
        $headers = $http->getConfig('headers');

        $this->assertEquals('http://host:5984', (string) $uri);

        $this->assertEquals([
            'User-Agent'   => 'CouchDB PHP consumer',
            'Content-Type' => 'application/json',
            'Cookie'       => 'auth cookie',
        ], $headers);
    }

    public function testConstructorWithBasicAuth()
    {
        $config = [
            'headers' => [
                'User-Agent' => 'CouchDB PHP consumer'
            ],
        ];

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, $config);

        /** @var \GuzzleHttp\Client $http */
        $http = $this->getPrivateProperty($client, 'http');

        /** @var \GuzzleHttp\Psr7\Uri $uri */
        $uri = $http->getConfig('base_uri');

        /** @var array $headers */
        $headers = $http->getConfig('headers');

        $this->assertEquals('http://user:pass@host:5984', (string) $uri);

        $this->assertEquals([
            'User-Agent'   => 'CouchDB PHP consumer',
            'Content-Type' => 'application/json',
        ], $headers);
    }

    public function testGetAllDatabases()
    {
        $handler = MockHandler::createWithMiddleware([
            new Response(200, [], '["_global_changes", "_metadata", "_replicator", "_users"]'),
        ]);

        $client    = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $databases = $client->getAllDatabases();

        $this::assertEquals([
            '_global_changes',
            '_metadata',
            '_replicator',
            '_users',
        ], $databases);
    }

    /**
     * @expectedException \Couchdb\Exception\ConnectionException
     * @expectedExceptionMessage Failed to connect to host port 5984
     */
    public function testGetAllDatabasesCantConnect()
    {
        $message = 'Failed to connect to host port 5984';
        $request = new Request('GET', '/_all_dbs');

        $handler = MockHandler::createWithMiddleware([
            new ConnectException($message, $request),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $client->getAllDatabases();
    }

    /**
     * @expectedException \Couchdb\Exception\UnauthorizedException
     * @expectedExceptionMessage Client error: `GET http://user:***@host:5984/_all_dbs` resulted in a `401 Unauthorized`
     */
    public function testGetAllDatabasesUnauthorized()
    {
        $message  = 'Client error: `GET http://user:***@host:5984/_all_dbs` resulted in a `401 Unauthorized`';
        $request  = new Request('GET', '/_all_dbs');
        $response = new Response(401, [], '{"error":"unauthorized","reason":"Name or password is incorrect."}');

        $handler = MockHandler::createWithMiddleware([
            new ClientException($message, $request, $response),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $client->getAllDatabases();
    }

    public function testIsDatabaseExists()
    {
        $handler = MockHandler::createWithMiddleware([
            new Response(200),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $this::assertTrue($client->isDatabaseExists('database'));
    }

    public function testIsDatabaseNotExists()
    {
        $message  = 'Client error: `HEAD http://user:***@host:5984/database` resulted in a `404 Object Not Found`';
        $request  = new Request('HEAD', '/database');
        $response = new Response(404);

        $handler = MockHandler::createWithMiddleware([
            new ClientException($message, $request, $response),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $this::assertFalse($client->isDatabaseExists('database'));
    }

    /**
     * @expectedException \Couchdb\Exception\ConnectionException
     * @expectedExceptionMessage Failed to connect to host port 5984
     */
    public function testIsDatabaseExistsCantConnect()
    {
        $message = 'Failed to connect to host port 5984';
        $request = new Request('HEAD', '/database');

        $handler = MockHandler::createWithMiddleware([
            new ConnectException($message, $request),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $client->isDatabaseExists('database');
    }

    /**
     * @expectedException \Couchdb\Exception\UnauthorizedException
     * @expectedExceptionMessage Client error: `HEAD http://user:***@host:5984/database` resulted in a `401 Unauthorized`
     */
    public function testIsDatabaseExistsUnauthorized()
    {
        $message  = 'Client error: `HEAD http://user:***@host:5984/database` resulted in a `401 Unauthorized`';
        $request  = new Request('HEAD', '/database');
        $response = new Response(401, [], '{"error":"unauthorized","reason":"Name or password is incorrect."}');

        $handler = MockHandler::createWithMiddleware([
            new ClientException($message, $request, $response),
        ]);

        $client = new Client('host', 5984, 'user', 'pass', Client::AUTH_BASIC, ['handler' => $handler]);
        $client->isDatabaseExists('database');
    }
}
