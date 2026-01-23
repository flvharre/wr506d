# 🎬 API de Gestion de Films - WR506D 2025-2026

API REST et GraphQL complète pour la gestion de films (type IMDb simplifié) développée avec Symfony 6+ et API Platform.

## 📋 Table des matières

- [Technologies utilisées](#technologies-utilisées)
- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentification](#authentification)
- [Endpoints API](#endpoints-api)
- [Collection Postman](#collection-postman)

---

## 🚀 Technologies utilisées

### Backend
- **Symfony 6+** - Framework PHP
- **API Platform 3** - Framework REST/GraphQL
- **Doctrine ORM** - Gestion base de données
- **MySQL/MariaDB** - Base de données relationnelle
- **JWT Authentication** - LexikJWTAuthenticationBundle
- **VichUploaderBundle** - Gestion des uploads de fichiers
- **Rate Limiter** - Limitation de requêtes par utilisateur
- **OTPHP** - Authentification à deux facteurs (2FA/TOTP)
- **Endroid QR Code** - Génération de QR codes

### Sécurité
- 🔐 Authentification JWT
- 🔑 API Keys personnalisées par utilisateur
- 📱 2FA avec Google Authenticator/Authy
- ⏱️ Rate limiting configurables (20 req/h anonymes, 100 req/h authentifiés)
- 🔒 Hashage des mots de passe avec bcrypt
- 🛡️ CORS configuré

---

## ✨ Fonctionnalités

### 🎬 Gestion des Films
- CRUD complet sur les films
- Upload d'affiches (multipart/form-data)
- Association avec réalisateurs, acteurs, catégories
- Filtrage et recherche avancée
- Pagination automatique (30 items/page)
- Support REST et GraphQL

### 💬 Système de commentaires
- Ajout de commentaires sur les films
- Modération par les administrateurs
- Attribution automatique de l'auteur connecté

### 🔐 Authentification triple
1. **JWT** - Pour applications frontend classiques
2. **API Keys** - Pour intégrations serveur-to-serveur
3. **2FA (TOTP)** - Authentification à deux facteurs via Google Authenticator

### 👥 Gestion des utilisateurs
- Système de rôles hiérarchiques (MEMBER → AUTHOR → EDITOR → ADMIN)
- Rate limiting personnalisé par utilisateur
- Gestion des API Keys personnelles

### 🎭 Gestion des entités
- **Acteurs** : Biographie, photo, filmographie
- **Réalisateurs** : Informations complètes
- **Catégories** : Classification des films
- **Médias** : Gestion centralisée des images

---

## 📦 Prérequis

- **PHP** 8.1 ou supérieur
- **Composer** 2.x
- **MySQL** 8.0+ ou **MariaDB** 10.6+
- **Extensions PHP** : `pdo_mysql`, `gd`, `intl`, `zip`, `curl`, `xml`, `mbstring`
- **OpenSSL** (pour JWT)

---

## 🔧 Installation

### 1. Cloner le projet

```bash
git clone https://github.com/votre-repo/wr506d-api.git
cd wr506d-api
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

Copier et adapter le fichier d'environnement :

```bash
cp .env .env.local
nano .env.local
```

Configuration `.env.local` :

```env
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=GENERER_UNE_CLE_ALEATOIRE_64_CHARS

# Base de données
DATABASE_URL="mysql://user:password@127.0.0.1:3306/wr506d?serverVersion=8.0&charset=utf8mb4"

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=VOTRE_PASSPHRASE_SECURE

# CORS
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

**Générer `APP_SECRET` :**

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### 4. Générer les clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

### 5. Créer la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 6. Préparer le dossier uploads

**⚠️ Important** : Avant de charger les fixtures, supprimer tous les fichiers existants dans le dossier media :

```bash
# Supprimer les anciens fichiers
rm -rf public/uploads/media/*

# Ou sous Windows
del /Q public\uploads\media\*
```

Le dossier `public/uploads/media` existe déjà dans le projet, assurez-vous simplement qu'il a les bonnes permissions :

```bash
chmod 755 public/uploads/media
```

### 7. (Optionnel) Charger des données de test

```bash
php bin/console doctrine:fixtures:load
```

**Note** : Les fixtures créeront automatiquement :
- Des films, acteurs, réalisateurs et catégories
- Des médias associés
- Un utilisateur administrateur avec les identifiants suivants :
  * **Email** : `admin@admin.com`
  * **Username** : `admin`
  * **Mot de passe** : `admin123`

Ces identifiants sont créés automatiquement lors du chargement des fixtures.

### 8. Démarrer le serveur

```bash
symfony server:start
# ou
php -S localhost:8319 -t public
```

**L'API est accessible sur** : `http://localhost:8319`

**Documentation interactive** : `http://localhost:8319/api/docs`

---

## ⚙️ Configuration

### Rate Limiting

Configuration dans `config/packages/rate_limiter.yaml` :

```yaml
framework:
    rate_limiter:
        anonymous_api:
            policy: 'fixed_window'
            limit: 20
            interval: '1 hour'
        authenticated_api:
            policy: 'fixed_window'
            limit: 100
            interval: '1 hour'
```

**Personnalisation par utilisateur :**

Chaque utilisateur possède un attribut `apiRateLimit` modifiable par les administrateurs :

```http
PATCH /api/users/{id}
Authorization: Bearer {admin_token}
Content-Type: application/merge-patch+json

{
  "apiRateLimit": 500
}
```

Les headers de réponse indiquent l'état actuel :

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1765790070
```

---

## 🔐 Authentification

### Méthode 1 : JWT (recommandée)

#### Inscription

```http
POST /api/users
Content-Type: application/ld+json

{
  "email": "user@example.com",
  "username": "johndoe",
  "plainPassword": "Password123!"
}
```

#### Connexion

```http
POST /auth
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "Password123!"
}
```

**Réponse :**

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

#### Utilisation

```http
GET /api/movies
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

### Méthode 2 : API Key

#### Générer une clé

```http
POST /api/me/api-key
Authorization: Bearer {jwt_token}
```

**Réponse (la clé n'est visible qu'une seule fois) :**

```json
{
  "message": "API key generated successfully...",
  "api_key": "a1b2c3d4e5f6789...",
  "prefix": "a1b2c3d4e5f6",
  "created_at": "2025-12-15 10:30:00"
}
```

#### Utilisation

```http
GET /api/movies
X-API-Key: a1b2c3d4e5f6789...
```

---

### Méthode 3 : Authentification à deux facteurs (2FA)

#### Configuration initiale

**1. Générer le QR code :**

```http
POST /api/2fa/setup
Authorization: Bearer {jwt_token}
```

**2. Scanner le QR code** avec Google Authenticator, Authy, ou similaire

**3. Activer le 2FA** avec le code généré :

```http
POST /api/2fa/enable
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "code": "123456"
}
```

#### Connexion avec 2FA

```http
POST /auth
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "Password123!",
  "totp_code": "123456"
}
```

---

## 📚 Endpoints API

### Vue d'ensemble

L'API expose les ressources suivantes :

- **Films** (`/api/movies`) - CRUD complet, filtres avancés
- **Acteurs** (`/api/actors`) - Gestion des acteurs
- **Réalisateurs** (`/api/directors`) - Gestion des réalisateurs
- **Catégories** (`/api/categories`) - Classification des films
- **Commentaires** (`/api/comments`) - Système de commentaires
- **Médias** (`/api/media_objects`) - Upload d'images
- **Utilisateurs** (`/api/users`) - Gestion des comptes

### Exemples d'utilisation REST

#### Créer un film

```http
POST /api/movies
Authorization: Bearer {token}
Content-Type: application/ld+json

{
  "title": "Inception",
  "description": "A thief who steals corporate secrets...",
  "releaseDate": "2010-07-16",
  "duration": 148,
  "metascore": 74,
  "online": true,
  "director": "/api/directors/1",
  "categories": ["/api/categories/1", "/api/categories/3"],
  "actors": ["/api/actors/1", "/api/actors/2"]
}
```

#### Upload d'une image

```http
POST /api/media_objects
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: [fichier binaire]
movie: /api/movies/1
```

### GraphQL

L'API GraphQL est disponible sur `/api/graphql` avec une interface GraphiQL sur `/api/graphql/graphiql`.

#### Exemple : Lister les films avec leurs catégories

```graphql
query {
  movies {
    edges {
      node {
        id
        name
        categories {
          edges {
            node {
              id
              name
            }
          }
        }
      }
    }
  }
}
```

---

## 🛡️ Sécurité

### Rôles disponibles

```
ROLE_ADMIN    (accès complet)
   ↓
ROLE_EDITOR   (édition de contenu)
   ↓
ROLE_AUTHOR   (création de contenu)
   ↓
ROLE_MEMBER   (lecture seule)
```

### Rate Limiting

Quand la limite est atteinte (429 Too Many Requests) :

```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please try again later.",
  "retry_after_seconds": 3542
}
```

---

## 📖 Liens utiles avec l'API

**Swagger UI** : `http://localhost:8319/api/docs`

**GraphQL** : `http://localhost:8319/api/graphql/graphiql`

---

Projet universitaire - BUT MMI 2025-2026 - WR506D
**Flavie Harre** - BUT MMI - IUT de Troyes
