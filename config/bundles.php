<?php

use DH\AuditorBundle\DHAuditorBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use FOS\RestBundle\FOSRestBundle;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Nelmio\CorsBundle\NelmioCorsBundle;
use Snc\RedisBundle\SncRedisBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use Vich\UploaderBundle\VichUploaderBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    WebProfilerBundle::class => ['dev' => true, 'test' => true],
    LexikJWTAuthenticationBundle::class => ['all' => true],
    StofDoctrineExtensionsBundle::class => ['all' => true],
    SncRedisBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    MakerBundle::class => ['dev' => true],
    NelmioApiDocBundle::class => ['all' => true],
    FOSRestBundle::class => ['all' => true],
    TwigExtraBundle::class => ['all' => true],
    DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    VichUploaderBundle::class => ['all' => true],
    DHAuditorBundle::class => ['all' => true],
    DebugBundle::class => ['dev' => true],
    NelmioCorsBundle::class => ['all' => true],
];
