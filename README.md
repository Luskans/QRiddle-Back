# Application (Laravel)

Ce projet est une application de type "Chasse au trésor" construite avec le framework Laravel. L'application permet aux utilisateurs de créer, jouer, laisser des avis et consulter des classements pour des énigmes géolocalisées.

---

## Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Architecture et design](#architecture-et-design)
- [Design Patterns Implémentés](#design-patterns-implémentés)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Tests](#tests)
- [Licence](#licence)

---

## Fonctionnalités

- **Gestion d'utilisateurs**  
  - Inscription, connexion, déconnexion avec Sanctum
  - Profil utilisateur
  - Possibilité de voir ses listes d'énigmes jouées et créées

- **Gestion des énigmes (Riddles)**  
  - Création, modification, suppression d’énigmes par leurs créateurs
  - Passage en mode "public" ou "privé" avec mot de passe généré aléatoirement

- **Gestion des étapes (Steps)**  
  - Ajout d'étapes pour chaque énigme avec QR codes uniques générés automatiquement
  - Possibilité de télécharger les QR codes

- **Gestion des indices (Hints)**  
  - Ajout d'indices pour chaque étape qui peuvent être du texte, une image, ou un audio

- **Gameplay**  
  - Démarrage de sessions de jeu pour jouer aux énigmes
  - Validation d’étapes en scannant le QR code approprié
  - Déverrouillage d’indices
  - Abandon ou complétion de la partie
  - Calcul du score automatique
  - Compte rendu de la session jouée

- **Classements et avis**  
  - Classement global et par énigme
  - Possibilité de laisser un avis (review) sur une énigme terminée
  - Calcul et mise à jour des scores finaux avec bonus/malus

---

## Architecture et design

Le code suit une architecture en couche avec des Services, Repositories et Interfaces qui sépare les responsabilités :

- **Controllers** pour gérer les requêtes HTTP.
- **Services** (ex: `GameplayService`, `ScoreService`, `RiddleService`, etc.) qui contiennent la logique métier.
- **Repositories** (ex: `SessionStepRepository`, `ScoreRepository`, `RiddleRepository`, etc.) qui contiennent l'accès aux données'.
- **Interfaces** pour faciliter l'injection de dépendances et permettre le mocking, notamment lors des tests.
- **Modèles Eloquent** pour l'accès aux données.
- **Factories et Seeders** pour la création de données de test.

---

## Installation

1. Clonez le dépôt :
  ```bash
  git clone https://github.com/*.git
2. Installer les dépendances :
  ```bash
  composer install
3. Copiez le fichier .env.example en .env et configurez vos paramètres de connexion à la base de données :
  ```bash
  cp .env.example .env
4. Générez la clé de l'application :
  ```bash
  php artisan key:generate
5. Exécutez les migrations (et seeders si besoin) :
  ```bash
  php artisan migrate --seed
6. Lancez le serveur de développement :
  ```bash
  php artisan serve --host ***.***.*.** --port 8000
