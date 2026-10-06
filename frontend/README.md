# frontend

SPA React/TypeScript d'Objectif Binder : catalogue de cartes (recherche, filtres, pagination) et fiche carte. Voir le [README racine](../README.md) pour l'installation et le lancement.

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
