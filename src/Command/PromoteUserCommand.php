<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote', description: 'Donne (ou retire avec --revoke) le rôle administrateur à un utilisateur')]
class PromoteUserCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email ou pseudo de l\'utilisateur')]
        string $user,
        #[Option(description: 'Retirer le rôle administrateur')]
        bool $revoke = false,
    ): int {
        $account = $this->users->findOneBy(['email' => $user]) ?? $this->users->findOneBy(['username' => $user]);
        if (null === $account) {
            $io->error(\sprintf('Aucun utilisateur « %s ».', $user));

            return Command::FAILURE;
        }

        $roles = array_values(array_diff($account->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']));
        $account->setRoles($revoke ? $roles : [...$roles, 'ROLE_ADMIN']);
        $this->em->flush();

        $io->success(\sprintf('%s %s administrateur.', $account->getUsername(), $revoke ? 'n\'est plus' : 'est maintenant'));

        return Command::SUCCESS;
    }
}
