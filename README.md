# tcgCollector

Application web de gestion et consultation de cartes à collectionner (Trading Card Games).

Projet personnel conçu pour être générique : il n'est lié à aucune licence ou jeu de cartes en particulier (Pokémon, Magic, Yu-Gi-Oh...). Les jeux, extensions et cartes sont des données, pas des hypothèses codées en dur.

## Statut

🚧 En cours de construction — Phase 1 (Foundation).

## Fonctionnalités prévues

- Recherche et filtres sur une base de cartes (jeu, extension, rareté, nom)
- Fiche détaillée par carte
- Gestion de collection personnelle (cartes possédées, quantités, favoris)
- Statistiques de collection (taux de complétion par extension, etc.)
- Import de données depuis plusieurs sources (JSON, CSV, API publique, scraper configurable)

## Stack technique

- **Backend** : PHP 8.3, Symfony 7, Doctrine, PostgreSQL, JWT (Lexik)
- **Frontend** : TypeScript, React, Vite, TanStack Query, Tailwind CSS
- **Infra** : Docker Compose, GitHub Actions (CI)

## Architecture

Monolithe Symfony exposant une API REST, consommée par une SPA React découplée. Le module d'import/scraping est indépendant du domaine métier et s'exécute en ligne de commande.

Documentation détaillée à venir dans `docs/`.

## Installation

```bash
git clone git@github.com:castorno/tcgCollector.git
cd tcgCollector
docker compose up
```

(Instructions détaillées à venir une fois le squelette backend/frontend en place.)

## Tests

À venir.

## Import / données externes

Le dépôt ne contient et ne contiendra aucune image ou jeu de données protégé par le droit d'auteur. Les données de démonstration sont importées depuis des sources publiques dont les conditions d'utilisation autorisent explicitement cet usage (ex. API publiques de données de cartes). Les marques et noms de jeux cités appartiennent à leurs propriétaires respectifs ; ce projet n'a aucun lien officiel avec eux.

## Licence

MIT — voir [LICENSE](./LICENSE). Cette licence couvre le code source du projet, pas les données ou visuels de cartes tiers.
