# backend

API Symfony d'Objectif Binder (PHP 8.4+, Symfony 8, PostgreSQL, Doctrine ORM).

## Lancer le backend

```bash
docker compose up -d
```

- API accessible sur `http://localhost:8080`
- Healthcheck : `curl http://localhost:8080/api/health`
- Les migrations Doctrine s'exécutent automatiquement au démarrage du conteneur.
- Les clés de signature des JWT (`config/jwt/*.pem`, non commitées) sont générées au démarrage si elles manquent.

## Endpoints

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/health` | État de santé (API + base de données) |
| GET | `/api/games` | Liste des jeux |
| GET | `/api/games/{slug}/sets` | Extensions d'un jeu, de la plus récente à la plus ancienne |
| GET | `/api/games/{slug}/rarities` | Raretés d'un jeu, triées par `sortOrder` |
| GET | `/api/cards` | Recherche de cartes (`q`, `game`, `set`, `rarity`, `identity`, `page`, `limit`) |
| GET | `/api/cards/{id}` | Fiche carte détaillée : ses identités, et `pullOddsOneIn` si un `PullRate` est défini pour sa rareté |
| GET | `/api/identities` | Vue regroupée : identités d'un jeu (`q`, `game`, `page`, `limit`) avec leur nombre de cartes |
| GET | `/api/identities/{id}` | Une identité |
| POST | `/api/auth/register` | Création de compte (`email`, `password`) |
| POST | `/api/auth/login` | Connexion : renvoie un JWT et pose le cookie de rafraîchissement |
| POST | `/api/auth/refresh` | Nouveau JWT à partir du cookie de rafraîchissement |
| POST | `/api/auth/logout` | Déconnexion : supprime la session et efface le cookie |
| GET | `/api/me` | Utilisateur connecté (JWT requis) |
| GET | `/api/collection` | Cartes possédées d'une recherche, avec leurs exemplaires par langue (JWT requis, comme toutes les routes `/api/collection`) |
| GET | `/api/collection/missing` | Cartes d'une recherche que l'utilisateur ne possède pas |
| GET | `/api/collection/completion` | Part d'une recherche que l'utilisateur possède |
| GET | `/api/collection/identities` | Progression de l'utilisateur dans une page de la vue regroupée |
| GET | `/api/collection/cards/{id}` | Exemplaires possédés d'une carte |
| PUT | `/api/collection/cards/{id}/{language}` | Ajoute la carte dans une langue, ou remplace sa quantité et son état (`quantity`, `condition`) |
| DELETE | `/api/collection/cards/{id}/{language}` | Retire la carte dans cette langue |

Les routes de liste de `/api/collection` acceptent les paramètres de la route publique qu'elles accompagnent (`/api/cards` ou `/api/identities`). Le paramètre `identity` prend l'identifiant d'une identité, ou `none` pour les cartes qui n'en ont pas.

L'API est fermée par défaut : toute route `/api` exige un JWT (`Authorization: Bearer …`), sauf l'authentification, le healthcheck et la lecture du catalogue (jeux, cartes, identités). Voir [`docs/authentication.md`](../docs/authentication.md).

Toute erreur sur une route `/api/*` est renvoyée en JSON (`{"error": "..."}`) avec le code HTTP approprié — y compris les 404/422 par défaut de Symfony, normalement rendus en HTML, interceptés par `ApiExceptionListener`. Une requête dont le corps échoue à la validation (422) ajoute le détail par champ dans `violations`.

## Tests

```bash
# Une seule fois : créer la base de test et y appliquer les migrations
docker compose exec php php bin/console --env=test doctrine:database:create --if-not-exists
docker compose exec php php bin/console --env=test doctrine:migrations:migrate --no-interaction

# Lancer la suite
docker compose exec php php bin/phpunit
```

Convention suivie : les tests de bout en bout (requête HTTP réelle) étendent `WebTestCase`, les tests de services/logique métier étendent `KernelTestCase`. Les tests qui touchent la base de données ouvrent une transaction en `setUp()` et la annulent (`rollBack`) en `tearDown()`, pour ne jamais laisser de données résiduelles entre deux tests.

Après avoir récupéré une nouvelle migration, la base de test doit la recevoir aussi : relancer la commande `doctrine:migrations:migrate` ci-dessus.

Un test compte les requêtes SQL d'une page du catalogue (`CardControllerTest::testListRunsTheSameNumberOfQueriesWhateverThePageSize`) : il échoue si une relation recommence à être chargée carte par carte.

## Analyse statique

```bash
docker compose exec php vendor/bin/phpstan analyse
```

PHPStan lit le code de `src/` sans l'exécuter et signale les incohérences de types (niveau 8, avec les extensions Symfony et Doctrine). Il s'appuie sur le conteneur de services compilé de l'environnement `dev` : après un changement de configuration resté sans requête, lancer d'abord `bin/console cache:warmup`. Les tests ne sont pas analysés.

La CI lance aussi `composer audit`, qui échoue si une dépendance a une vulnérabilité connue, et construit l'image de production.

## Commandes utiles

```bash
docker compose exec php php bin/console app:import:fetch-tcgdex --set=swsh3            # télécharger une extension depuis TCGdex dans var/import/tcgdex/
docker compose exec php php bin/console app:import var/import/cartes.jsonl --dry-run   # vérifier un fichier d'import sans rien écrire (voir docs/import.md)
docker compose exec php php bin/console app:demo:seed              # insérer le jeu de démonstration fictif, ou le compléter s'il date d'avant les identités
docker compose exec php php bin/console doctrine:migrations:diff   # générer une migration depuis les entités
docker compose exec php php bin/console doctrine:schema:validate   # vérifier que le schéma correspond au mapping
docker compose exec php php bin/console lint:container             # valider le câblage des services
docker compose exec php php bin/console gesdinet:jwt:clear         # supprimer les jetons de rafraîchissement expirés
```

## Documentation

À la racine du repo :

- [`docs/data-model.md`](../docs/data-model.md) : modèle de données et décisions de conception.
- [`docs/authentication.md`](../docs/authentication.md) : authentification, choix de sécurité et limites connues.
- [`docs/collection.md`](../docs/collection.md) : collection, taux de complétion et vue regroupée.
- [`docs/import.md`](../docs/import.md) : import du catalogue et format des fichiers.
