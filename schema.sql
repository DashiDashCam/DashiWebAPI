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
  id INT NOT NULL,
  accountID INT NOT NULL,
  videoContext BLOB NOT NULL,
  started DATETIME NOT NULL,
  size INT NOT NULL,
  PRIMARY KEY (id, accountID),
  FOREIGN KEY (accountID) REFERENCES Accounts (id)
);
