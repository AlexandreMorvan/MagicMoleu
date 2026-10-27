<?php

namespace App\Controller\Admin;

use App\Entity\MtgSet;
use App\Set\SetLogoException;
use App\Set\SetLogoStorage;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
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
 * Éditions : les données viennent de Scryfall ; seuls l'illustration de couverture et le logo sont modifiables.
 *
 * @extends AbstractCrudController<MtgSet>
 */
class MtgSetCrudController extends AbstractCrudController
{
    public function __construct(private readonly SetLogoStorage $logos)
    {
    }

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
        $this->updateLogo($entityInstance, $original['logoSourceUrl']);

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Logo : la case « supprimer » l'emporte, puis un fichier envoyé, puis une nouvelle URL.
     * En cas d'échec, l'ancien logo est gardé et l'administrateur est prévenu.
     */
    private function updateLogo(MtgSet $set, ?string $previousUrl): void
    {
        $request = $this->getContext()->getRequest();
        $file = $request->files->all('MtgSet')['logoFile'] ?? null;
        $url = trim((string) $set->getLogoSourceUrl());

        try {
            if ($request->request->all('MtgSet')['removeLogo'] ?? false) {
                $this->logos->remove($set);
                $this->addFlash('success', 'Logo de l\'édition supprimé.');
            } elseif ($file instanceof UploadedFile) {
                $this->logos->upload($set, $file);
                $this->addFlash('success', 'Logo de l\'édition mis à jour.');
            } elseif ('' !== $url && $url !== (string) $previousUrl) {
                $this->logos->download($set, $url);
                $this->addFlash('success', 'Logo de l\'édition mis à jour.');
            }
        } catch (SetLogoException $e) {
            $set->setLogoSourceUrl($previousUrl);
            $this->addFlash('danger', 'Logo non enregistré : '.$e->getMessage());
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(TextFilter::new('setType'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('iconSvgUri', 'Icône')->setTemplatePath('admin/field/set_icon.html.twig')->onlyOnIndex()->setSortable(false);
        yield TextField::new('logoPath', 'Logo')->setTemplatePath('admin/field/set_logo.html.twig')->hideOnForm()->setSortable(false);
        yield TextField::new('code', 'Code')->setDisabled();
        yield TextField::new('name', 'Nom')->setDisabled();
        yield TextField::new('setType', 'Type')->setDisabled();
        yield DateField::new('releasedAt', 'Sortie')->setDisabled();
        yield IntegerField::new('cardCount', 'Cartes')->setDisabled();
        yield TextField::new('coverArtUri', 'Couverture')->setTemplatePath('admin/field/cover.html.twig')->hideOnForm()->setSortable(false);
        yield UrlField::new('coverArtUri', 'URL de l\'illustration (art_crop Scryfall)')->onlyOnForms();
        yield TextField::new('coverCardName', 'Carte de couverture');
        yield BooleanField::new('coverLocked', 'Couverture verrouillée')->renderAsSwitch(false);

        // Logo (titre stylisé) : envoi d'un fichier enregistré depuis n'importe quel site, ou URL téléchargée par le serveur
        $set = $this->getContext()?->getEntity()?->getInstance();
        yield Field::new('logoFile', 'Logo : envoyer une image')->onlyOnForms()
            ->setFormType(FileType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/png,image/webp,image/jpeg'],
                'constraints' => [new Image(maxSize: '2M', mimeTypes: ['image/png', 'image/webp', 'image/jpeg'], mimeTypesMessage: 'Envoie une image PNG, WebP ou JPEG.')],
            ])
            ->setHelp(($set instanceof MtgSet && $set->getLogoPath()
                ? \sprintf('<img src="%s" alt="" style="max-height:60px;max-width:240px;display:block;margin:4px 0;background:#1b2030;padding:4px;border-radius:4px">', htmlspecialchars('/'.$set->getLogoPath()))
                : '').'PNG, WebP ou JPEG, 2 Mo maximum (fond transparent conseillé). Remplace le logo actuel.');
        yield UrlField::new('logoSourceUrl', 'Logo : ou URL de l\'image')->onlyOnForms()
            ->setHelp('Téléchargée par le serveur, par exemple depuis la page produit sur magic.wizards.com (clic droit sur le logo → copier l\'adresse de l\'image). Les sites protégés contre les robots (magic-ville, wiki) refusent : enregistre l\'image et envoie-la plutôt.');
        if ($set instanceof MtgSet && $set->getLogoPath()) {
            yield BooleanField::new('removeLogo', 'Supprimer le logo')->onlyOnForms()->renderAsSwitch(false)
                ->setFormTypeOptions(['mapped' => false, 'required' => false]);
        }
    }
}
