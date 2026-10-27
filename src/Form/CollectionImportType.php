<?php

namespace App\Form;

use App\Entity\CardCollection;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Import d'un export CSV ManaBox dans une collection existante ou nouvelle.
 *
 * @extends AbstractType<array{collection: ?CardCollection, name: ?string, file: mixed, replace: bool}>
 */
class CollectionImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $owner */
        $owner = $options['owner'];

        $builder
            ->add('collection', EntityType::class, [
                'class' => CardCollection::class,
                'label' => 'Collection',
                'required' => false,
                'placeholder' => '+ Nouvelle collection',
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('c')
                    ->where('c.owner = :owner')->setParameter('owner', $owner)->orderBy('c.name', SortDirection::Ascending),
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de la nouvelle collection',
                'required' => false,
                'attr' => ['placeholder' => 'Ma collection ManaBox', 'maxlength' => 100],
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier CSV exporté depuis ManaBox',
                'attr' => ['accept' => '.csv,text/csv'],
                'constraints' => [
                    new Assert\NotNull(message: 'Choisis le fichier CSV à importer.'),
                    new Assert\File(maxSize: '5M', extensions: ['csv', 'txt'], extensionsMessage: 'Envoie le fichier .csv exporté par ManaBox.'),
                ],
            ])
            ->add('replace', CheckboxType::class, [
                'label' => 'Remplacer le contenu de la collection (pour réimporter un export complet sans doublons)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('owner');
        $resolver->setAllowedTypes('owner', User::class);
    }
}
