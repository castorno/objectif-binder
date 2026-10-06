# Authentification

Ce document décrit comment un utilisateur s'inscrit, se connecte et reste connecté, et les choix de sécurité associés. Il couvre l'API, puis la façon dont le frontend s'en sert.

## Vue d'ensemble

L'API utilise deux jetons aux rôles distincts :

| | Jeton d'accès (JWT) | Jeton de rafraîchissement |
|---|---|---|
| Rôle | Prouve l'identité à chaque requête | Permet d'obtenir un nouveau jeton d'accès |
| Durée de vie | 15 minutes | 14 jours après sa dernière utilisation |
| Transport | En-tête `Authorization: Bearer …` | Cookie `HttpOnly`, posé et lu par le serveur |
| Stockage côté client | En mémoire JavaScript uniquement | Aucun accès possible depuis JavaScript |
| Stockage côté serveur | Aucun (vérifié par sa signature) | Table `refresh_token`, sous forme d'empreinte |
| Révocable | Non, d'où sa courte durée | Oui : usage unique, supprimé à la déconnexion |

```
Navigateur                                API
    │  POST /api/auth/login {email, password}  │
    │ ───────────────────────────────────────► │
    │  200 {token}  +  Set-Cookie: refresh_token (HttpOnly)
    │ ◄─────────────────────────────────────── │
    │                                          │
    │  GET /api/me   Authorization: Bearer <token>
    │ ───────────────────────────────────────► │
    │  200 {id, email}                         │
    │ ◄─────────────────────────────────────── │
    │                                          │
    │  … 15 minutes plus tard, le jeton d'accès a expiré …
    │                                          │
    │  POST /api/auth/refresh   (le cookie part tout seul)
    │ ───────────────────────────────────────► │
    │  200 {token}  +  Set-Cookie: nouveau refresh_token
    │ ◄─────────────────────────────────────── │
```

## Points d'entrée

| Méthode | Route | Accès | Réponse |
|---|---|---|---|
| POST | `/api/auth/register` | public | `201 {id, email}` — ne connecte pas |
| POST | `/api/auth/login` | public | `200 {token}` et cookie de rafraîchissement |
| POST | `/api/auth/refresh` | public (cookie) | `200 {token}` et nouveau cookie |
| POST | `/api/auth/logout` | public (cookie) | `204`, session supprimée et cookie effacé |
| GET | `/api/me` | connecté | `200 {id, email}` |

Erreurs, toujours au format `{"error": "…"}` :

| Code | Cas |
|---|---|
| 400 | JSON mal formé, ou identifiants incomplets à la connexion |
| 401 | Identifiants faux, jeton d'accès absent, invalide ou expiré, jeton de rafraîchissement refusé |
| 409 | E-mail déjà inscrit |
| 415 | Inscription envoyée autrement qu'en JSON |
| 422 | Inscription invalide ; le détail par champ est dans `violations` |
| 429 | Trop de tentatives (voir « Limitation de débit ») |

Les messages sont volontairement génériques : un mot de passe faux et un e-mail inconnu donnent exactement la même réponse, et un jeton de rafraîchissement absent, inconnu, expiré ou déjà utilisé aussi.

Une requête qui porte un jeton d'accès invalide ou expiré est refusée (401) avant tout traitement, y compris sur une route publique. Le client doit donc appeler `refresh` et `logout` sans en-tête `Authorization`.

## Décisions de conception

### Où vit le jeton côté navigateur

Trois options ont été comparées :

- **JWT dans `localStorage`** : simple, mais une seule faille XSS suffit à voler le jeton et à l'utiliser depuis une autre machine.
- **JWT dans un cookie `HttpOnly`** : illisible par JavaScript, mais le navigateur l'envoie tout seul, ce qui expose chaque requête d'écriture au CSRF, et rien ne permet de déconnecter réellement un utilisateur.
- **JWT court en mémoire + jeton de rafraîchissement en cookie `HttpOnly`** : l'option retenue.

Ce que l'option retenue apporte :

- **Face au XSS** : un script injecté ne peut lire que le JWT de 15 minutes. Le jeton longue durée lui est inaccessible.
- **Face au CSRF** : l'API n'authentifie que par l'en-tête `Authorization`, qu'un site tiers ne peut pas faire envoyer par le navigateur. Seuls `refresh` et `logout` lisent le cookie, et celui-ci est `SameSite=Strict` et limité au chemin `/api/auth`.
- **Révocation** : la déconnexion supprime la session en base, ce qu'un JWT seul ne permet pas.

