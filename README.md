# Objectif Binder

Application web de gestion et consultation de cartes à collectionner (Trading Card Games).

Projet personnel conçu pour être générique : il n'est lié à aucune licence ou jeu de cartes en particulier (Pokémon, Magic, Yu-Gi-Oh...). Les jeux, extensions et cartes sont des données, pas des hypothèses codées en dur.

## Statut

🚧 En cours de construction — Phase 2 (MVP) : catalogue consultable (recherche, filtres, fiche carte avec probabilité d'obtention) et comptes utilisateur (inscription, connexion, session conservée) et collection personnelle (cartes possédées par langue, quantité, état). Favoris et statistiques à venir.

## Fonctionnalités prévues

- Recherche et filtres sur une base de cartes (jeu, extension, rareté, nom)
- Fiche détaillée par carte
- Gestion de collection personnelle (cartes possédées, quantités, favoris)
- Statistiques de collection (taux de complétion par extension, etc.)
- Import de données depuis plusieurs sources (JSON, CSV, API publique, scraper configurable)

## Stack technique

- **Backend** : PHP 8.4+, Symfony 8, Doctrine, PostgreSQL, authentification JWT (Lexik) avec jetons de rafraîchissement
- **Frontend** : TypeScript, React, React Router, Vite, TanStack Query, Tailwind CSS
- **Infra** : Docker Compose, GitHub Actions (CI)

## Architecture

Monolithe Symfony exposant une API REST, consommée par une SPA React découplée. Le module d'import/scraping est indépendant du domaine métier et s'exécute en ligne de commande.

Documentation détaillée dans `docs/` :

- [`docs/data-model.md`](./docs/data-model.md) : modèle de données.
- [`docs/authentication.md`](./docs/authentication.md) : authentification et choix de sécurité.

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

Le navigateur ne parle qu'au frontend : celui-ci relaie `/api` vers l'API, si bien que la page et l'API partagent la même origine. Le port 8080 reste ouvert pour appeler l'API directement (`curl`, healthcheck).

`docker compose up` à la racine démarre tout (Postgres, API Symfony, frontend React en mode dev avec hot-reload). Les services backend sont définis dans `backend/compose.yaml` et inclus ici (`include:`) plutôt que dupliqués — `cd backend && docker compose up` reste utilisable pour travailler sur le backend seul.

## Tests

- **Backend** (PHPUnit) : voir [`backend/README.md`](./backend/README.md#tests).
- **Frontend** (Vitest, React Testing Library, MSW) : voir [`frontend/README.md`](./frontend/README.md#tests).

```bash
docker compose exec php php bin/phpunit   # backend, après création de la base de test
docker compose exec frontend npm test     # frontend
```

## Import / données externes

Le dépôt ne contient et ne contiendra aucune image ou jeu de données protégé par le droit d'auteur. Le jeu de démonstration fourni par `app:demo:seed` est inventé pour le projet. Les données réelles seront importées depuis des sources publiques dont les conditions d'utilisation autorisent explicitement cet usage (ex. API publiques de données de cartes). Les marques et noms de jeux cités appartiennent à leurs propriétaires respectifs ; ce projet n'a aucun lien officiel avec eux.

## Licence

MIT — voir [LICENSE](./LICENSE). Cette licence couvre le code source du projet, pas les données ou visuels de cartes tiers.
