# Projet de Dépollution

Ce projet est une application de gestion de dépollution qui permet aux administrateurs et aux opérateurs de gérer efficacement les projets de dépollution. L'application utilise des données JSON comme source pour stocker et récupérer les informations nécessaires.

## Structure du Projet

- **public/** : Contient les fichiers accessibles au public, y compris les interfaces utilisateur.
  - `index.php` : Point d'entrée de l'application.
  - `admin.php` : Interface pour les administrateurs.
  - `operateur1.php` : Interface pour le premier opérateur.
  - `operateur2.php` : Interface pour le deuxième opérateur.
  - `api.php` : Gère les requêtes API.
  - **assets/** : Contient les fichiers CSS et JavaScript.
  
- **src/** : Contient la logique de l'application.
  - **Controllers/** : Gère les requêtes et les réponses.
  - **Models/** : Définit les structures de données.
  - **Repositories/** : Gère l'accès aux données.
  - **Services/** : Contient la logique métier.
  - **Forms/** : Gère les formulaires.
  - **Middlewares/** : Protège les routes.
  - **Utils/** : Contient des fonctions utilitaires.
  - **Views/** : Contient les templates de l'application.

- **data/** : Contient les fichiers JSON pour les projets, tâches, sites et utilisateurs.

- **config/** : Contient les fichiers de configuration de l'application.

- **tests/** : Contient les tests unitaires pour assurer la qualité du code.

- **logs/** : Dossier pour les fichiers de log.

- **storage/** : Dossier pour le stockage de fichiers.

## Installation

1. Clonez le dépôt sur votre machine locale.
2. Assurez-vous d'avoir PHP et un serveur web (comme WAMP) installés.
3. Configurez votre serveur pour pointer vers le dossier `public`.
4. Installez les dépendances via Composer si nécessaire.

## Utilisation

- Accédez à `index.php` pour commencer à utiliser l'application.
- Les administrateurs peuvent gérer les projets et les utilisateurs via `admin.php`.
- Les opérateurs peuvent saisir et confirmer les informations des voyages via leurs interfaces respectives.

## Contribuer

Les contributions sont les bienvenues ! Veuillez soumettre une demande de tirage pour toute amélioration ou correction.

## License

Ce projet est sous licence MIT. Veuillez consulter le fichier LICENSE pour plus de détails.