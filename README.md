# Objectif Binder

Application web de gestion et consultation de cartes à collectionner (Trading Card Games).

Projet personnel conçu pour être générique : il n'est lié à aucune licence ou jeu de cartes en particulier (Pokémon, Magic, Yu-Gi-Oh...). Les jeux, extensions et cartes sont des données, pas des hypothèses codées en dur.

## Statut

🚧 En cours de construction. Le MVP (Phase 2) est fonctionnel sur un jeu de démonstration fictif ; l'import (Phase 3) est en cours : l'import de fichiers (JSON Lines, CSV) et une première source de données réelles (TCGdex) existent.

## Fonctionnalités

**Disponibles**

- **Catalogue** : recherche par nom, filtres par jeu, extension et rareté, pagination, fiche détaillée par carte avec sa probabilité d'obtention dans un booster.
- **Comptes** : inscription, connexion, session conservée d'une visite à l'autre.
- **Collection** : cartes possédées par langue, avec quantité et état, gérées depuis la fiche carte.
- **Complétion** : le catalogue affiche la part de la recherche en cours que l'on possède, marque les cartes possédées et se filtre sur les cartes possédées ou manquantes.
- **Vue regroupée** : une entrée par « identité » (une créature qui revient d'extension en extension) au lieu d'une entrée par carte, avec la progression dans chacune et le nombre d'entrées commencées. Elle se filtre par groupe d'identités (les générations), et l'ajout de la première carte d'une identité est signalé.
- **Prix estimé** : sur la fiche d'une carte, pour un utilisateur connecté, le prix relevé sur une place de marché, gardé un mois avant d'être redemandé.

- **Import** : chargement du catalogue depuis un fichier JSON Lines ou CSV, en ligne de commande, avec essai à blanc, rapport et historique. Relancer un import ne crée aucun doublon. Une commande télécharge les extensions Pokémon depuis la base ouverte [TCGdex](https://tcgdex.dev).

**Prévues**

- Page d'administration des imports ; scraper configurable.
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
- [`docs/import.md`](./docs/import.md) : import du catalogue et format des fichiers.

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

- Aucune donnée réelle fournie avec le dépôt : le jeu de démonstration est fictif. Les données réelles se téléchargent depuis TCGdex, en français uniquement (voir [`docs/import.md`](./docs/import.md)).
- Aucune image de carte par défaut : chaque carte reçoit un visuel généré à partir de son nom. L'affichage d'images servies par un tiers est une option de l'import, à activer en connaissance de cause (voir [`docs/import.md`](./docs/import.md)).
- Interface en français uniquement.
- Configuration de développement seulement : voir « Avant une mise en production » dans [`docs/authentication.md`](./docs/authentication.md).
- Performances non mesurées à l'échelle d'un vrai catalogue : voir [`docs/collection.md`](./docs/collection.md#limites-connues).

## Import / données externes

Le dépôt ne contient et ne contiendra aucune image ou jeu de données protégé par le droit d'auteur, et l'application ne stocke aucune image de carte. Le jeu de démonstration fourni par `app:demo:seed` est inventé pour le projet. Le format des fichiers importés et le fonctionnement de l'import sont décrits dans [`docs/import.md`](./docs/import.md) ; les fichiers eux-mêmes vont dans `backend/var/import/`, ignoré par Git. Les données réelles viennent de sources publiques dont les conditions d'utilisation autorisent cet usage. La première est [TCGdex](https://tcgdex.dev), dont la base de données est publiée sous licence MIT par le projet TCGdex ; merci à ses contributeurs. Les marques et noms de jeux cités appartiennent à leurs propriétaires respectifs ; ce projet n'a aucun lien officiel avec eux.

## Licence

MIT — voir [LICENSE](./LICENSE). Cette licence couvre le code source du projet, pas les données ou visuels de cartes tiers.
