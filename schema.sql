CREATE DATABASE `Dashi`;

USE `Dashi`;

CREATE TABLE Accounts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(64) UNIQUE NOT NULL,
  password VARCHAR(96) NOT NULL,
  fullName VARCHAR(64) NOT NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Videos (
  id           BINARY(32) UNIQUE NOT NULL,
  accountID    INT        NOT NULL,
  thumbnail    MEDIUMBLOB NOT NULL,
  videoContent LONGBLOB DEFAULT NULL,
  started      DATETIME   NOT NULL,
  size         INT        NOT NULL,
  length       INT        NOT NULL,
  startLat     FLOAT    DEFAULT NULL,
  startLong    FLOAT    DEFAULT NULL,
  endLat       FLOAT    DEFAULT NULL,
  endLong      FLOAT    DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (accountID) REFERENCES Accounts (id)
);

CREATE TABLE VideoChunks (
  id          INT NOT NULL AUTO_INCREMENT,
  part        INT NOT NULL,
  videoID     INT NOT NULL,
  content     LONGBLOB NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (videoID) REFERENCES Videos (id)
);

CREATE TABLE Shares (
  id      BINARY(256) UNIQUE NOT NULL,
  videoID INT                NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (videoID) REFERENCES Videos (id)
);

CREATE TABLE Token_Types (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(7) NOT NULL
);

/* Populate table with the valid token types */
INSERT INTO Token_Types (type) VALUES ('access');
INSERT INTO Token_Types (type) VALUES ('refresh');

CREATE TABLE Auth_Tokens (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(1024) NOT NULL UNIQUE,
  accountID INT NOT NULL,
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastUsed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires DATETIME NOT NULL,
  active BOOL NOT NULL DEFAULT TRUE,
  issuedTo VARCHAR(15) NOT NULL,
  typeID INT NOT NULL,
  FOREIGN KEY (accountID) REFERENCES Accounts (id),
  FOREIGN KEY (typeID) REFERENCES Token_Types (id)
);

CREATE USER 'api'@'localhost' IDENTIFIED BY 'password';
GRANT SELECT ON Dashi.* TO 'api'@'localhost';
GRANT UPDATE ON Dashi.* TO 'api'@'localhost';
GRANT INSERT ON Dashi.* TO 'api'@'localhost';
GRANT DELETE ON Dashi.* TO 'api'@'localhost';
