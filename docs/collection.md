# Collection et complétion

Ce document décrit comment un utilisateur suit ce qu'il possède, et comment le catalogue lui montre où il en est. Le modèle de données lui-même est dans [`data-model.md`](./data-model.md), l'authentification dans [`authentication.md`](./authentication.md).

## Vue d'ensemble

L'objectif du projet tient en une question : « combien m'en manque-t-il ? ». La réponse n'est pas une page de statistiques à part : elle s'affiche dans le catalogue, sur la recherche en cours. Un collectionneur qui cherche « wyrm » voit combien de wyrms existent, combien il en possède, et lesquels lui manquent.

Trois briques y contribuent :

- **la collection** : les cartes possédées, par langue, avec une quantité et un état ;
- **le taux de complétion** : la part d'une recherche que l'utilisateur possède ;
- **la vue regroupée** : une entrée par identité (une créature, une carte de règles) au lieu d'une entrée par carte.

```
Catalogue (public, identique pour tous)        Collection (propre à l'utilisateur)
────────────────────────────────────────       ───────────────────────────────────────
GET /api/cards        ── mêmes filtres ──►     GET /api/collection            possédées
                                               GET /api/collection/missing    manquantes
                                               GET /api/collection/completion taux

GET /api/identities   ── mêmes filtres ──►     GET /api/collection/identities progression
                                                                              par identité
```

## Points d'entrée

Toutes les routes `/api/collection` exigent un JWT. Les routes de liste acceptent les paramètres de la route publique qu'elles accompagnent.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/collection` | Cartes possédées d'une recherche : une entrée par carte, avec ses exemplaires par langue |
| GET | `/api/collection/missing` | Cartes d'une recherche que l'utilisateur ne possède pas, dans la forme de `/api/cards` |
| GET | `/api/collection/completion` | Taux d'une recherche : `total`, `owned`, et les exemplaires des cartes de la page (`ownedOnPage`) |
| GET | `/api/collection/identities` | Pour une page de la vue regroupée : cartes possédées par identité, et nombre d'identités commencées |
| GET | `/api/collection/cards/{id}` | Exemplaires possédés d'une carte, un par langue |
| PUT | `/api/collection/cards/{id}/{language}` | Ajoute la carte dans une langue, ou remplace quantité et état |
| DELETE | `/api/collection/cards/{id}/{language}` | Retire la carte dans cette langue |

Côté catalogue, deux routes publiques servent la vue regroupée : `GET /api/identities` (liste paginée, avec le nombre de cartes de chaque identité) et `GET /api/identities/{id}`. Les cartes d'une identité sont une recherche comme une autre : `GET /api/cards?identity=<id>`, ou `identity=none` pour les cartes qui n'en ont pas.

## Décisions de conception

### Le propriétaire vient du jeton, jamais de la requête

Aucune route n'accepte d'identifiant d'utilisateur, ni dans l'URL ni dans le corps. Le contrôleur reçoit l'utilisateur authentifié et le passe au dépôt, dont toutes les méthodes de lecture exigent un propriétaire en argument. Lire ou modifier la collection d'un autre en changeant un identifiant dans une requête (faille dite IDOR) est donc impossible par construction, et pas seulement interdit par une vérification qu'on pourrait oublier.

### Le catalogue reste public, le personnel passe à côté

`GET /api/cards` et `GET /api/identities` renvoient la même chose à tout le monde, connecté ou non. Ce qui dépend de l'utilisateur est demandé par une seconde requête, lancée en parallèle par le frontend.

**Pourquoi ne pas ajouter un champ « possédée » à chaque carte du catalogue :** la réponse dépendrait alors de qui la demande. Le catalogue ne pourrait plus être mis en cache simplement, et une route publique devrait gérer un jeton optionnel. En séparant, la grosse réponse (les cartes) reste partageable, et seule une petite réponse (quelques compteurs) est personnelle.

### `PUT` sur la clé naturelle

Une entrée de collection est identifiée par `(utilisateur, carte, langue)`, une clé que la base garantit unique. L'ajout et la mise à jour passent par un `PUT` sur cette clé plutôt que par un `POST` qui créerait une ressource :

