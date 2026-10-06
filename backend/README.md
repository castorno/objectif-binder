# backend

API Symfony de tcgCollector (PHP 8.4+, Symfony 8, PostgreSQL, Doctrine ORM).

## Lancer le backend

```bash
docker compose up -d
```

- API accessible sur `http://localhost:8080`
- Healthcheck : `curl http://localhost:8080/api/health`
- Les migrations Doctrine s'exécutent automatiquement au démarrage du conteneur.

## Endpoints

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/health` | État de santé (API + base de données) |
| GET | `/api/games` | Liste des jeux |
| GET | `/api/games/{slug}/sets` | Extensions d'un jeu, de la plus récente à la plus ancienne |
| GET | `/api/games/{slug}/rarities` | Raretés d'un jeu, triées par `sortOrder` |
| GET | `/api/cards` | Recherche de cartes (`q`, `game`, `set`, `rarity`, `page`, `limit`) |
| GET | `/api/cards/{id}` | Fiche carte détaillée, inclut `pullOddsOneIn` si un `PullRate` est défini pour sa rareté |

Toute erreur sur une route `/api/*` est renvoyée en JSON (`{"error": "..."}`) avec le code HTTP approprié — y compris les 404/422 par défaut de Symfony, normalement rendus en HTML, interceptés par `ApiExceptionListener`.

## Tests

```bash
# Une seule fois : créer la base de test et y appliquer les migrations
docker compose exec php php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php php bin/console --env=test doctrine:migrations:migrate --no-interaction

# Lancer la suite
docker compose exec php php bin/phpunit
```

Convention suivie : les tests de bout en bout (requête HTTP réelle) étendent `WebTestCase`, les tests de services/logique métier étendent `KernelTestCase`. Les tests qui touchent la base de données ouvrent une transaction en `setUp()` et la annulent (`rollBack`) en `tearDown()`, pour ne jamais laisser de données résiduelles entre deux tests.

## Commandes utiles

```bash
docker compose exec php php bin/console doctrine:migrations:diff   # générer une migration depuis les entités
docker compose exec php php bin/console doctrine:schema:validate   # vérifier que le schéma correspond au mapping
docker compose exec php php bin/console lint:container             # valider le câblage des services
```

## Documentation

Voir [`docs/data-model.md`](../docs/data-model.md) à la racine du repo pour le détail du modèle de données et les décisions de conception.
