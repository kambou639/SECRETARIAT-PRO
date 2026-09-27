# Secrétariat Pro

Application web PHP complète de gestion de secrétariat et de vente d'articles,
100% autonome (**aucune dépendance CDN**), multi-utilisateurs avec rôles,
import/export Excel, étiquettes QR code / codes-barres.

## Fonctionnalités

- **Authentification multi-utilisateurs** avec 3 rôles : Administrateur, Secrétaire, Vendeur
- **Caisse / vente d'articles** : panier interactif, gestion de stock en temps réel, facture imprimable
- **Services d'impression facturés à la page** : impression/photocopie N&B ou couleur, recto/recto-verso, A4/A3 - prix au feuillet, sans gestion de stock (contrairement aux produits physiques)
- **Calculateur d'impression** : sélection du type de document + nombre de pages + reliure et couverture cartonnée en option, avec calcul instantané du total avant ajout au panier
- **Tarifs dégressifs par palier de quantité** : configurables par article (ex. 1-50 pages à 25 FCFA, 51-200 à 20 FCFA, 201+ à 15 FCFA) - le prix est toujours recalculé et vérifié côté serveur à la validation
- **Articles de reliure & finition** : spirales (plastique/métallique), pages cartonnées de couverture, dos thermocollés - gérés comme des produits classiques avec stock
- **Catalogue d'articles** : catégories, prix, stock, seuils d'alerte, images
- **Étiquettes QR Code & codes-barres (Code128)** générées en PHP pur (sans librairie externe), imprimables en planche
- **Courrier** : enregistrement entrant/sortant, pièces jointes, suivi de statut
- **Agenda / Rendez-vous** : planification, liaison avec les clients
- **Clients** : fiche client avec historique d'achats et de rendez-vous
- **Import / Export Excel** : import de catalogue d'articles, export ventes/clients/articles (généré côté navigateur avec SheetJS)
- **Statistiques** : tableaux de bord et graphiques (Chart.js)
- **Sécurité** : CSRF sur tous les formulaires, hashage bcrypt des mots de passe, protection anti brute-force (blocage temporaire après 5 échecs), sessions sécurisées (cookies HttpOnly, régénération d'ID), validation des uploads (type MIME réel), requêtes préparées PDO partout

## Prérequis

- PHP **8.0+** avec extensions : `pdo_mysql`, `gd`, `mbstring`, `fileinfo`
- MySQL **5.7+** ou MariaDB **10.3+**
- Serveur web Apache (avec `mod_rewrite`/`mod_headers` recommandé) ou Nginx

## Installation

1. **Copier les fichiers** sur votre serveur (ex. dans `htdocs/secretariat_pro` pour XAMPP/WAMP).

2. **Créer la base de données** en important le schéma :
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   Cela crée la base `secretariat_pro`, toutes les tables, et un compte administrateur par défaut :
   - **Identifiant** : `admin`
   - **Mot de passe** : `Admin@2026`

   ⚠️ **Changez ce mot de passe immédiatement après la première connexion** (menu utilisateur → Mon profil).

3. **Configurer la connexion à la base de données** dans `config/config.php` :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'secretariat_pro');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   ```

4. **Permissions des dossiers** : le dossier `uploads/` (et ses sous-dossiers `articles/`, `courriers/`, `logo/`) doit être accessible en écriture par le serveur web :
   ```bash
   chmod -R 775 uploads/
   chown -R www-data:www-data uploads/   # adapter selon votre serveur
   ```

5. **Accéder à l'application** via votre navigateur : `http://votre-domaine/` (ou `http://localhost/secretariat_pro/`).

## Structure du projet

