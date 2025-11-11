Déploiement — Docker, Railway (DB) et Render

Résumé rapide
- Le projet est dockerisé via `Dockerfile` et un `docker-compose.yml` est fourni pour le développement local.
- Vous pouvez utiliser une base PostgreSQL fournie par Railway (DATABASE_URL) en production.
- Le manifeste `render.yaml` est prêt pour déployer sur Render (Docker). Configurez les variables d'environnement dans le dashboard Render.

Étapes locales (docker-compose)
1. Construire et démarrer les services :
   docker-compose up --build
2. Le conteneur PHP-FPM écoute le port 9000 ; normalement vous voudrez ajouter un reverse proxy (nginx) ou utiliser Sail/Valet.

Railway (base PostgreSQL distante)
1. Créez un projet PostgreSQL sur Railway et copiez l'URL de connexion (DATABASE_URL). Exemple :
   postgres://user:password@host:port/dbname
2. Dans Render -> votre service, ajoutez la variable d'environnement `DATABASE_URL` avec la valeur fournie par Railway.

Render (déploiement Docker)
1. Poussez la branche que vous voulez déployer (par ex. `main`) vers GitHub.
2. Créez un service Web sur Render, choisissez "Docker" et pointez sur votre dépôt.
3. Dans les variables d'environnement de Render, ajoutez au minimum :
   - DATABASE_URL (valeur Railway)
   - APP_KEY (générez localement et mettez ici)
   - APP_ENV=production
   - APP_DEBUG=false
4. Déclenchez un deploy. Render utilisera le `Dockerfile` du repo. Si besoin, définissez `startCommand` (par ex. `php-fpm` ou `vendor/bin/heroku-php-apache2 public/`).

Notes et conseils
- Ne stockez jamais de secrets dans le repo. Utilisez le dashboard Render et Railway pour gérer les variables sécurisées.
- Après déploiement, exécutez les migrations manuellement via `php artisan migrate --force` (Render a une interface de shell ou via CI).
- Si vous voulez que j'automatise la création de PR/merge vers `main` et pousse vers Git remote, dites-moi si je dois exécuter les commandes git ici (j'aurai besoin que votre repo ait un remote configuré et que vous m'autorisiez à pousser).
