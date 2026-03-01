<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Główna klasa Kernel aplikacji Symfony.
 *
 * Odpowiada za inicjalizację frameworka, ładowanie konfiguracji
 * oraz rejestrację bundli i serwisów.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
