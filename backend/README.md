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
| GET | `/api/cards` | Recherche de cartes (`q`, `game`, `set`, `rarity`, `page`, `limit`) |
| GET | `/api/cards/{id}` | Fiche carte détaillée, inclut `pullOddsOneIn` si un `PullRate` est défini pour sa rareté |
| POST | `/api/auth/register` | Création de compte (`email`, `password`) |
| POST | `/api/auth/login` | Connexion : renvoie un JWT et pose le cookie de rafraîchissement |
| POST | `/api/auth/refresh` | Nouveau JWT à partir du cookie de rafraîchissement |
| POST | `/api/auth/logout` | Déconnexion : supprime la session et efface le cookie |
| GET | `/api/me` | Utilisateur connecté (JWT requis) |

L'API est fermée par défaut : toute route `/api` exige un JWT (`Authorization: Bearer …`), sauf l'authentification, le healthcheck et la lecture du catalogue. Voir [`docs/authentication.md`](../docs/authentication.md).

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

## Commandes utiles

```bash
docker compose exec php php bin/console app:demo:seed              # insérer le jeu de démonstration fictif (sans effet s'il existe déjà)
docker compose exec php php bin/console doctrine:migrations:diff   # générer une migration depuis les entités
docker compose exec php php bin/console doctrine:schema:validate   # vérifier que le schéma correspond au mapping
docker compose exec php php bin/console lint:container             # valider le câblage des services
docker compose exec php php bin/console gesdinet:jwt:clear         # supprimer les jetons de rafraîchissement expirés
```

## Documentation

À la racine du repo :

- [`docs/data-model.md`](../docs/data-model.md) : modèle de données et décisions de conception.
- [`docs/authentication.md`](../docs/authentication.md) : authentification, choix de sécurité et limites connues.
