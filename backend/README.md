# backend

API Symfony de tcgCollector (PHP 8.4+, Symfony 8, PostgreSQL, Doctrine ORM).

## Lancer le backend

```bash
docker compose up -d
```

- API accessible sur `http://localhost:8080`
- Healthcheck : `curl http://localhost:8080/api/health`
- Les migrations Doctrine s'exécutent automatiquement au démarrage du conteneur.

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
