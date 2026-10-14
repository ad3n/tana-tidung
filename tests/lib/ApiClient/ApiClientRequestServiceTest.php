<?php

declare(strict_types=1);

namespace KejawenLab\ApiSkeleton\Tests\ApiClient;

use KejawenLab\ApiSkeleton\ApiClient\ApiClientRequestService;
use KejawenLab\ApiSkeleton\ApiClient\Message\RequestLog;
use KejawenLab\ApiSkeleton\Entity\ApiClient;
use KejawenLab\ApiSkeleton\Entity\ApiClientRequest;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Repository\ApiClientRepository;
use KejawenLab\ApiSkeleton\Repository\ApiClientRequestRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
class ApiClientRequestServiceTest extends TestCase
{
    public function testCreateFromMessage(): void
    {
        $client = new ApiClient();
        $clientId = Uuid::uuid4();
        $property = new ReflectionProperty($client, 'id');
        $property->setValue($client, $clientId);
        $request = Request::create('/api/test', 'GET', ['q' => 'value']);
        $response = new Response('result', 201);
        $message = new RequestLog($client, $request, $response);

        $clients = $this->createMock(ApiClientRepository::class);
        $clients->expects($this->once())->method('find')->with((string) $clientId)->willReturn($client);
        $records = $this->createMock(ApiClientRequestRepository::class);
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
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(fn ($message) => new Envelope($message));
        $service = new ApiClientRequestService(
            $bus, $records, new AliasHelper(), $clients,
            ApiClientRequest::class
        );

        $service($message);
    }
}
