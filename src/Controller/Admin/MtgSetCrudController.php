<?php

namespace App\Controller\Admin;

use App\Entity\MtgSet;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * Éditions : les données viennent de Scryfall ; seule l'illustration de couverture est modifiable.
 *
 * @extends AbstractCrudController<MtgSet>
 */
class MtgSetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MtgSet::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Édition')
            ->setEntityLabelInPlural('Éditions')
            ->setSearchFields(['name', 'code'])
            ->setDefaultSort(['releasedAt' => 'DESC'])
            ->setHelp(Crud::PAGE_EDIT, 'Modifier l\'illustration la verrouille : le calcul automatique après les imports ne la remplacera plus. Pour revenir au choix automatique, décocher « Couverture verrouillée » sans toucher à l\'illustration.');
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Voir sur le site', 'fa fa-up-right-from-square')
            ->linkToUrl(fn (MtgSet $set) => $this->generateUrl('app_set_show', ['code' => $set->getCode()]))
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $viewOnSite);
    }

    /**
     * Une couverture modifiée à la main est verrouillée, pour que le calcul automatique ne l'écrase pas.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        if ($original['coverArtUri'] !== $entityInstance->getCoverArtUri() || $original['coverCardName'] !== $entityInstance->getCoverCardName()) {
            $entityInstance->setCoverLocked(true);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(TextFilter::new('setType'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('iconSvgUri', 'Icône')->setTemplatePath('admin/field/set_icon.html.twig')->onlyOnIndex()->setSortable(false);
        yield TextField::new('code', 'Code')->setDisabled();
        yield TextField::new('name', 'Nom')->setDisabled();
        yield TextField::new('setType', 'Type')->setDisabled();
        yield DateField::new('releasedAt', 'Sortie')->setDisabled();
        yield IntegerField::new('cardCount', 'Cartes')->setDisabled();
        yield TextField::new('coverArtUri', 'Couverture')->setTemplatePath('admin/field/cover.html.twig')->hideOnForm()->setSortable(false);
        yield UrlField::new('coverArtUri', 'URL de l\'illustration (art_crop Scryfall)')->onlyOnForms();
        yield TextField::new('coverCardName', 'Carte de couverture');
        yield BooleanField::new('coverLocked', 'Couverture verrouillée')->renderAsSwitch(false);
    }
}
