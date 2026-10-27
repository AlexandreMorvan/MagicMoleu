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

    /**
     * @return FormInterface<User>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(RegistrationFormType::class, new User());
    }

    #[LiveAction]
    public function save(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, Security $security, AchievementChecker $achievements): Response
    {
        $this->submitForm();

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