### API fermée par défaut

Toute route `/api` exige une connexion, sauf celles listées explicitement dans `access_control` (`config/packages/security.yaml`) : l'authentification, le healthcheck et la lecture du catalogue. Une route ajoutée plus tard et oubliée dans cette liste sera donc fermée, pas ouverte.

Le pare-feu est sans session (`stateless`) : l'utilisateur est rechargé depuis la base à chaque requête, si bien qu'un compte supprimé perd l'accès immédiatement, même avec un JWT encore valide.

### Jetons de rafraîchissement

- **Usage unique** : chaque rafraîchissement remplace le jeton. Une copie volée cesse de fonctionner dès que le client légitime rafraîchit, et inversement.
- **Stockés hachés** (SHA-256) : une copie de la table ne permet pas de se connecter. Un hachage lent serait inutile ici, le jeton étant une valeur aléatoire de 64 octets, impossible à deviner.
- **Cinq sessions au plus par utilisateur** : une sixième connexion ferme la session inutilisée depuis le plus longtemps.
- **Routes en `POST` uniquement** : un `GET`, qu'un simple lien ou une image peut déclencher, répond 405.

### Signature des JWT

Les JWT sont signés avec une paire de clés RSA (algorithme RS256). Les clés ne sont jamais commitées (`config/jwt/*.pem` est ignoré par Git) : le script de démarrage du conteneur les génère si elles manquent. La phrase secrète qui chiffre la clé privée vient de la variable `JWT_PASSPHRASE`.

Seul l'en-tête `Authorization` est accepté : un jeton passé dans l'URL est ignoré, car les URL se retrouvent dans les journaux et l'historique du navigateur.

### Inscription

- **Objet de requête dédié** (`RegisterRequest`) : il n'a que deux propriétés, `email` et `password`. Un champ `roles` envoyé par un client n'a nulle part où aller.
- **Mot de passe** : 10 à 128 caractères et un contrôle de robustesse, sans règle de composition arbitraire (chiffre, symbole…). Il est haché avant stockage et n'apparaît dans aucune réponse.
- **E-mail** : stocké en minuscules ; la connexion ignore la casse.
- **JSON obligatoire** : un formulaire HTML classique est refusé (415), pour qu'une page tierce ne puisse pas créer de comptes depuis le navigateur d'un visiteur.
- **Pas de connexion automatique** : seule la route de connexion délivre des jetons.

### Limitation de débit

| Point d'entrée | Limite | Compté par |
|---|---|---|
| Connexion | 5 échecs par minute | e-mail + adresse IP (et 25 par adresse, tous e-mails confondus) |
| Inscription | 10 par heure | adresse IP |
| Rafraîchissement | 30 par minute | adresse IP |

La limite de connexion est comptée par couple e-mail + adresse, et non par e-mail seul : sinon, n'importe qui pourrait verrouiller le compte d'un autre en se trompant volontairement.

Le rafraîchissement est traité par le pare-feu, avant tout contrôleur ; sa limite est donc appliquée par `RefreshRateLimitListener` plutôt que par l'attribut `#[RateLimit]` utilisé pour l'inscription.

## Côté frontend

Le navigateur ne parle qu'au serveur du frontend, qui relaie `/api` vers l'API : la page et l'API partagent la même origine. Il n'y a donc pas de CORS, et le cookie de rafraîchissement est un simple cookie de même origine.

### Où vit chaque jeton

- **Jeton d'accès** : dans une variable JavaScript (`src/api/accessToken.ts`), nulle part ailleurs. Il disparaît au rechargement de la page.
- **Jeton de rafraîchissement** : dans le cookie `HttpOnly`, que le code ne voit jamais.

### Restauration de la session

Au démarrage, l'application demande `GET /api/me`. Sans jeton en mémoire, le client appelle d'abord `POST /api/auth/refresh` : si le cookie est valide, la session reprend ; sinon l'utilisateur est un visiteur. Un visiteur déclenche donc un 401 sur `refresh` à chaque chargement de page, ce qui est normal.

### Appels authentifiés

Le client (`src/api/client.ts`) n'envoie le jeton que sur demande, avec l'option `auth`. Le catalogue est toujours appelé sans jeton.

