<?php

namespace App\Twig\Components;

use App\Achievement\AchievementChecker;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Formulaire d'inscription validé en direct (pseudo / email déjà pris, mots de passe différents…).
 */
#[AsLiveComponent]
class RegistrationForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** Refus global (limite d'inscriptions atteinte), affiché au-dessus du formulaire */
    public ?string $error = null;

    /**
     * @return FormInterface<User>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(RegistrationFormType::class, new User());
    }

    #[LiveAction]
    public function save(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        AchievementChecker $achievements,
        Request $request,
        #[Target('registration')]
        RateLimiterFactoryInterface $registrationLimiter,
    ): ?Response {
        $this->submitForm();

        // Champ piège rempli : très probablement un robot
        if ('' !== trim((string) $this->getForm()->get('website')->getData())) {
            $this->error = 'Inscription refusée. Si tu n\'es pas un robot, recharge la page et réessaie.';

            return null;
        }

        // Seules les inscriptions valides comptent : une faute de frappe ne bloque personne
        if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            $this->error = 'Trop de comptes créés depuis ta connexion. Réessaie dans une heure.';

            return null;
        }

        /** @var User $user */
        $user = $this->getForm()->getData();
        $user->setPassword($passwordHasher->hashPassword($user, $this->getForm()->get('plainPassword')->getData()));

        $entityManager->persist($user);
        $entityManager->flush();
        $achievements->check($user);

        $security->login($user, 'form_login', 'main');
        $this->addFlash('success', \sprintf('Bienvenue %s, ton compte est créé !', $user->getUsername()));

        return $this->redirectToRoute('app_profile');
    }
}