```
├── config/             Configuration (BDD, constantes) - accès web bloqué
├── includes/           Cœur applicatif (auth, fonctions, templates, QR/code-barres) - accès web bloqué
├── database/           Schéma SQL - accès web bloqué
├── assets/             CSS/JS/polices - 100% en local, aucun CDN
├── uploads/             Fichiers téléversés (images articles, pièces jointes courrier, logo)
├── articles/           Module catalogue, catégories, étiquettes QR/codes-barres
├── ventes/             Caisse (POS), historique, facture, annulation
├── clients/            Gestion des clients
├── courrier/           Courrier entrant/sortant
├── rendezvous/         Agenda / rendez-vous
├── utilisateurs/       Gestion des utilisateurs (admin) + profil personnel
├── rapports/           Statistiques et graphiques
├── import_export/      Import/export Excel
├── parametres/         Paramètres de l'entreprise (nom, logo, devise...)
├── login.php / logout.php / dashboard.php / index.php
```

## Rôles et permissions

| Fonctionnalité              | Admin | Secrétaire | Vendeur |
|------------------------------|:-----:|:----------:|:-------:|
| Caisse / Ventes              | ✅    | ❌          | ✅      |
| Catalogue articles (lecture) | ✅    | ✅          | ✅      |
| Catalogue articles (créer/modifier) | ✅ | ✅      | ❌      |
| Suppression articles         | ✅    | ❌          | ❌      |
| Courrier & Agenda             | ✅    | ✅          | ❌      |
| Clients                       | ✅    | ✅          | ✅ (consultation caisse) |
| Utilisateurs, Paramètres, Statistiques, Import/Export | ✅ | ❌ | ❌ |

## Notes techniques

- **Articles "Produit" vs "Service"** : chaque article a un type. Les **produits** (fournitures, spirales, pages cartonnées...) ont un stock géré avec seuils d'alerte. Les **services** (impression N&B/couleur, photocopie, recto/recto-verso, A4/A3...) sont facturés à l'unité (généralement la page) mais n'ont pas de stock : ils restent toujours disponibles en caisse et n'apparaissent jamais dans les alertes de stock. La base est livrée avec 7 tarifs d'impression et 6 articles de reliure en exemple, tous modifiables/complétables depuis **Articles → Catalogue**.
- **Tarifs dégressifs** : depuis la fiche d'un article (**Articles → Catalogue → Modifier → Tarifs dégressifs**), vous pouvez définir des paliers de prix par quantité (ex. « à partir de 51 pages, jusqu'à 200 → 20 FCFA », « à partir de 201 → 15 FCFA »). Le prix appliqué est **toujours recalculé côté serveur** au moment de la vente à partir de ces paliers - impossible de le manipuler depuis le navigateur.
- **Calculateur d'impression** (bouton dans la Caisse) : permet de choisir un type de document, indiquer le nombre de pages, et ajouter en option une reliure et une couverture cartonnée en un seul geste, avec le total calculé en direct (paliers dégressifs inclus) avant l'ajout au panier.
- En caisse, cliquer sur un article ouvre une fenêtre de saisie de quantité (pratique pour entrer directement « 45 pages » plutôt que de cliquer 45 fois), avec le prix recalculé en temps réel selon les paliers.
- **Aucune dépendance CDN** : Bootstrap 5, Font Awesome 6, Chart.js, SheetJS, html2canvas et jsPDF sont fournis localement dans `assets/`.
- **QR codes et codes-barres** générés nativement en PHP (GD), sans bibliothèque tierce : voir `includes/QRHelper.php` (basé sur l'implémentation libre de Kazuhiko Arase, licence MIT) et `includes/Barcode128.php` (Code128, implémentation originale).
- **Import/Export Excel** utilise SheetJS côté navigateur : aucune donnée transite par un service tiers, tout reste sur votre serveur.
- Les mots de passe sont hashés avec `password_hash()` (bcrypt). Les mots de passe ne sont jamais stockés ni journalisés en clair.
- Les montants de vente sont **toujours recalculés côté serveur** à partir des prix en base au moment de la validation (jamais depuis les données envoyées par le navigateur), pour éviter toute manipulation.

## Support

Application développée sur mesure. Pour toute évolution (nouveaux rôles, modules, rapports), adaptez les fichiers dans les dossiers correspondants - l'architecture est volontairement simple (PHP procédural + PDO) pour rester facilement modifiable.
