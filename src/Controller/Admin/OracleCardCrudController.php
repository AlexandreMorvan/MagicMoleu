<?php

namespace App\Controller\Admin;

use App\Entity\OracleCard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * Consultation des cartes : en lecture seule, les données viennent de Scryfall et seraient écrasées au prochain import.
 *
 * @extends AbstractCrudController<OracleCard>
 */
class OracleCardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OracleCard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Carte')
            ->setEntityLabelInPlural('Cartes')
            ->setSearchFields(['name', 'typeLine', 'oracleText'])
            ->setDefaultSort(['name' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Voir sur le site', 'fa fa-up-right-from-square')
            ->linkToUrl(fn (OracleCard $card) => $this->generateUrl('app_card_show', ['id' => $card->getId()]))
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewOnSite);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('layout'))
            ->add(NumericFilter::new('cmc'))
            ->add(BooleanFilter::new('reserved'))
            ->add(BooleanFilter::new('gameChanger'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('imageUri', 'Image')->setTemplatePath('admin/field/card_image.html.twig')->setSortable(false);
        yield TextField::new('name', 'Nom');
        yield TextField::new('manaCost', 'Coût')->setTemplatePath('admin/field/mana.html.twig');
        yield TextField::new('typeLine', 'Type');
        yield NumberField::new('cmc', 'CMC')->setNumDecimals(1);
        yield TextField::new('layout', 'Layout')->hideOnIndex();
        yield TextareaField::new('oracleText', 'Texte')->onlyOnDetail()->setTemplatePath('admin/field/mana.html.twig');
        yield IntegerField::new('edhrecRank', 'Rang EDHREC');
        yield BooleanField::new('reserved', 'Liste réservée')->renderAsSwitch(false)->hideOnIndex();
        yield BooleanField::new('gameChanger', 'Game changer')->renderAsSwitch(false)->hideOnIndex();
    }
}
