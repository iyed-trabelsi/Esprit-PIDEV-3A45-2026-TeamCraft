-- Drop existing tables
DROP TABLE IF EXISTS participation;
DROP TABLE IF EXISTS evenement;
DROP TABLE IF EXISTS place;

-- Create place table with id_place
CREATE TABLE place (
    id_place INT AUTO_INCREMENT NOT NULL,
    nom_place VARCHAR(255) NOT NULL,
    type_place VARCHAR(255) NOT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    capacite_max INT NOT NULL,
    PRIMARY KEY(id_place)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create evenement table with id_evenement
CREATE TABLE evenement (
    id_evenement INT AUTO_INCREMENT NOT NULL,
    place_id INT NOT NULL,
    nom_evenement VARCHAR(255) NOT NULL,
    type_evenement VARCHAR(255) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    status VARCHAR(255) NOT NULL,
    INDEX IDX_B26681EDA6A219 (place_id),
    PRIMARY KEY(id_evenement),
    CONSTRAINT FK_B26681EDA6A219 FOREIGN KEY (place_id) REFERENCES place (id_place) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Create participation table with id_participation
CREATE TABLE participation (
    id_participation INT AUTO_INCREMENT NOT NULL,
    evenement_id INT NOT NULL,
    date_inscription DATETIME NOT NULL,
    INDEX IDX_AB55E24FFD02F13 (evenement_id),
    PRIMARY KEY(id_participation),
    CONSTRAINT FK_AB55E24FFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
