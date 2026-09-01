-- Schéma de la base de données — Système d'archivage intelligent des mémoires
-- Dérivé du schéma relationnel présenté au Chapitre 6 du mémoire

CREATE DATABASE IF NOT EXISTS archivage_memoires CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE archivage_memoires;

-- M04 — Comptes utilisateurs
CREATE TABLE utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('ETUDIANT', 'ENCADREUR', 'GESTIONNAIRE', 'ADMINISTRATEUR') NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE etablissement (
    id_etablissement INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    ville VARCHAR(100)
) ENGINE=InnoDB;

-- M01 — Gestion des mémoires
CREATE TABLE memoire (
    id_memoire INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    resume TEXT,
    mots_cles VARCHAR(255),
    annee_academique INT NOT NULL,
    filiere VARCHAR(100) NOT NULL,
    chemin_fichier VARCHAR(255) NOT NULL,
    statut ENUM('EN_ATTENTE', 'PUBLIE', 'REJETE', 'RETIRE') NOT NULL DEFAULT 'EN_ATTENTE',
    id_etudiant INT NOT NULL,
    id_encadreur INT NULL,
    id_etablissement INT NOT NULL,
    date_depot DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_etudiant) REFERENCES utilisateur(id_utilisateur),
    FOREIGN KEY (id_encadreur) REFERENCES utilisateur(id_utilisateur),
    FOREIGN KEY (id_etablissement) REFERENCES etablissement(id_etablissement),
    UNIQUE KEY uk_doublon (titre, id_etudiant, annee_academique)
) ENGINE=InnoDB;
-- Note : id_encadreur n'apparaît pas dans le schéma relationnel du Chapitre 6 du mémoire ;
-- il est ajouté ici pour pouvoir techniquement appliquer la règle métier du §2.3.2
-- (« accès au texte intégral réservé ... à son encadreur »), qui suppose un lien mémoire-encadreur.

-- Historique des décisions de validation (statut fait foi = memoire.statut)
CREATE TABLE depot (
    id_depot INT AUTO_INCREMENT PRIMARY KEY,
    date_depot DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('EN_ATTENTE', 'PUBLIE', 'REJETE', 'RETIRE') NOT NULL,
    commentaire_validation TEXT,
    id_memoire INT NOT NULL,
    id_gestionnaire INT,
    FOREIGN KEY (id_memoire) REFERENCES memoire(id_memoire),
    FOREIGN KEY (id_gestionnaire) REFERENCES utilisateur(id_utilisateur)
) ENGINE=InnoDB;

-- M02 — Journal des consultations (alimente aussi M05)
CREATE TABLE consultation (
    id_consultation INT AUTO_INCREMENT PRIMARY KEY,
    date_consultation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_memoire INT NOT NULL,
    id_utilisateur INT NOT NULL,
    FOREIGN KEY (id_memoire) REFERENCES memoire(id_memoire),
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
) ENGINE=InnoDB;

-- M03 — Rapports de similarité
CREATE TABLE rapport_similarite (
    id_rapport INT AUTO_INCREMENT PRIMARY KEY,
    taux_similarite FLOAT NOT NULL,
    id_memoire INT NOT NULL,
    id_memoire_compare INT NOT NULL,
    date_analyse DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_memoire) REFERENCES memoire(id_memoire),
    FOREIGN KEY (id_memoire_compare) REFERENCES memoire(id_memoire)
) ENGINE=InnoDB;

-- M04 — Journal d'activité transversal
CREATE TABLE journal_activite (
    id_journal INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    date_action DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_utilisateur INT,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
) ENGINE=InnoDB;

-- Paramètres de configuration (dont le seuil d'alerte de similarité, UC3.3)
CREATE TABLE parametre (
    cle VARCHAR(100) PRIMARY KEY,
    valeur VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO parametre (cle, valeur) VALUES ('seuil_similarite', '30');

-- Données de démonstration
INSERT INTO etablissement (nom, ville) VALUES
('CFI-CIRAS', 'Brazzaville'),
('Université Marien Ngouabi', 'Brazzaville');

-- Mot de passe identique pour tous les comptes de démonstration : Passer123
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role) VALUES
('Onanga Angassina', 'Lucien', 'admin@archivage.cg', '$2y$10$gfouCyumGriPd0KmH51MI.4iPbZnnuB3pR6IqSqZv291/tHYifqJW', 'ADMINISTRATEUR'),
('Moukala', 'Sarah', 'gestionnaire@archivage.cg', '$2y$10$gfouCyumGriPd0KmH51MI.4iPbZnnuB3pR6IqSqZv291/tHYifqJW', 'GESTIONNAIRE'),
('Ibara', 'Paul', 'encadreur@archivage.cg', '$2y$10$gfouCyumGriPd0KmH51MI.4iPbZnnuB3pR6IqSqZv291/tHYifqJW', 'ENCADREUR'),
('Nkounkou', 'Grace', 'etudiant@archivage.cg', '$2y$10$gfouCyumGriPd0KmH51MI.4iPbZnnuB3pR6IqSqZv291/tHYifqJW', 'ETUDIANT');
