# frontend

SPA React/TypeScript d'Objectif Binder : catalogue de cartes (recherche, filtres, pagination, vue regroupée par identité), fiche carte, collection et taux de complétion, inscription, connexion et page de compte. Voir le [README racine](../README.md) pour l'installation et le lancement.

## Organisation du code

```
src/
├── api/          client HTTP, requêtes (TanStack Query) et types de l'API
├── components/   composants partagés (mise en page, champs, messages d'état)
├── features/
│   ├── auth/         connexion, inscription, session, garde de route
│   ├── cards/        catalogue, filtres, fiche carte
│   ├── collection/   cartes possédées, barre de progression
│   └── identities/   vue regroupée du catalogue
├── lib/          fonctions sans dépendance à React ni à l'API
└── test/         API simulée, jeux de données et outils de test
```

L'état de la recherche (filtres, page, vue) vit dans l'URL, pas dans un état React : voir [`docs/collection.md`](../docs/collection.md#côté-frontend).

## Accès à l'API

Le code appelle l'API par des adresses relatives (`/api/cards`). En développement, le serveur Vite relaie `/api` vers le conteneur de l'API (bloc `server.proxy` de `vite.config.ts`, cible définie par `API_PROXY_TARGET` dans le `compose.yaml` racine). La page et l'API ont ainsi la même origine : pas de CORS, et le cookie de session est un cookie de même origine.

Hors Docker, la cible par défaut est `http://localhost:8080`.

## Authentification

Le jeton d'accès n'est gardé qu'en mémoire ; la session survit au rechargement grâce à un cookie `HttpOnly` que le code ne voit pas. Le fonctionnement et les choix de sécurité sont décrits dans [`docs/authentication.md`](../docs/authentication.md#côté-frontend).

Dans les tests, personne n'est connecté par défaut ; `signInAs()`, `allowLogin()` et `allowRegistration()` (`src/test/session.ts`) simulent une session, une connexion ou une inscription. De même la collection est vide par défaut ; `haveCollection()` (`src/test/collection.ts`) en simule une, tenue à jour au fil des ajouts et retraits que fait l'application.

## Design

Les couleurs sont des jetons sémantiques définis une seule fois dans `src/index.css` (`bg-surface`, `text-muted`, `bg-accent`…), avec leur variante sombre : les composants n'utilisent jamais une couleur brute. Les titres et les chiffres clés utilisent la police Space Grotesk, servie par le site lui-même ; le texte courant reste dans la police du système.

## Tests

```bash
docker compose exec frontend npm test             # une passe
docker compose exec frontend npm run test:watch   # relance à chaque modification
```

Les tests tournent avec [Vitest](https://vitest.dev/) dans un DOM simulé (jsdom) ; aucun navigateur ni API démarrée n'est nécessaire.

Conventions suivies :

- un fichier de test vit à côté du code qu'il couvre (`odds.test.ts` à côté de `odds.ts`) ;
- les composants sont interrogés par rôle et par libellé ([React Testing Library](https://testing-library.com/)), comme le ferait un utilisateur ou un lecteur d'écran, pas par classe CSS ;
- les tests d'écran montent toute l'application avec `renderApp(url)` (`src/test/render.tsx`) : vraies routes, historique en mémoire, cache de requêtes neuf ;
- l'API est simulée au niveau réseau par [MSW](https://mswjs.io/) (`src/test/server.ts`), ce qui fait passer les tests par le vrai client `fetch`. Un test qui a besoin d'une erreur ou d'un résultat vide remplace une route avec `server.use(...)` ; un appel sans réponse prévue fait échouer le test.

## Commandes utiles

```bash
docker compose exec frontend npm run lint    # analyse statique (oxlint)
docker compose exec frontend npm run build   # vérification des types puis build de production
```
