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

Parmi eux, `language` (code ISO 639-1) ne garde que les cartes imprimées dans cette langue, d'après `Card.languages`, et celles dont les langues sont inconnues. C'est un filtre au choix, pas un réglage : sans lui, la complétion compte toutes les cartes du catalogue, y compris celles qui n'existent pas en français. Avec `language=fr`, une carte sortie seulement en anglais n'est plus comptée comme manquante. Le filtre porte sur la liste des cartes, la collection, les manquantes et la complétion, qui partent de la même requête ; la vue par identité et la page des boosters ne le connaissent pas.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/collection` | Cartes possédées d'une recherche : une entrée par carte, avec ses exemplaires par langue |
| GET | `/api/collection/missing` | Cartes d'une recherche que l'utilisateur ne possède pas, dans la forme de `/api/cards` |
| GET | `/api/collection/completion` | Taux d'une recherche : `total`, `owned`, et les exemplaires des cartes de la page (`ownedOnPage`) |
| GET | `/api/collection/identities` | Pour une page de la vue regroupée : cartes possédées par identité, image d'une carte possédée de chacune (`ownedImageByIdentity`, la première dans l'ordre du catalogue), et nombre d'identités commencées |
| GET | `/api/collection/identities/missing` | Identités d'une recherche dont l'utilisateur n'a aucune carte en propre, avec l'image propre à chacune : ce dont on imprime une carte repère. Mêmes paramètres et mêmes entrées que `GET /api/identities`, sauf l'image |
| GET | `/api/collection/cards/{id}` | Exemplaires possédés d'une carte, une entrée par langue et finition |
| PUT | `/api/collection/cards/{id}/{language}/{finish}` | Ajoute la carte dans une langue et une finition, ou remplace quantité et état |
| DELETE | `/api/collection/cards/{id}/{language}/{finish}` | Retire la carte dans cette langue et cette finition |
| GET | `/api/collection/boosters` | Ce qu'un booster de chaque extension apporte en moyenne, le plus intéressant d'abord |

Côté catalogue, deux routes publiques servent la vue regroupée : `GET /api/identities` (liste paginée, avec le nombre de cartes de chaque identité) et `GET /api/identities/{id}`. Les cartes d'une identité sont une recherche comme une autre : `GET /api/cards?identity=<id>`, ou `identity=none` pour les cartes qui n'en ont pas.

## Décisions de conception

### Le propriétaire vient du jeton, jamais de la requête

Aucune route n'accepte d'identifiant d'utilisateur, ni dans l'URL ni dans le corps. Le contrôleur reçoit l'utilisateur authentifié et le passe au dépôt, dont toutes les méthodes de lecture exigent un propriétaire en argument. Lire ou modifier la collection d'un autre en changeant un identifiant dans une requête (faille dite IDOR) est donc impossible par construction, et pas seulement interdit par une vérification qu'on pourrait oublier.

### Le catalogue reste public, le personnel passe à côté

`GET /api/cards` et `GET /api/identities` renvoient la même chose à tout le monde, connecté ou non. Ce qui dépend de l'utilisateur est demandé par une seconde requête, lancée en parallèle par le frontend.

**Pourquoi ne pas ajouter un champ « possédée » à chaque carte du catalogue :** la réponse dépendrait alors de qui la demande. Le catalogue ne pourrait plus être mis en cache simplement, et une route publique devrait gérer un jeton optionnel. En séparant, la grosse réponse (les cartes) reste partageable, et seule une petite réponse (quelques compteurs) est personnelle.

### `PUT` sur la clé naturelle

Une entrée de collection est identifiée par `(utilisateur, carte, langue, finition)`, une clé que la base garantit unique. L'ajout et la mise à jour passent par un `PUT` sur cette clé plutôt que par un `POST` qui créerait une ressource :

- envoyer deux fois la même requête laisse la collection dans le même état (idempotence) : un double clic ou une requête rejouée ne crée pas de doublon ;
- le frontend n'a pas à connaître l'identifiant technique de l'entrée ;
- le corps est l'état complet : un état de carte absent est remis à « non précisé ».

### Un état par défaut, choisi par l'utilisateur

Qui range ses cartes sous pochette dès l'ouverture les ajoute presque toutes dans le même état. Le compte garde cet état (`User.defaultCondition`, modifiable sur la page « Mon compte » par `PUT /api/me/preferences`), et une carte ajoutée le reçoit.

**C'est l'interface qui l'applique, pas l'API.** Le bouton d'ajout envoie l'état préféré dans le `PUT`, comme si l'utilisateur l'avait choisi. Si le serveur le mettait lui-même quand l'état est absent, « absent » ne voudrait plus dire « non précisé » : on ne pourrait plus retirer l'état d'une carte, et le `PUT` ne décrirait plus tout ce qu'il écrit. La préférence est une valeur de départ, pas une règle cachée. Les cartes déjà en collection ne changent pas quand elle change.

Si deux requêtes créent la même entrée au même instant, l'index unique en rejette une et l'API répond `409`.

### Une seule définition des filtres

Les filtres de recherche (`q`, `game`, `set`, `rarity`, `identity`) sont appliqués à un seul endroit, `CardRepository::createSearchQueryBuilder()`. Le catalogue, les cartes possédées, les cartes manquantes et le taux de complétion partent tous de cette requête. Deux conséquences :

- un filtre signifie la même chose partout : le taux affiché correspond toujours à la liste affichée ;
- un nouveau filtre vaut immédiatement pour tout. Le filtre par identité, ajouté en dernier, a donné « les wyrms qu'il me manque » sans une ligne de code dédiée.

« Possédées » et « manquantes » sont la même recherche, restreinte par un `EXISTS` ou un `NOT EXISTS` sur les exemplaires de l'utilisateur. Un test vérifie qu'elles découpent bien une recherche en deux, sans carte oubliée ni comptée deux fois.

### Le catalogue se feuillette dans l'ordre de sortie

Les cartes sont triées par date de sortie de leur extension, de la plus ancienne à la plus récente, puis par numéro dans l'extension. Une extension dont la date n'est pas connue vient en dernier. Le code d'une extension ne sert qu'à départager deux extensions sorties le même jour : c'est un identifiant, pas un ordre.

Cet ordre vaut pour le catalogue, les cartes possédées et les cartes manquantes, qui partagent la même requête.

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

### Les identités se filtrent par groupe

Un jeu peut ranger ses identités en groupes : les générations de créatures, par exemple. Le groupe est une donnée de l'import (un nom et un rang sur chaque identité), et le jeu dit comment il les appelle (`Game.identityGroupLabel`). La vue regroupée propose alors un filtre sous ce nom.

Le groupe est un filtre de la recherche d'identités, appliqué au même endroit que les autres (`CardIdentityRepository::createSearchQueryBuilder()`) : la liste, les totaux et la progression de l'utilisateur le suivent sans code dédié. Filtrer sur une génération donne donc la complétion de cette génération.

Il n'y a pas de table des groupes : un groupe existe tant qu'une identité le nomme. Les cartes sans identité n'appartiennent à aucun groupe, et l'entrée « Autres cartes » disparaît quand un groupe est choisi.

### La première carte d'une identité se fête

Quand un utilisateur ajoute une carte qui est sa première d'une identité, la réponse de l'API le dit (`discovery`) : quelles identités, et où il en est parmi celles du jeu. L'écran affiche alors un court message pendant quelques secondes.

C'est le serveur qui décide de ce qui est « premier », une fois la carte enregistrée : l'utilisateur possède alors exactement une carte de l'identité. La même carte dans une seconde langue, un changement de quantité ou une deuxième carte de la même identité n'en sont pas.

Le message passe par un petit système de notification (`components/Notifications.tsx`) : une zone annoncée aux lecteurs d'écran, des messages qui s'effacent seuls et que l'on peut fermer. Rien de ce qu'ils disent n'est nécessaire pour utiliser la page.

### Une identité a le visage de sa première carte

Quand le catalogue connaît des images de cartes, une entrée de la vue regroupée affiche celle de la **première carte** de l'identité : la plus ancienne par date de sortie, parmi celles qui ont une image. Rien n'est stocké ni choisi à la main : l'image est retrouvée à la demande, en une requête pour toute la page, comme les compteurs. Une identité dont aucune carte n'a d'image garde le visuel généré.

C'est la seule requête du projet écrite en SQL : « la première ligne de chaque groupe » (`DISTINCT ON`) n'existe pas dans le langage de requête de Doctrine.

### Quel booster ouvrir

La page « Boosters » classe les extensions dont les taux de drop sont saisis selon ce qu'un booster apporte à l'utilisateur, d'après sa collection. `GET /api/collection/boosters` (connecté ; `?game=` pour un seul jeu) renvoie pour chacune :

- **le nombre moyen de nouvelles identités par booster** (`expectedNewIdentities`), qui sert au classement : des identités dont l'utilisateur ne possède aucune carte, toutes extensions confondues ;
- **le nombre moyen de cartes non possédées par booster** (`expectedMissingCards`) ;
- les identités nouvelles les plus probables, avec leur chance ;
- un indicateur `incomplete` quand des cartes du booster ont une rareté sans taux : les chiffres ne couvrent alors que le reste.

Chaque ligne donne aussi l'image de l'extension et, s'il est saisi, le prix du booster. La page en tire un second chiffre, les nouvelles identités **par euro**, et propose alors de classer « par booster » ou « par euro ». Le tri par euro se fait côté écran, sur une liste courte ; une extension sans prix ne peut pas être classée ainsi et passe à la fin.

Une extension sans aucun taux n'est pas listée ; la réponse dit combien sont dans ce cas. Un booster contient les cartes de l'extension et de ses sous-extensions.

**Pourquoi une moyenne et pas « la probabilité d'obtenir au moins une nouveauté ».** C'était l'idée de départ. Calculée sur un vrai cas (33 identités possédées sur 1 031), elle vaut 100 % pour tous les boosters : elle ne les départage pas tant qu'une collection est peu avancée. La moyenne, elle, sépare un booster plein de la même espèce d'un booster varié.

**Le calcul**, isolé dans `BoosterOddsCalculator` (de l'arithmétique pure, sans base de données) :

- un taux « X cartes pour Y boosters » donne X ÷ Y cartes de la rareté par booster, réparties également entre les cartes de la rareté ;
- quand un booster en contient plusieurs (3 communes), elles sont tirées sans doublon : la chance d'en obtenir au moins une parmi celles qu'on vise suit la loi hypergéométrique ; un taux non entier (7 pour 2) est un mélange de 3 et de 4 cartes ;
- une identité imprimée dans plusieurs raretés peut venir de chacune : ses chances se combinent ;
- le nombre moyen de nouvelles identités est la somme des chances de chacune. Cette somme reste juste même si les tirages dépendent les uns des autres.

Exemple, sur « 30ᵉ Anniversaire » avec ses taux saisis en octobre 2026 : Pikachu 100 % (30 cartes garanties), Miaouss 15,4 % (3 communes et 3 illustrations rares), Dracaufeu 0,32 % (une seule carte classique, 1 booster sur 315).

**Hypothèses et limites :**

- les cartes d'une rareté sortent aussi souvent les unes que les autres ;
- un booster ne contient jamais deux fois la même carte ;
- ce qu'un booster donne d'une rareté ne dit rien de ce qu'il donne d'une autre. C'est une approximation : dans un vrai booster, plusieurs raretés se disputent le même emplacement. Les moyennes n'en souffrent pas, les chances par identité légèrement ;
- les finitions ne sont pas comptées (la carte reverse d'un booster est ignorée), ni les cartes sans rareté ;
- les taux sont des estimations saisies à la main : le résultat ne vaut pas mieux qu'eux.

Quatre requêtes SQL quel que soit le nombre d'extensions (`BoosterContentRepository`).

### Des cartes repères à imprimer

Un classeur rangé par identité a une pochette par identité, vide tant qu'aucune carte ne l'occupe. La page « Cartes repères » imprime de quoi marquer ces pochettes : une carte au format standard (63 × 88 mm) par identité manquante, neuf par feuille A4, avec son numéro, son nom et, en option, son image.

**Manquante n'est pas le contraire de commencée.** Une identité est commencée dès qu'une carte possédée la montre, même parmi d'autres. Ici la règle est plus stricte : seule une carte qui ne montre qu'elle remplit sa pochette, car une carte à deux identités ne peut pas être rangée à deux endroits. Les deux règles coexistent, chacune à son usage (`searchIdentitiesMissingByUser` et `countIdentitiesStartedByUser`).

**Pourquoi le navigateur imprime, et pas un PDF généré :** la page est mise en forme en millimètres avec une feuille de style d'impression, et « Enregistrer en PDF » fait le reste. Aucune dépendance, et l'aperçu à l'écran est la feuille elle-même. Un PDF généré ne garantirait pas mieux la taille : ce qui la fausse est l'option « ajuster à la page » de l'impression, qui redimensionne un PDF tout autant. La page le rappelle, et donne la largeur à vérifier.

**L'image est celle de l'identité, pas d'une de ses cartes.** Une carte repère montre ce qui manque, pas une carte qu'on n'a pas : `CardIdentity.imageUrl` garde l'adresse d'une image de l'identité elle-même, servie par un tiers et fournie par l'import quand on lui demande les images (voir [import.md](import.md), « Les images »). Sans elle, la carte repère porte le numéro et le nom. La vue regroupée, elle, garde le visage de la première carte : c'est un catalogue de cartes.

L'API reste paginée (100 au plus) ; la page lit toutes les pages d'une sélection avant d'afficher, puisqu'on imprime tout d'un coup.

## Côté frontend

### L'URL porte tout l'état de la recherche

Les filtres, la page, le filtre de possession (`ownership`), l'identité (`identity`) et la vue (`view`) vivent dans l'URL. Une recherche se met en favori ou se partage, et le bouton retour du navigateur la restaure. Les valeurs qu'un visiteur ne peut pas utiliser sont ignorées pour lui, et une valeur que l'API refuserait est écartée plutôt qu'envoyée.

### Une extension se trouve en tapant

Un jeu réel compte près de deux cents extensions : une liste déroulante ne s'y prête plus. Le filtre « Extension » est un champ de saisie qui réduit la liste à mesure que l'on tape (`ComboboxField`). La recherche porte sur le nom et le code, n'importe où dans le texte, sans tenir compte des accents ni des majuscules, et exige tous les mots saisis : « dragon » trouve « EX Dragon », « ecarlate » trouve « Écarlate et Violet ». Chaque ligne indique la date de sortie.

Le filtrage se fait dans le navigateur, sur la liste déjà chargée : aucune requête par frappe.

Le composant suit le modèle « combobox » des recommandations WAI-ARIA : le focus reste dans le champ, les flèches déplacent une surbrillance annoncée aux lecteurs d'écran, Entrée choisit, Échap referme. Rien n'est choisi sans geste explicite : taper puis quitter le champ ne change pas le filtre. Il est testé au clavier et à la souris, pas avec un lecteur d'écran réel.

Une extension dont aucune carte n'a d'image porte une carte barrée dans la liste : ses cartes n'afficheront que des visuels générés. L'API le dit pour chaque extension (`hasPictures`), en une requête pour toute la liste. Le signe n'apparaît que si le jeu a des images par ailleurs ; sinon il serait sur toutes les lignes et ne dirait rien. Il est doublé d'un texte pour les lecteurs d'écran.

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
- **Performances mesurées sur un seul jeu.** Avec le catalogue français complet de TCGdex (22 146 cartes) et une collection simulée de 8 000 cartes, en mode développement : une page du catalogue ou des cartes manquantes répond en moins de 100 ms, le taux de complétion en 15 ms. La recherche par nom (`LIKE '%…%'`) parcourt toute la table en 4 ms : aucun index n'est justifié à ce volume. Rien n'est mesuré au-delà, ni avec plusieurs jeux de cette taille. Une seconde mesure, le 10 octobre 2026, a ramené une page du catalogue de 57 à 33 ms et écarté l'index de recherche par nom envisagé : voir [`performance.md`](./performance.md).
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
