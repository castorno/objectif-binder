# Objectif Binder

Application web de gestion et consultation de cartes à collectionner (Trading Card Games).

Projet personnel conçu pour être générique : il n'est lié à aucune licence ou jeu de cartes en particulier (Pokémon, Magic, Yu-Gi-Oh...). Les jeux, extensions et cartes sont des données, pas des hypothèses codées en dur.

## Statut

🚧 En cours de construction. Le MVP (Phase 2) est fonctionnel sur un jeu de démonstration fictif ; l'import de données réelles (Phase 3) est la prochaine étape.

## Fonctionnalités

**Disponibles**

- **Catalogue** : recherche par nom, filtres par jeu, extension et rareté, pagination, fiche détaillée par carte avec sa probabilité d'obtention dans un booster.
- **Comptes** : inscription, connexion, session conservée d'une visite à l'autre.
- **Collection** : cartes possédées par langue, avec quantité et état, gérées depuis la fiche carte.
- **Complétion** : le catalogue affiche la part de la recherche en cours que l'on possède, marque les cartes possédées et se filtre sur les cartes possédées ou manquantes.
- **Vue regroupée** : une entrée par « identité » (une créature qui revient d'extension en extension) au lieu d'une entrée par carte, avec la progression dans chacune et le nombre d'entrées commencées.

**Prévues**

- Import de données depuis plusieurs sources (JSON, CSV, API publique, scraper configurable).
- Favoris.

## Stack technique

- **Backend** : PHP 8.4+, Symfony 8, Doctrine, PostgreSQL, authentification JWT (Lexik) avec jetons de rafraîchissement
- **Frontend** : TypeScript, React, React Router, Vite, TanStack Query, Tailwind CSS
- **Infra** : Docker Compose, GitHub Actions (CI)

## Architecture

Monolithe Symfony exposant une API REST, consommée par une SPA React découplée. Le module d'import/scraping est indépendant du domaine métier et s'exécute en ligne de commande.

Documentation détaillée dans `docs/` :

- [`docs/data-model.md`](./docs/data-model.md) : modèle de données.
- [`docs/authentication.md`](./docs/authentication.md) : authentification et choix de sécurité.
- [`docs/collection.md`](./docs/collection.md) : collection, taux de complétion et vue regroupée.

## Installation

```bash
git clone git@github.com:castorno/objectif-binder.git
cd objectif-binder
docker compose up -d --build
```

Le catalogue est vide au premier lancement. Pour insérer un jeu de démonstration entièrement fictif (2 extensions, 120 cartes, 6 créatures servant d'identités, taux d'obtention) :

```bash
docker compose exec php php bin/console app:demo:seed
```

Créez ensuite un compte depuis l'écran d'inscription pour essayer la collection et la complétion.

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

## Limitations

- Aucune donnée réelle : seul le jeu de démonstration fictif est disponible tant que l'import n'existe pas.
- Aucune image de carte : chaque carte reçoit un visuel généré à partir de son nom.
- Interface en français uniquement.
- Configuration de développement seulement : voir « Avant une mise en production » dans [`docs/authentication.md`](./docs/authentication.md).
- Performances non mesurées à l'échelle d'un vrai catalogue : voir [`docs/collection.md`](./docs/collection.md#limites-connues).

## Import / données externes

Le dépôt ne contient et ne contiendra aucune image ou jeu de données protégé par le droit d'auteur. Le jeu de démonstration fourni par `app:demo:seed` est inventé pour le projet. Les données réelles seront importées depuis des sources publiques dont les conditions d'utilisation autorisent explicitement cet usage (ex. API publiques de données de cartes). Les marques et noms de jeux cités appartiennent à leurs propriétaires respectifs ; ce projet n'a aucun lien officiel avec eux.

## Licence

MIT — voir [LICENSE](./LICENSE). Cette licence couvre le code source du projet, pas les données ou visuels de cartes tiers.
