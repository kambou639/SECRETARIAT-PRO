-- ------------------------------------------------------------
-- Migration : paiement multiple, vente à crédit (versements) et rapport de caisse
-- À exécuter une seule fois sur une base existante (ne supprime aucune donnée).
-- Pour une nouvelle installation, schema.sql contient déjà ces éléments.
-- ------------------------------------------------------------

ALTER TABLE ventes
    MODIFY COLUMN mode_paiement ENUM('especes','mobile_money','carte','virement','autre','mixte') NOT NULL DEFAULT 'especes',
    ADD COLUMN statut_paiement ENUM('payee','partielle','impayee') NOT NULL DEFAULT 'payee' AFTER mode_paiement;

CREATE TABLE IF NOT EXISTS vente_paiements (
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

-- Reconstitue un paiement initial pour chaque vente déjà enregistrée,
-- à partir de son mode_paiement / montant_paye existants (pour l'historique des paiements).
INSERT INTO vente_paiements (vente_id, mode_paiement, montant, created_at)
SELECT v.id, v.mode_paiement, v.montant_paye, v.created_at
FROM ventes v
WHERE v.montant_paye > 0
  AND NOT EXISTS (SELECT 1 FROM vente_paiements vp WHERE vp.vente_id = v.id);

-- Recalcule le statut de paiement de chaque vente existante.
UPDATE ventes
SET statut_paiement = CASE
    WHEN montant_paye <= 0 THEN 'impayee'
    WHEN montant_paye < montant_total THEN 'partielle'
    ELSE 'payee'
END
WHERE statut = 'validee';
