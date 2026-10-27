-- BDweb, the shop's database. MySQL runs this once, on the first start with an empty volume,
-- in the database named by MYSQL_DATABASE (compose.yaml). What changed from the original
-- phpMyAdmin dump, and why, is in CHANGES.md.

SET NAMES utf8mb4;

CREATE TABLE produktuak (
  id int NOT NULL AUTO_INCREMENT,
  izena varchar(30) NOT NULL,
  deskripzioa varchar(150) NOT NULL,
  -- Money as an exact decimal, not a float
  salneurria decimal(8,2) NOT NULL,
  pic varchar(60) NOT NULL,
  stock int NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO produktuak (id, izena, deskripzioa, salneurria, pic, stock) VALUES
(1, 'produktu 1', 'produktu 1ren deskripzioa', 12.00, 'generikoa.jpg', 5),
(2, 'produktu 2', 'produktu 2ren deskripzioa', 10.00, 'generikoa.jpg', 7),
(4, 'produktu 3', 'hirugarrena da, etiketa barik', 8.00, 'producto3.jpg', 3);

CREATE TABLE users (
  id mediumint NOT NULL AUTO_INCREMENT,
  -- The email; long enough for any address, and unique, so it can't be registered twice
  username varchar(254) NOT NULL,
  -- An Argon2id hash from password_hash(); the original's varchar(60) held unsalted MD5
  password varchar(255) NOT NULL,
  -- Who is an admin is stored, instead of comparing usernames in the code
  is_admin tinyint(1) NOT NULL DEFAULT 0,
  -- Goes up when the password changes, which logs out every other session
  session_version int NOT NULL DEFAULT 0,
  izena varchar(60) NOT NULL,
  abizena varchar(60) NOT NULL DEFAULT '',
  hiria varchar(60) NOT NULL DEFAULT '',
  lurraldea varchar(60) NOT NULL DEFAULT '',
  herrialdea varchar(60) NOT NULL DEFAULT '',
  -- Text, not int: 01001 (Gasteiz) keeps its zero, and a phone can start with +
  postakodea char(5) NOT NULL DEFAULT '',
  telefonoa varchar(15) NOT NULL DEFAULT '',
  -- A file in storage/perfiles, outside the web root
  irudia varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The original two accounts, without a password that works: '!' is no hash, so no password
-- matches it. Give one a password with bin/set-password.php (README.md). The original shipped
-- both with the password "password", as unsalted MD5.
INSERT INTO users (id, username, password, is_admin, izena, abizena, hiria, lurraldea, herrialdea, postakodea, telefonoa, irudia) VALUES
(1, 'admin@bdweb', '!', 1, 'admin', '', 'eibar', 'gipuzkoa', 'euskal herria', '20600', '121212121', 'Soy_Admin.jpg'),
(2, 'izaskun@uni.eus', '!', 0, 'izaskun', 'ffff', 'ss', 'ddd', 'España', '48280', '121232343', 'usuario.jpg');

-- Wrong passwords, counted per account and per IP to slow down guessing (lib/auth.php)
CREATE TABLE login_failures (
  id bigint NOT NULL AUTO_INCREMENT,
  username varchar(254) NOT NULL,
  ip varchar(45) NOT NULL,
  failed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY login_failures_username (username, failed_at),
  KEY login_failures_ip (ip, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
