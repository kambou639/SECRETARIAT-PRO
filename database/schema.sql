-- ============================================================
-- Secrétariat Pro - Schéma de base de données MySQL
-- Application de gestion de secrétariat + vente d'articles
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS secretariat_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secretariat_pro;

-- ------------------------------------------------------------
-- Utilisateurs & rôles
-- ------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telephone VARCHAR(30) DEFAULT NULL,
    role ENUM('admin','secretaire','vendeur') NOT NULL DEFAULT 'vendeur',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion DATETIME DEFAULT NULL,
    tentatives_echouees INT NOT NULL DEFAULT 0,
    bloque_jusqu DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Mot de passe par défaut : Admin@2026 (à changer immédiatement après 1ère connexion)
INSERT INTO users (username, password_hash, full_name, email, role, actif) VALUES
('admin', '$2y$10$czC/Tm7xiEtKzw2bv4dZO.JZwFPuktNZKOFKd2OU7NKHL.Fwt/4NS', 'Administrateur', 'admin@secretariat-pro.local', 'admin', 1);

-- ------------------------------------------------------------
-- Catégories d'articles
-- ------------------------------------------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (nom, description) VALUES
('Fournitures de bureau', 'Papeterie, stylos, classeurs...'),
('Consommables informatiques', 'Cartouches, toners, câbles...'),
('Impressions & Reprographie', 'Services d\'impression et photocopie facturés à la page'),
('Reliure & Finition', 'Spirales, pages cartonnées, dos thermocollés...'),
('Divers', 'Autres articles');

-- ------------------------------------------------------------
-- Articles (produits vendus)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS articles;
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    categorie_id INT DEFAULT NULL,
    type ENUM('produit','service') NOT NULL DEFAULT 'produit',
    prix_achat DECIMAL(12,2) NOT NULL DEFAULT 0,
    prix_vente DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    seuil_alerte INT NOT NULL DEFAULT 5,
    unite VARCHAR(20) NOT NULL DEFAULT 'pièce',
    image VARCHAR(255) DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_articles_code (code),
    INDEX idx_articles_nom (nom)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Articles de démonstration (services d'impression + reliure)
-- Modifiable / complétable librement depuis l'application.
-- ------------------------------------------------------------
INSERT INTO articles (code, nom, description, categorie_id, type, prix_achat, prix_vente, stock, seuil_alerte, unite, actif) VALUES
-- Services d'impression (facturés à la page, pas de gestion de stock)
('IMP-NB-R', 'Impression N&B A4 (recto)', 'Impression noir & blanc, une face, format A4', 3, 'service', 0, 25, 0, 0, 'page', 1),
('IMP-NB-RV', 'Impression N&B A4 (recto-verso)', 'Impression noir & blanc, deux faces, format A4', 3, 'service', 0, 40, 0, 0, 'page', 1),
('PHOTO-NB', 'Photocopie N&B A4', 'Photocopie noir & blanc, format A4', 3, 'service', 0, 25, 0, 0, 'page', 1),
('IMP-COL-R', 'Impression Couleur A4 (recto)', 'Impression couleur, une face, format A4', 3, 'service', 0, 150, 0, 0, 'page', 1),
('IMP-COL-RV', 'Impression Couleur A4 (recto-verso)', 'Impression couleur, deux faces, format A4', 3, 'service', 0, 250, 0, 0, 'page', 1),
('IMP-NB-A3', 'Impression N&B A3', 'Impression noir & blanc, format A3', 3, 'service', 0, 100, 0, 0, 'page', 1),
('IMP-COL-A3', 'Impression Couleur A3', 'Impression couleur, format A3', 3, 'service', 0, 400, 0, 0, 'page', 1),
-- Articles de reliure / finition (produits avec gestion de stock)
('SPIR-P-PT', 'Spirale plastique (petite, jusqu\'à 50p)', 'Spirale plastique pour reliure', 4, 'produit', 150, 300, 100, 10, 'pièce', 1),
('SPIR-P-GD', 'Spirale plastique (grande, +50p)', 'Spirale plastique pour gros documents', 4, 'produit', 250, 500, 100, 10, 'pièce', 1),
('SPIR-METAL', 'Spirale métallique', 'Spirale métallique pour reliure durable', 4, 'produit', 350, 700, 50, 5, 'pièce', 1),
('CART-TRANS', 'Page cartonnée transparente (couverture)', 'Couverture transparente pour reliure', 4, 'produit', 50, 100, 200, 20, 'pièce', 1),
('CART-COUL', 'Page cartonnée couleur (couverture)', 'Couverture cartonnée couleur pour reliure', 4, 'produit', 75, 150, 200, 20, 'pièce', 1),
('RELIURE-THERM', 'Reliure dos thermocollé', 'Dos thermocollé pour reliure de documents', 4, 'produit', 400, 1000, 50, 5, 'pièce', 1);


-- ------------------------------------------------------------
-- Paliers de prix dégressifs (tarifs par quantité)
-- Permet ex. : 1-50 pages = 25 FCFA, 51-200 = 20 FCFA, 201+ = 15 FCFA
-- Si aucune quantité ne correspond à un palier, le prix de base de
-- l'article (articles.prix_vente) s'applique par défaut.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS paliers_prix;
CREATE TABLE paliers_prix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    quantite_min INT NOT NULL,
    quantite_max INT DEFAULT NULL COMMENT 'NULL = illimité',
    prix_unitaire DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_paliers_article (article_id, quantite_min)
) ENGINE=InnoDB;

