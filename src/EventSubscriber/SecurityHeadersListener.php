<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * En-têtes de sécurité ajoutés à toutes les réponses.
 *
 * La CSP se limite pour l'instant aux directives sans risque pour Turbo, les composants Live et EasyAdmin :
 * interdiction d'afficher le site dans une iframe (clickjacking), de changer la base des URL,
 * de charger des plugins et d'envoyer un formulaire vers un autre site.
 * Restreindre aussi les scripts (script-src avec nonce) demandera de vérifier le site dans un navigateur.
 */
#[AsEventListener]
final class SecurityHeadersListener
{
    private const array HEADERS = [
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'",
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        // HSTS : uniquement en production et en HTTPS, pour ne pas bloquer les sites locaux en HTTP
        if ('prod' === $this->environment && $event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
