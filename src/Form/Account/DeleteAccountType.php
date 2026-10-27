<?php

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * @extends AbstractType<array{password: string}>
 */
class DeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', PasswordType::class, [
            'label' => 'Mot de passe',
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [new UserPassword(message: 'Mot de passe incorrect.')],
        ]);
    }
}
