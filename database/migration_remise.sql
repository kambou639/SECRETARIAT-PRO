-- ------------------------------------------------------------
-- Migration : prise en compte de la remise sur les ventes
-- À exécuter une seule fois sur une base existante (ne supprime aucune donnée).
-- Pour une nouvelle installation, schema.sql contient déjà ces colonnes.
-- ------------------------------------------------------------

ALTER TABLE ventes
    ADD COLUMN montant_brut DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER user_id,
    ADD COLUMN remise_type ENUM('aucune','pourcentage','montant') NOT NULL DEFAULT 'aucune' AFTER montant_brut,
    ADD COLUMN remise_valeur DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER remise_type,
    ADD COLUMN remise_montant DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER remise_valeur;

-- Pour les ventes déjà enregistrées (sans remise), le montant brut = montant total.
UPDATE ventes SET montant_brut = montant_total WHERE montant_brut = 0;
