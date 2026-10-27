<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Ajoute `app.build_version`, différent à chaque compilation du conteneur (donc à chaque déploiement,
     * et en dev à chaque modification du code) : il sert à invalider les caches navigateur longs.
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        return parent::getKernelParameters() + ['app.build_version' => base_convert((string) hrtime(true), 10, 36)];
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
