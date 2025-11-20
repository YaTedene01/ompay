# OMPAY - Application de Paiement Mobile

OMPAY est une application de paiement mobile développée avec Laravel, offrant l'authentification par SMS, les transferts d'argent et les paiements QR.

## Fonctionnalités

- Authentification sécurisée par SMS avec tokens temporaires
- Gestion des comptes utilisateurs avec soldes
- Transferts d'argent entre utilisateurs
- Paiements via codes QR marchands
- Historique des transactions avec pagination
- API RESTful documentée avec Swagger

## Technologies Utilisées

- **Laravel** : Framework PHP pour le backend
- **Laravel Passport** : Authentification OAuth2
- **PGSQL** : Base de données
- **Swagger/OpenAPI** : Documentation API
- **Architecture** : Repository/Service avec injection de dépendance

## API OMPAY - Documentation des Endpoints

OMPAY est une application de paiement mobile développée avec Laravel, utilisant l'authentification par SMS et les tokens Passport.

### Authentification

#### POST /api/auth/envoyer-lien
- **Corps** : `{ "phone": "string" }`
- **Description** : Génère un token temporaire et retourne un lien de vérification. L'envoi SMS est désactivé pour les tests.
- **Réponse** : `{ "status": true, "data": { "token": "...", "expires_in": 600, "message": "..." } }`

#### POST /api/auth/echange
- **Corps** : `{ "temp_token": "string" }`
- **Description** : Échange le token temporaire contre un token d'accès Passport. Crée l'utilisateur si nécessaire.
- **Réponse** : `{ "status": true, "data": { "access_token": "...", "token_type": "Bearer" } }`

#### POST /api/auth/logout (auth:api requis)
- **Description** : Révoque le token d'accès actuel pour déconnecter l'utilisateur.
- **Réponse** : `{ "status": true, "message": "Déconnexion réussie" }`

### Comptes (auth:api requis)

#### GET /api/compte/dashboard
- **Description** : Retourne le tableau de bord complet de l'utilisateur (compte, QR code, transactions récentes).
- **Réponse** : Données utilisateur, compte, QR code et dernières transactions.

#### GET /api/compte/solde
- **Description** : Consulte le solde du compte.
- **Réponse** : `{ "compte_id": "...", "solde": 150.50, "devise": "XOF" }`

#### POST /api/compte/transfert
- **Corps** : `{ "montant": 25.00, "to_phone": "771234567" }` ou `{ "to_compte_id": "uuid" }`
- **Description** : Effectue un transfert d'argent vers un autre utilisateur.
- **Réponse** : Détails du transfert effectué.

#### POST /api/compte/paiement
- **Corps** : `{ "code_marchand": "ABC123", "montant": 50.00 }`
- **Description** : Effectue un paiement via un code QR marchand.
- **Réponse** : Détails du paiement.

#### GET /api/compte/transactions
- **Paramètres** : `?per_page=15&type=transfert`
- **Description** : Liste les transactions du compte avec pagination.
- **Réponse** : Liste paginée des transactions.

### Notes Techniques

- **Authentification** : Utilise Laravel Passport avec le driver `auth:api`.
- **Jobs** : L'envoi de SMS est géré par `SendAuthLinkJob` (actuellement désactivé pour les tests).
- **Architecture** : Suit le pattern Repository/Service avec injection de dépendance.
- **Ressources API** : Utilise Laravel API Resources pour formater les réponses.
- **Production** : Configurez un fournisseur SMS réel (Twilio) et une file d'attente (Redis/Queue).

## Installation et Configuration

1. **Cloner le repository**
   ```bash
   git clone <repository-url>
   cd ompay
   ```

2. **Installer les dépendances**
   ```bash
   composer install
   npm install
   ```

3. **Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan passport:install
   ```

4. **Base de données**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Démarrer le serveur**
   ```bash
   php artisan serve
   ```

## Déploiement

Consultez `README_DEPLOY.md` pour les instructions de déploiement en production.

## Licence

Ce projet est sous licence MIT.

YA TEDENE FAYE
