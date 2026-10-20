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

| Commande | Source | Fréquence conseillée |
|---|---|---|
| `app:scryfall:import-sets` | `GET /sets` | quotidienne |
| `app:scryfall:import-cards [--force]` | bulk `default-cards` (JSONL gzip) | quotidienne |
| `app:scryfall:import-localized --lang=fr [--force]` | bulk `all-cards` | hebdomadaire |

Les fichiers bulk sont mis en cache dans `var/scryfall/` et ne sont retéléchargés / réimportés que si Scryfall
en publie une nouvelle version (`--force` pour réimporter quand même). Un verrou empêche deux imports simultanés.

## Modèle de données

- `OracleCard` : la carte en tant que règle (oracle_id Scryfall) ; couleurs et identité couleur en **bitmask WUBRG**
  (`App\Enum\Color`), ce qui permet de tester l'identité Commander en SQL : `color_identity & ~:mask = 0`.
- `CardFace` : faces des cartes multi-faces (transform, MDFC, split, adventure…).
- `CardPrinting` : une impression (édition, numéro, langue, images, prix).
- `MtgSet`, `CardLegality` (format × statut), `CardLocalization` (noms/textes traduits), `User`.

Les identifiants Scryfall servent de clés primaires (BINARY(16)) ; l'import écrit via DBAL par lots
(`INSERT … ON DUPLICATE KEY UPDATE`), il est donc idempotent.

## Tests

```bash
bin/console doctrine:database:create --env=test
bin/console doctrine:migrations:migrate --env=test
php bin/phpunit
```
