# MagicMoleu

Site Magic: The Gathering : recherche de cartes, decks Standard / Commander avec vérification des ban lists, spoilers.
Données issues de [Scryfall](https://scryfall.com/docs/api), stockées dans MariaDB.

**Stack** : PHP 8.4, Symfony 8.1, Doctrine ORM, Twig + Symfony UX (LiveComponent, Turbo), Tailwind CSS 4 (binaire autonome, pas de Node).

## Installation

```bash
composer install

# Base de données : renseigner DATABASE_URL dans .env.local (et .env.test.local pour les tests)
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate

# Données Scryfall
bin/console app:scryfall:import-sets
bin/console app:scryfall:import-cards               # ~80 Mo, ~1 min 30
bin/console app:scryfall:import-localized --lang=fr # ~2 Go, noms et textes français

# CSS et serveur
bin/console tailwind:build --watch
symfony serve -d
```

## Import Scryfall

| Commande | Source | Planification (UTC) |
|---|---|---|
| `app:scryfall:sync-spoilers` | `GET /sets` + recherche des cartes des 30 derniers jours et à venir | toutes les heures (h+05) |
| `app:scryfall:import-cards [--force]` | bulk `default-cards` (JSONL gzip) | tous les jours à 10 h 15 |
| `app:scryfall:import-localized --lang=fr [--force]` | bulk `all-cards` | le lundi à 11 h 30 |
| `app:scryfall:import-sets` | `GET /sets` | (inclus dans `sync-spoilers`) |

Les fichiers bulk sont mis en cache dans `var/scryfall/` et ne sont retéléchargés / réimportés que si Scryfall
en publie une nouvelle version (`--force` pour réimporter quand même). Un verrou empêche deux imports simultanés.

> **En environnement `dev`, lancer les imports avec `--no-debug`.** En mode debug, Doctrine conserve chaque
> requête SQL pour le profiler : la mémoire grimpe jusqu'à la limite (1 Go) et `import-cards` plante vers
> 90 000 cartes. Avec `--no-debug`, elle reste stable (~80 Mo). Même chose pour le worker `messenger:consume`.
> Inutile en `prod`, où le debug est désactivé.
>
> ```bash
> bin/console app:scryfall:import-cards --no-debug
> bin/console messenger:consume scheduler_default --no-debug
> ```

### Planification

Les tâches sont déclarées dans `src/Schedule.php` (Symfony Scheduler) et exécutées par un worker Messenger,
à garder actif (systemd, Supervisor…) :

```bash
bin/console messenger:consume scheduler_default
bin/console debug:scheduler   # prochaines exécutions
```

## Modèle de données

- `OracleCard` : la carte en tant que règle (oracle_id Scryfall) ; couleurs et identité couleur en **bitmask WUBRG**
  (`App\Enum\Color`), ce qui permet de tester l'identité Commander en SQL : `color_identity & ~:mask = 0`.
- `CardFace` : faces des cartes multi-faces (transform, MDFC, split, adventure…).
- `CardPrinting` : une impression (édition, numéro, langue, images, prix). Le drapeau `extra` (variante sans bordure,
  showcase, extended art, traitement foil spécial, promo…) est calculé à l'import ; ces cartes sont masquées par défaut
  sur la page d'une édition (case « Afficher les extras »).
- `MtgSet`, `CardLegality` (format × statut), `CardLocalization` (noms/textes traduits), `User`.
- `CardCollection` / `CollectionCard` : collections privées d'un utilisateur ; une ligne par impression et finition (normale / foil).

Les identifiants Scryfall servent de clés primaires (BINARY(16)) ; l'import écrit via DBAL par lots
(`INSERT … ON DUPLICATE KEY UPDATE`), il est donc idempotent.

## Collections

Section « Collection » (utilisateurs connectés) : plusieurs collections par utilisateur, vues en vignettes ou en liste,
filtres (nom, rareté, couleur), classement (par édition, tranche de prix ou rareté : sous des en-têtes avec nombre de
cartes et valeur) et statistiques (valeur en euros, normale ou foil selon l'exemplaire, cartes uniques,
raretés, couleurs, cartes les plus chères, éditions principales).

- **Ajout d'une carte** : depuis sa fiche (modale de la recherche, des éditions, des spoilers, des decks…), composant
  live `CollectionAdder` rendu en différé pour que la fiche, identique pour tous, garde son cache navigateur de 24 h.
- **Ajout d'un deck** : depuis la page du deck, toutes les cartes jouées (commandant, deck, réserve) avec les impressions
  choisies dans le deck, dans une collection existante ou une nouvelle.

Les decks s'affichent en liste, en vignettes ou en tableau détaillé, groupés par type, couleur ou CCM
(`?vue=…&groupe=…` sur la page du deck, boutons dans l'éditeur).

## Back-office

Interface EasyAdmin sur `/admin`, réservée au rôle `ROLE_ADMIN` : tableau de bord, utilisateurs (rôles),
decks, succès débloqués, notifications (envoi d'un message à un utilisateur), cartes (lecture seule) et éditions
(choix manuel de l'illustration de couverture, protégée du calcul automatique).

```bash
bin/console app:user:promote <email-ou-pseudo>            # donner le rôle admin
bin/console app:user:promote <email-ou-pseudo> --revoke   # le retirer
```

## Succès et notifications

Le catalogue des succès est l'enum `App\Enum\Achievement` (textes, rareté, icône, condition).
`AchievementChecker` débloque les succès mérités et crée une notification (cloche du menu + toast) ;
il est appelé à l'inscription, à chaque modification de deck et une fois par jour et par session (anniversaires).

## Tests

```bash
bin/console doctrine:database:create --env=test
bin/console doctrine:migrations:migrate --env=test
php bin/phpunit
```