Quand un appel authentifié reçoit 401, le client renouvelle le jeton puis rejoue l'appel, une seule fois. Deux précautions tiennent au fait qu'un jeton de rafraîchissement ne sert qu'une fois :

- les appels simultanés partagent un seul rafraîchissement ;
- entre onglets, qui partagent le cookie, les rafraîchissements se font à tour de rôle grâce à l'API `Web Locks` du navigateur, là où elle existe.

Si le renouvellement échoue, la session est terminée pour toute l'application, à un seul endroit (`src/api/queryClient.ts`).

### Écrans

| Route | Écran |
|---|---|
| `/login` | Connexion |
| `/register` | Inscription, suivie d'une connexion automatique |
| `/account` | Compte de l'utilisateur, réservé aux utilisateurs connectés |

- **Retour après connexion** : la page d'origine est gardée dans l'état du routeur, pas dans l'URL, et seuls les chemins internes sont acceptés. Un lien piégé ne peut donc pas rediriger vers un autre site.
- **Messages d'erreur** : choisis d'après le code de réponse et rédigés en français côté frontend ; le texte de l'API n'est jamais affiché.
- **Routes protégées** : `RequireAuth` renvoie un visiteur vers la connexion puis le ramène. C'est un confort de navigation, pas une protection : les données sont gardées par l'API, qui répond 401 sans jeton valide quoi qu'affiche le navigateur.

## Limites connues

- **L'inscription révèle si un e-mail est déjà utilisé** (réponse 409). Le masquer demanderait une confirmation par e-mail, que le projet n'envoie pas encore. La limitation de débit réduit le risque d'énumération.
- **Pas de vérification d'e-mail ni de réinitialisation de mot de passe**, pour la même raison.
- **Un JWT reste valable jusqu'à son expiration**, même après déconnexion : 15 minutes au plus.
- **Pas de détection de réutilisation** d'un jeton de rafraîchissement. Elle fermerait toute la session au moindre rejeu, y compris quand deux onglets rafraîchissent en même temps.
- **Jetons expirés non purgés automatiquement** : la commande `gesdinet:jwt:clear` les supprime et devra être planifiée.

## Avant une mise en production

- Fournir `APP_SECRET` et `JWT_PASSPHRASE` par de vraies variables d'environnement, et conserver les clés `config/jwt/*.pem` hors de l'image (volume ou gestionnaire de secrets). Générées à chaque déploiement, elles invalideraient tous les jetons en circulation.
- Servir l'API en HTTPS : le cookie de rafraîchissement est `Secure`.
- Placer un serveur frontal qui serve le frontend et relaie `/api` vers l'API, comme le fait le serveur Vite en développement : le navigateur ne doit voir qu'une seule origine.
- Retirer ou restreindre `CORS_ALLOW_ORIGIN`, qui n'autorise aujourd'hui que `localhost` et ne sert plus au frontend.
- Déclarer les proxys de confiance (`framework.trusted_proxies`) : sans cela, derrière un répartiteur de charge, toutes les requêtes semblent venir de la même adresse et partagent les mêmes limites de débit.
- Vérifier l'en-tête `Origin` sur `refresh` et `logout` : `SameSite=Strict` écarte les autres sites, pas un autre sous-domaine du même site.
- Planifier `gesdinet:jwt:clear`.

## Où regarder dans le code

| Sujet | Fichier |
|---|---|
| Pare-feu, règles d'accès, limite de connexion | `backend/config/packages/security.yaml` |
| Durée du JWT, clés | `backend/config/packages/lexik_jwt_authentication.yaml` |
| Jetons de rafraîchissement, cookie | `backend/config/packages/gesdinet_jwt_refresh_token.yaml` |
| Limites d'inscription et de rafraîchissement | `backend/config/packages/rate_limiter.yaml` |
| Routes | `backend/src/Controller/Api/AuthController.php` |
| Inscription | `backend/src/Dto/RegisterRequest.php`, `backend/src/Service/UserRegistrationService.php` |
| Format des erreurs | `backend/src/EventListener/` |
| Tests | `backend/tests/Controller/` (`AuthControllerTest`, `RegistrationTest`, `RefreshTokenTest`, `RateLimitTest`) |
| Client, jeton en mémoire, renouvellement | `frontend/src/api/` (`client.ts`, `accessToken.ts`, `queryClient.ts`) |
| Session, écrans, routes protégées | `frontend/src/features/auth/` |
