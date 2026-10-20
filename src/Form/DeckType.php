<?php

namespace App\Form;

use App\Entity\Deck;
use App\Enum\Format;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Deck>
 */
class DeckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du deck',
                'attr' => ['placeholder' => 'Mono-Red Aggro'],
            ])
            ->add('format', EnumType::class, [
                'class' => Format::class,
                'label' => 'Format',
                'choices' => Format::deckFormats(),
                'choice_label' => static fn (Format $f) => $f->label(),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('public', CheckboxType::class, [
                'label' => 'Deck public (visible par tous)',
                'required' => false,
            ])
            ->add('list', TextareaType::class, [
                'label' => $options['list_label'],
                'mapped' => false,
                'required' => false,
                'help' => 'Une carte par ligne (« 4 Lightning Bolt »). Sections reconnues : Commander, Deck, Sideboard, Maybeboard.',
                'attr' => ['rows' => 10, 'class' => 'font-mono text-sm', 'placeholder' => "Commander\n1 Atraxa, Praetors' Voice\n\nDeck\n1 Sol Ring\n35 Forest"],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deck::class,
            'list_label' => 'Importer une liste (optionnel)',
        ]);
    }
}