- envoyer deux fois la même requête laisse la collection dans le même état (idempotence) : un double clic ou une requête rejouée ne crée pas de doublon ;
- le frontend n'a pas à connaître l'identifiant technique de l'entrée ;
- le corps est l'état complet : un état de carte absent est remis à « non précisé ».

Si deux requêtes créent la même entrée au même instant, l'index unique en rejette une et l'API répond `409`.

### Une seule définition des filtres

Les filtres de recherche (`q`, `game`, `set`, `rarity`, `identity`) sont appliqués à un seul endroit, `CardRepository::createSearchQueryBuilder()`. Le catalogue, les cartes possédées, les cartes manquantes et le taux de complétion partent tous de cette requête. Deux conséquences :

- un filtre signifie la même chose partout : le taux affiché correspond toujours à la liste affichée ;
- un nouveau filtre vaut immédiatement pour tout. Le filtre par identité, ajouté en dernier, a donné « les wyrms qu'il me manque » sans une ligne de code dédiée.

« Possédées » et « manquantes » sont la même recherche, restreinte par un `EXISTS` ou un `NOT EXISTS` sur les exemplaires de l'utilisateur. Un test vérifie qu'elles découpent bien une recherche en deux, sans carte oubliée ni comptée deux fois.

### Compter des cartes, pas des lignes

Une carte possédée en français et en japonais donne deux lignes en base, mais c'est une seule carte pour un collectionneur. La liste des cartes possédées est donc paginée sur les cartes, puis les exemplaires de la page sont lus en une requête et rattachés à leur carte. Paginer directement les lignes aurait faussé les totaux et coupé une carte entre deux pages.

### Aucune jointure qui multiplie les lignes

Une carte peut avoir plusieurs identités. Filtrer par identité avec une jointure ferait sortir cette carte en double, ce qui fausserait la pagination et les comptages. Le filtre est donc un `EXISTS` (`MEMBER OF` en DQL). C'est aussi ce qui permet au paginateur de limiter la requête telle quelle, sans requête intermédiaire.

### Deux requêtes par page, quelle que soit sa taille

Une page de cartes charge chaque carte avec son extension, son jeu et sa rareté dans la même requête ; la seconde requête compte le total. Sans cela, chaque relation était lue carte par carte : 27 requêtes pour une page de 8 cartes venant d'extensions différentes, et un nombre qui grandissait avec la page (problème dit « N+1 »).

Un test compte les requêtes SQL pour deux tailles de page et exige le même nombre. Il vide d'abord la mémoire de Doctrine : les objets que le test vient de créer seraient sinon déjà chargés, et le problème resterait invisible.

### Des taux calculés, jamais stockés

Aucun compteur n'est enregistré : ni le nombre de cartes d'une identité, ni le taux d'une recherche. Tout est compté à la demande, comme la probabilité d'obtention d'une carte. Une valeur stockée se désynchroniserait à chaque import ou à chaque carte ajoutée à une collection.

### Partir de ce que l'utilisateur possède

Le nombre d'identités commencées se demandait d'abord identité par identité : « l'utilisateur possède-t-il une carte de celle-ci ? ». Avec 1 000 identités et 8 000 cartes possédées, la base reparcourait la collection pour chacune : 1,3 seconde.

La requête liste maintenant une fois les identités des cartes possédées, puis y confronte la recherche : 15 ms pour le même résultat. Le défaut était invisible sur le jeu de démonstration (6 identités) ; c'est la mesure sur des données réelles qui l'a montré.

### Deux niveaux de progression en vue regroupée

La vue regroupée montre deux choses distinctes :

- sur chaque entrée, combien de ses cartes sont possédées (« 3 / 12 ») ;
- au-dessus de la grille, combien d'entrées sont **commencées**, c'est-à-dire possédées par au moins une carte. C'est ce que l'on entend par compléter l'index d'un jeu.

Les cartes sans identité restent accessibles par une dernière entrée, « Autres cartes », qui ne compte pas dans cette progression.

### Une identité a le visage de sa première carte

Quand le catalogue connaît des images de cartes, une entrée de la vue regroupée affiche celle de la **première carte** de l'identité : la plus ancienne par date de sortie, parmi celles qui ont une image. Rien n'est stocké ni choisi à la main : l'image est retrouvée à la demande, en une requête pour toute la page, comme les compteurs. Une identité dont aucune carte n'a d'image garde le visuel généré.

