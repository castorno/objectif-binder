# Mise en ligne

Feuille de route pour héberger l'application sur un serveur (VPS), pour un usage personnel avec, plus tard, une démonstration publique. Rien de ce qui suit n'est encore fait ; les étapes sont classées par ordre de réalisation. Les points de sécurité déjà identifiés côté authentification sont dans « Avant une mise en production » de [`authentication.md`](./authentication.md) et repris ici.

## Principe

- **L'instance personnelle est fermée** : pas d'inscription publique, et rien de lisible sans être connecté. Aujourd'hui, l'inscription est ouverte et le catalogue comme les images (`/api/card-images`…) sont publics (`access_control` dans `backend/config/packages/security.yaml`). En ligne, cela permettrait à n'importe qui de créer un compte, et, si `CARD_IMAGE_CACHE` ou `CARD_PRICES` sont activés, de récupérer par le serveur des images et des données de tiers : ce serait les rediffuser publiquement.
- **Une démonstration publique, si besoin, est une autre instance** (sous-domaine `demo.`, base séparée), avec le jeu fictif de `app:demo:seed` et les options de tiers désactivées.

## 1. Fermer l'instance

- Désactiver l'inscription par une variable (`REGISTRATION_ENABLED`, désactivée par défaut) et créer les comptes en console (`app:user:create`, à écrire, sur le modèle de `app:user:promote`). Tests : inscription refusée quand désactivée, commande.
- En production, exiger un utilisateur connecté pour tout `/api`, sauf l'authentification et `/api/health`.

## 2. Construire le frontend pour la production

`frontend/Dockerfile` n'a qu'une étape `dev` (serveur Vite). Ajouter une étape qui lance `vite build`, et faire servir `dist/` par le Caddy de FrankenPHP : `/api` vers PHP, le reste en fichiers statiques avec repli sur `index.html` pour les routes React. Le navigateur ne voit qu'une seule origine, comme en développement. À proposer en détail avant de l'implémenter (image unique ou conteneur séparé).

## 3. Configuration de production

- `POSTGRES_PASSWORD` : le rendre obligatoire (`:?`) dans `compose.prod.yaml`, comme `APP_SECRET` et `JWT_PASSPHRASE` ; il vaut encore `!ChangeMe!` par défaut.
- Clés `config/jwt/*.pem` : générées une fois sur le serveur, gardées dans un volume (elles sont déjà exclues de Git et de l'image). Les régénérer à chaque déploiement invaliderait toutes les sessions.
- Valeurs dans un `.env` présent seulement sur le serveur, jamais commité.
- Lancer avec `compose.yaml` + `compose.prod.yaml`, **jamais** `compose.override.yaml` : il publie le port de Postgres.
- Retirer `CORS_ALLOW_ORIGIN` (le frontend est sur la même origine).
- Vérifier l'en-tête `Origin` sur `refresh` et `logout`.
- `trusted_proxies` : inutile tant que Caddy reçoit directement les requêtes ; nécessaire si un proxy (Cloudflare…) est placé devant, sinon toutes les requêtes partagent les mêmes limites de débit.
- Limiter la taille des journaux Docker (`logging.options.max-size`).

## 4. Domaine et HTTPS

- DNS : enregistrements A (et AAAA) vers le serveur.
- `SERVER_NAME=<domaine>`, `HTTP_PORT=80`, `HTTPS_PORT=443` : Caddy obtient et renouvelle seul le certificat Let's Encrypt. HTTPS est obligatoire, le cookie de rafraîchissement étant `Secure`.
- En-têtes dans le Caddyfile : `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Content-Security-Policy` (le script de thème en ligne de `frontend/index.html` demandera un hash ou d'être déplacé dans un fichier), `X-Robots-Tag: noindex`.
- `robots.txt` (`User-agent: *` / `Disallow: /`) : une politesse pour les moteurs de recherche, pas une protection. Les robots malveillants l'ignorent, et le fichier est public : n'y lister aucun chemin sensible.

## 5. Serveur

- SSH par clé uniquement, connexion root et mot de passe désactivés.
- Pare-feu : 22, 80, 443 (et 443/udp pour HTTP/3). Attention : Docker écrit ses propres règles iptables, un port publié par `ports:` contourne `ufw`. D'où l'importance de ne publier que Caddy.
- Mises à jour de sécurité automatiques (`unattended-upgrades`).

## 6. Sauvegardes et tâches planifiées

- `pg_dump` planifié, copié hors du serveur ; plus les volumes `set_images` et `custom_card_images` (les autres se reconstruisent). Tester une restauration au moins une fois.
- Planifier `gesdinet:jwt:clear` (jetons de rafraîchissement expirés).

## 7. Procédure de déploiement

À écrire ici une fois rodée : récupérer le code, construire les images, `doctrine:migrations:migrate`, redémarrer, vérifier `/api/health`.

## Plus tard

### Ouvrir à quelques personnes : invitations plutôt que validation

Une inscription soumise à validation demande un état sur l'utilisateur, un écran d'administration, l'envoi d'e-mails (`symfony/mailer` et un SMTP, absents aujourd'hui), et laisse une porte publique que des robots rempliraient, avec des e-mails d'inconnus à gérer (RGPD). Une invitation (lien à usage unique, avec expiration, transmis à la main) n'ouvre la porte qu'à quelqu'un de connu : une table d'invitations et une vérification à l'inscription. Modification du modèle de données : à proposer avant de l'implémenter.

### Démonstration pour les recruteurs

Sur une démo publique, on suppose les visiteurs hostiles : les identifiants seront publics.

1. **D'abord** : captures d'écran et courte vidéo dans le README. Peu d'effort, aucun risque.
2. **Ensuite, si utile** : instance `demo.` séparée, compte de démonstration remis à zéro chaque nuit (`app:demo:reset` planifié, à partir de `app:demo:seed`), sans possibilité de changer son mot de passe ni de supprimer le compte, prix et images de tiers désactivés. Un compte en lecture seule ne montrerait pas la gestion de collection, cœur de l'application.

### Autres

- Surveillance de disponibilité sur `/api/health` (service externe, au choix).
- Obligations légales (mentions légales LCEN, RGPD) : non vérifiées. Faibles pour un site fermé et personnel ; une page de confidentialité deviendrait nécessaire si d'autres personnes peuvent s'inscrire. Pas de bandeau cookies : le seul cookie est strictement nécessaire.
