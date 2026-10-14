<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\ApiClient;

use PHPUnit\Framework\TestCase;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class ApiClientRequestServiceTest extends TestCase
{
    public function testCreateFromMessage(): void
    {
        $client = new \KejawenLab\ApiSkeleton\Entity\ApiClient();
        $clientId = \Ramsey\Uuid\Uuid::uuid4();
        $property = new \ReflectionProperty($client, 'id');
        $property->setValue($client, $clientId);
        $request = \Symfony\Component\HttpFoundation\Request::create('/api/test', 'GET', ['q' => 'value']);
        $response = new \Symfony\Component\HttpFoundation\Response('result', 201);
        $message = new \KejawenLab\ApiSkeleton\ApiClient\Message\RequestLog($client, $request, $response);

        $clients = $this->createMock(\KejawenLab\ApiSkeleton\Repository\ApiClientRepository::class);
        $clients->expects($this->once())->method('find')->with((string) $clientId)->willReturn($client);
        $records = $this->createMock(\KejawenLab\ApiSkeleton\Repository\ApiClientRequestRepository::class);
        $records->expects($this->once())->method('persist')->with($this->callback(function ($record) use ($client): bool {
            $this->assertSame($client, $record->getApiClient());
            $this->assertSame('/api/test', $record->getPath());
            $this->assertSame('GET', $record->getMethod());
            $this->assertSame(['q' => 'value'], $record->getQueries());
            $this->assertSame('result', $record->getContent());
            $this->assertSame(201, $record->getStatusCode());

            return true;
        }));
        $records->expects($this->once())->method('commit');
        $bus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(fn ($message) => new \Symfony\Component\Messenger\Envelope($message));
        $service = new \KejawenLab\ApiSkeleton\ApiClient\ApiClientRequestService(
            $bus, $records, new \KejawenLab\ApiSkeleton\Pagination\AliasHelper(), $clients,
            \KejawenLab\ApiSkeleton\Entity\ApiClientRequest::class
        );

        $service($message);
    }
}
