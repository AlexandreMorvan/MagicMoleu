<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Account\ChangePasswordType;
use App\Form\Account\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * « Mot de passe oublié » : lien de réinitialisation envoyé par email (symfonycasts/reset-password-bundle).
 */
#[Route('/mot-de-passe-oublie')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, MailerInterface $mailer, #[Autowire('%env(MAILER_FROM)%')] string $from): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $users->findOneBy(['email' => $form->get('email')->getData()]);
            // Même réponse que l'email existe ou non : on ne révèle pas quels comptes existent
            if ($user) {
                try {
                    $token = $this->resetPasswordHelper->generateResetToken($user);
                    $mailer->send((new TemplatedEmail())
                        ->from(Address::create($from))
                        ->to((string) $user->getEmail())
                        ->subject('Réinitialisation de ton mot de passe')
                        ->htmlTemplate('reset_password/email.html.twig')
                        ->context(['resetToken' => $token, 'username' => $user->getUsername()]));
                    $this->setTokenObjectInSession($token);
                } catch (ResetPasswordExceptionInterface) {
                    // Demande trop rapprochée (throttle_limit) : le lien précédent reste valable
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/email-envoye', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig', [
            // Durée de validité affichée même si aucun email n'a été envoyé (compte inconnu)
            'lifetime_minutes' => (int) ($this->resetPasswordHelper->getTokenLifetime() / 60),
        ]);
    }

    /**
     * Le jeton du lien est mis en session puis retiré de l'URL, pour qu'il ne fuite pas (historique, en-tête Referer).
     */
    #[Route('/reinitialiser/{token}', name: 'app_reset_password', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $hasher, ?string $token = null): Response
    {
        if ($token) {
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Lien de réinitialisation introuvable.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'Ce lien de réinitialisation n\'est plus valable (expiré ou déjà utilisé). Fais une nouvelle demande.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if (!$user instanceof User) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ChangePasswordType::class, null, ['require_current_password' => false])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Un lien ne sert qu'une fois
            $this->resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'Mot de passe modifié : tu peux te connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }
}