-- Exemples de tarifs dégressifs sur les impressions N&B (recto et recto-verso)
INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire)
SELECT id, 51, 200, 20 FROM articles WHERE code = 'IMP-NB-R';
INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire)
SELECT id, 201, NULL, 15 FROM articles WHERE code = 'IMP-NB-R';
INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire)
SELECT id, 51, 200, 35 FROM articles WHERE code = 'IMP-NB-RV';
INSERT INTO paliers_prix (article_id, quantite_min, quantite_max, prix_unitaire)
SELECT id, 201, NULL, 30 FROM articles WHERE code = 'IMP-NB-RV';

DROP TABLE IF EXISTS mouvements_stock;
CREATE TABLE mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    type ENUM('entree','sortie','ajustement') NOT NULL,
    quantite INT NOT NULL,
    stock_avant INT NOT NULL,
    stock_apres INT NOT NULL,
    motif VARCHAR(255) DEFAULT NULL,
    reference VARCHAR(60) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Clients
-- ------------------------------------------------------------
DROP TABLE IF EXISTS clients;
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('particulier','entreprise') NOT NULL DEFAULT 'particulier',
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) DEFAULT NULL,
    telephone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    ville VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clients_nom (nom)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Ventes (caisse)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS ventes;
CREATE TABLE ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_facture VARCHAR(30) NOT NULL UNIQUE,
    client_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    montant_brut DECIMAL(14,2) NOT NULL DEFAULT 0,
    remise_type ENUM('aucune','pourcentage','montant') NOT NULL DEFAULT 'aucune',
    remise_valeur DECIMAL(12,2) NOT NULL DEFAULT 0,
    remise_montant DECIMAL(14,2) NOT NULL DEFAULT 0,
    montant_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    montant_paye DECIMAL(14,2) NOT NULL DEFAULT 0,
    monnaie_rendue DECIMAL(14,2) NOT NULL DEFAULT 0,
    mode_paiement ENUM('especes','mobile_money','carte','virement','autre','mixte') NOT NULL DEFAULT 'especes',
    statut_paiement ENUM('payee','partielle','impayee') NOT NULL DEFAULT 'payee',
    statut ENUM('validee','annulee') NOT NULL DEFAULT 'validee',
    observations VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ventes_numero (numero_facture),
    INDEX idx_ventes_date (created_at)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS vente_details;
CREATE TABLE vente_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    article_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(12,2) NOT NULL,
    sous_total DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Paiements liés à une vente : permet le paiement multiple (espèces + mobile money, etc.)
-- au moment de la vente, ainsi que les versements ultérieurs pour une vente à crédit.
DROP TABLE IF EXISTS vente_paiements;
CREATE TABLE vente_paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    mode_paiement ENUM('especes','mobile_money','carte','virement','autre') NOT NULL,
    montant DECIMAL(14,2) NOT NULL,
    user_id INT DEFAULT NULL,
    note VARCHAR(150) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vente_paiements_vente (vente_id),
    INDEX idx_vente_paiements_date (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Courrier (secrétariat)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS courriers;
CREATE TABLE courriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('entrant','sortant') NOT NULL,
    numero VARCHAR(50) NOT NULL,
    objet VARCHAR(255) NOT NULL,
    expediteur VARCHAR(150) DEFAULT NULL,
    destinataire VARCHAR(150) DEFAULT NULL,
    date_courrier DATE NOT NULL,
    date_enregistrement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('recu','en_traitement','traite','archive','envoye') NOT NULL DEFAULT 'recu',
    fichier_joint VARCHAR(255) DEFAULT NULL,
    observations TEXT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_courriers_numero (numero),
    INDEX idx_courriers_type (type)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Rendez-vous / Agenda
-- ------------------------------------------------------------
DROP TABLE IF EXISTS rendezvous;
CREATE TABLE rendezvous (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    date_rdv DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME DEFAULT NULL,
    lieu VARCHAR(150) DEFAULT NULL,
    contact_nom VARCHAR(150) DEFAULT NULL,
    contact_telephone VARCHAR(30) DEFAULT NULL,
    client_id INT DEFAULT NULL,
    statut ENUM('planifie','confirme','annule','termine') NOT NULL DEFAULT 'planifie',
    user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_rdv_date (date_rdv)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Paramètres (clé/valeur) - infos entreprise, config
-- ------------------------------------------------------------
DROP TABLE IF EXISTS parametres;
CREATE TABLE parametres (
    cle VARCHAR(80) NOT NULL PRIMARY KEY,
    valeur TEXT DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO parametres (cle, valeur) VALUES
('nom_entreprise', 'Secrétariat Pro'),
('slogan', 'Gestion de secrétariat et vente d\'articles'),
('adresse', 'Ouagadougou, Burkina Faso'),
('telephone', ''),
('email', ''),
('logo', ''),
('devise', 'FCFA'),
('prefixe_facture', 'FAC'),
('taux_tva', '0');

-- ------------------------------------------------------------
-- Journal d'activité (audit simplifié)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS activity_log;
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
