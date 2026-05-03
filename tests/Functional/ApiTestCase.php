<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    private static bool $databaseInitialized = false;
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $this->initializeDatabase($entityManager);
        $this->loadFixtures($entityManager, $container->get(AppFixtures::class));

        $this->client->disableReboot();
    }

    protected function postJson(string $uri, array $payload, array $server = []): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', ...$server],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    protected function authenticate(string $username = 'user@example.com', string $password = 'user123'): string
    {
        $this->postJson('/api/v1/auth', [
            'username' => $username,
            'password' => $password,
        ]);

        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['token'];
    }

    private function initializeDatabase(EntityManagerInterface $entityManager): void
    {
        $this->ensureDatabaseExists($entityManager);

        if (self::$databaseInitialized) {
            return;
        }

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        self::$databaseInitialized = true;
    }

    private function ensureDatabaseExists(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        try {
            $connection->connect();

            return;
        } catch (\Throwable) {
        }

        $params = $connection->getParams();
        $dbname = $params['dbname'] ?? null;
        $host = $params['host'] ?? null;
        $user = $params['user'] ?? null;

        if (!is_string($dbname) || !is_string($host) || !is_string($user)) {
            return;
        }

        $dsn = sprintf(
            'pgsql:host=%s;%sdbname=postgres',
            $host,
            isset($params['port']) ? sprintf('port=%s;', $params['port']) : ''
        );

        $adminConnection = new \PDO($dsn, $user, $params['password'] ?? null);
        $adminConnection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $databaseExists = (bool) $adminConnection
            ->query(sprintf("SELECT 1 FROM pg_database WHERE datname = '%s'", str_replace("'", "''", $dbname)))
            ->fetchColumn();

        if (!$databaseExists) {
            $adminConnection->exec(sprintf('CREATE DATABASE "%s"', str_replace('"', '""', $dbname)));
        }
    }

    private function loadFixtures(EntityManagerInterface $entityManager, AppFixtures $fixtures): void
    {
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $connection->executeStatement('DELETE FROM '.$platform->quoteIdentifier('billing_transaction'));
        $connection->executeStatement('DELETE FROM '.$platform->quoteIdentifier('billing_course'));
        $connection->executeStatement('DELETE FROM '.$platform->quoteIdentifier('refresh_tokens'));
        $connection->executeStatement('DELETE FROM '.$platform->quoteIdentifier('billing_user'));
        $fixtures->load($entityManager);
        $entityManager->clear();
    }
}