C'est la seule requête du projet écrite en SQL : « la première ligne de chaque groupe » (`DISTINCT ON`) n'existe pas dans le langage de requête de Doctrine.

## Côté frontend

### L'URL porte tout l'état de la recherche

Les filtres, la page, le filtre de possession (`ownership`), l'identité (`identity`) et la vue (`view`) vivent dans l'URL. Une recherche se met en favori ou se partage, et le bouton retour du navigateur la restaure. Les valeurs qu'un visiteur ne peut pas utiliser sont ignorées pour lui, et une valeur que l'API refuserait est écartée plutôt qu'envoyée.

### Une seule liste demandée à la fois

Selon la vue et le filtre de possession, le catalogue interroge une seule route : `/api/cards`, `/api/collection`, `/api/collection/missing` ou `/api/identities` (`useCatalogueSearch.ts`). Au rechargement d'une page filtrée sur « Manquantes », l'écran attend de savoir qui est connecté avant de charger, pour ne pas afficher tout le catalogue pendant un instant.

### Ce qui est personnel est un supplément

Si la requête du taux échoue, le catalogue s'affiche sans barre de progression et sans message d'erreur : l'utilisateur garde l'essentiel.

### Une modification à la fois

Les commandes du bloc « Ma collection » d'une fiche carte se désactivent pendant chaque envoi. Deux clics rapides sur « + » ne peuvent donc pas arriver au serveur dans le désordre. L'écran se met à jour avec la réponse du serveur, puis les listes et les taux concernés sont rechargés.

### Le cache ne change pas de main

Les données de collection gardées en mémoire par le navigateur sont effacées à la connexion et à la déconnexion. Un second compte ouvert dans le même onglet ne voit jamais celles du précédent.

### Accessibilité

- La barre de progression expose sa valeur aux lecteurs d'écran ; le détail affiché au survol est aussi atteignable au clavier et au toucher.
- Les changements de quantité sont annoncés, et le focus est déplacé quand la ligne qui le portait disparaît.
- Le glissement de la carte le long de la barre est supprimé si le système demande moins d'animations.

## Limites connues

- **L'état est partagé par langue.** Deux exemplaires français d'une même carte ne peuvent pas avoir deux états différents.
- **Les langues proposées sont une liste fixe du frontend.** L'API accepte tout code de langue à deux lettres.
- **Performances mesurées sur un seul jeu.** Avec le catalogue français complet de TCGdex (22 146 cartes) et une collection simulée de 8 000 cartes, en mode développement : une page du catalogue ou des cartes manquantes répond en moins de 100 ms, le taux de complétion en 15 ms. La recherche par nom (`LIKE '%…%'`) parcourt toute la table en 4 ms : aucun index n'est justifié à ce volume. Rien n'est mesuré au-delà, ni avec plusieurs jeux de cette taille.
- **Le nom des identités importées est déduit.** Voir [`import.md`](./import.md) : quelques espèces gardent un nom imparfait.
- **Les favoris ne sont pas exposés.** La table existe, sans route ni écran.
- **Pas de mise à jour optimiste.** L'écran attend la réponse du serveur avant de changer, ce qui est plus simple et plus sûr, au prix d'un léger délai.

## Où regarder dans le code

| Sujet | Fichier |
|---|---|
| Routes de la collection | `backend/src/Controller/Api/CollectionController.php` |
| Routes de la vue regroupée | `backend/src/Controller/Api/CardIdentityController.php` |
| Filtres de recherche et pagination | `backend/src/Repository/CardRepository.php` |
| Possédées, manquantes, taux | `backend/src/Repository/OwnedCardRepository.php` |
| Ajout, mise à jour, retrait | `backend/src/Service/CollectionService.php` |
| Tests de l'API | `backend/tests/Controller/CollectionControllerTest.php`, `CardIdentityControllerTest.php` |
| Bloc « Ma collection » d'une fiche | `frontend/src/features/collection/OwnedCardPanel.tsx` |
| Barre de progression | `frontend/src/features/collection/CompletionRate.tsx` |
| Choix de la liste à charger | `frontend/src/features/cards/useCatalogueSearch.ts` |
| État de la recherche dans l'URL | `frontend/src/features/cards/useCardSearchParams.ts` |
| Vue regroupée | `frontend/src/features/identities/` |
