# Objectif Binder

Application web de gestion et consultation de cartes à collectionner (Trading Card Games).

Projet personnel conçu pour être générique : il n'est lié à aucune licence ou jeu de cartes en particulier (Pokémon, Magic, Yu-Gi-Oh...). Les jeux, extensions et cartes sont des données, pas des hypothèses codées en dur.

## Statut

🚧 En cours de construction — Phase 2 (MVP) : catalogue consultable (recherche, filtres, fiche carte avec probabilité d'obtention). Authentification et gestion de collection à venir.

## Fonctionnalités prévues

- Recherche et filtres sur une base de cartes (jeu, extension, rareté, nom)
- Fiche détaillée par carte
- Gestion de collection personnelle (cartes possédées, quantités, favoris)
- Statistiques de collection (taux de complétion par extension, etc.)
- Import de données depuis plusieurs sources (JSON, CSV, API publique, scraper configurable)

## Stack technique

- **Backend** : PHP 8.4+, Symfony 8, Doctrine, PostgreSQL, JWT (Lexik, à venir)
- **Frontend** : TypeScript, React, React Router, Vite, TanStack Query, Tailwind CSS
- **Infra** : Docker Compose, GitHub Actions (CI)

## Architecture

Monolithe Symfony exposant une API REST, consommée par une SPA React découplée. Le module d'import/scraping est indépendant du domaine métier et s'exécute en ligne de commande.

Documentation détaillée à venir dans `docs/`.

## Installation

```bash
git clone git@github.com:castorno/objectif-binder.git
cd objectif-binder
docker compose up -d --build
```

Le catalogue est vide au premier lancement. Pour insérer un jeu de démonstration entièrement fictif (2 extensions, 120 cartes, taux d'obtention) :

```bash
docker compose exec php php bin/console app:demo:seed
```

- Frontend : http://localhost:5173
- API : http://localhost:8080 (healthcheck : `curl http://localhost:8080/api/health`)

`docker compose up` à la racine démarre tout (Postgres, API Symfony, frontend React en mode dev avec hot-reload). Les services backend sont définis dans `backend/compose.yaml` et inclus ici (`include:`) plutôt que dupliqués — `cd backend && docker compose up` reste utilisable pour travailler sur le backend seul.

## Tests

Voir [`backend/README.md`](./backend/README.md#tests) pour lancer la suite PHPUnit.

## Import / données externes

Le dépôt ne contient et ne contiendra aucune image ou jeu de données protégé par le droit d'auteur. Le jeu de démonstration fourni par `app:demo:seed` est inventé pour le projet. Les données réelles seront importées depuis des sources publiques dont les conditions d'utilisation autorisent explicitement cet usage (ex. API publiques de données de cartes). Les marques et noms de jeux cités appartiennent à leurs propriétaires respectifs ; ce projet n'a aucun lien officiel avec eux.

## Licence

MIT — voir [LICENSE](./LICENSE). Cette licence couvre le code source du projet, pas les données ou visuels de cartes tiers.
