<?php

declare(strict_types=1);

function prepareAdminCsrf(string $path, string $method, string $payload, array $headers): array
{
    if ('POST' !== $method || !str_starts_with($path, '/admin')) {
        return [$payload, $headers];
    }

    [$page, $pageHeaders] = request('/admin/' === $path ? '/admin/login' : '/admin/me', 200, headers: $headers);
    if (!preg_match('/name="_csrf_token" value="([^"]+)"/', $page, $match)) {
        throw new RuntimeException('Missing CSRF token');
    }

    parse_str($payload, $fields);
    $fields['_csrf_token'] = html_entity_decode($match[1]);
    $cookies = [];
    $otherHeaders = [];
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), 'cookie:')) {
            foreach (explode(';', substr($header, 7)) as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (2 === count($parts)) {
                    $cookies[$parts[0]] = $parts[1];
                }
            }
        } else {
            $otherHeaders[] = $header;
        }
    }

    foreach ($pageHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookie)) {
            $cookies[$cookie[1]] = $cookie[2];
        }
    }

    $otherHeaders[] = 'Cookie: '.implode('; ', array_map(static fn ($name, $value): string => $name.'='.$value, array_keys($cookies), $cookies));

    return [http_build_query($fields), $otherHeaders];
}
