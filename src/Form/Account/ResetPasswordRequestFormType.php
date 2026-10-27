<?php

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{email: string}>
 */
class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Email du compte',
            'attr' => ['autocomplete' => 'email', 'placeholder' => 'toi@exemple.fr'],
            'constraints' => [new Assert\NotBlank(message: 'Indique ton email.'), new Assert\Email(message: 'Cet email n\'est pas valide.')],
        ]);
    }
}
