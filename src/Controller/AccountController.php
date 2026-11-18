<?php

namespace App\Controller;

use App\Account\AccountDataExporter;
use App\Entity\User;
use App\Form\Account\AccountIdentityType;
use App\Form\Account\AccountProfileType;
use App\Form\Account\ChangePasswordType;
use App\Form\Account\DeleteAccountType;
use App\Form\Account\PreferencesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * « Mon compte » : identité, profil public, avatar, mot de passe, préférences, export et suppression du compte.
 */
#[Route('/profil')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * Chaque section a son propre formulaire (nommé d'après son type) : seul celui qui est envoyé est traité.
     */
    #[Route('/parametres', name: 'app_account', methods: ['GET', 'POST'])]
    public function settings(Request $request, #[CurrentUser] User $user, UserPasswordHasherInterface $hasher): Response
    {
        $identity = $this->createForm(AccountIdentityType::class, $user)->handleRequest($request);
        if ($identity->isSubmitted() && $identity->isValid()) {
            return $this->saveAndReauthenticate($user, 'Email mis à jour.', 'identite');
        }

        $profile = $this->createForm(AccountProfileType::class, $user)->handleRequest($request);
        if ($profile->isSubmitted() && $profile->isValid()) {
            return $this->saveAndReauthenticate($user, 'Profil mis à jour.', 'profil');
        }

        $password = $this->createForm(ChangePasswordType::class)->handleRequest($request);
        if ($password->isSubmitted() && $password->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $password->get('plainPassword')->getData()));

            return $this->saveAndReauthenticate($user, 'Mot de passe modifié.', 'mot-de-passe');
        }

        $preferences = $this->createForm(PreferencesType::class, $user)->handleRequest($request);
        if ($preferences->isSubmitted() && $preferences->isValid()) {
            return $this->saveAndReauthenticate($user, 'Préférences enregistrées.', 'preferences');
        }

        $delete = $this->createForm(DeleteAccountType::class)->handleRequest($request);
        if ($delete->isSubmitted() && $delete->isValid()) {
            $username = $user->getUsername();
            $this->em->remove($user);
            $this->em->flush();
            // Déconnexion sans redirection : la session est invalidée, le message va dans la nouvelle
            $this->security->logout(false);
            $this->addFlash('success', \sprintf('Le compte « %s » et toutes ses données ont été supprimés.', $username));

            return $this->redirectToRoute('app_home');
        }

        $forms = ['identity' => $identity, 'profile' => $profile, 'password' => $password, 'preferences' => $preferences, 'delete' => $delete];
        $views = array_map(static fn (FormInterface $form) => $form->createView(), $forms);
        $invalid = array_any($forms, static fn (FormInterface $form) => $form->isSubmitted());
        if ($invalid) {
            // Le formulaire refusé a déjà modifié l'utilisateur en mémoire (pseudo affiché dans le menu…) :
            // on revient à l'état enregistré, les vues gardent la saisie à corriger
            $this->em->refresh($user);
        }

        return $this->render('account/settings.html.twig', $views, new Response(status: $invalid ? 422 : 200));
    }

    /**
     * Toutes les données du compte en JSON (profil, decks, collections, succès).
     */
    #[Route('/export', name: 'app_account_export', methods: ['GET'])]
    public function export(#[CurrentUser] User $user, AccountDataExporter $exporter): Response
    {
        $filename = \sprintf('grenier-a-mana-%s-%s.json', (new AsciiSlugger())->slug((string) $user->getUsername())->lower(), date('Y-m-d'));
        $response = new JsonResponse($exporter->export($user));
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * Enregistre puis reconnecte l'utilisateur : l'email (identifiant) ou le mot de passe ont pu changer,
     * ce qui sinon le déconnecterait à la requête suivante.
     */
    private function saveAndReauthenticate(User $user, string $message, string $section): Response
    {
        $this->em->flush();
        $this->security->login($user, 'form_login', 'main');
        $this->addFlash('success', $message);

        return $this->redirect($this->generateUrl('app_account').'#'.$section);
    }
}
