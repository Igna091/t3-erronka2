-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-11-2021 a las 18:26:04
-- Versión del servidor: 10.4.21-MariaDB
-- Versión de PHP: 8.0.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bdweb`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produktuak`
--

CREATE TABLE `produktuak` (
  `id` int(11) NOT NULL,
  `izena` varchar(30) NOT NULL,
  `deskripzioa` varchar(150) NOT NULL,
  `salneurria` float NOT NULL,
  `pic` varchar(60) NOT NULL,
  `stock` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `produktuak`
--

INSERT INTO `produktuak` (`id`, `izena`, `deskripzioa`, `salneurria`, `pic`, `stock`) VALUES
(1, 'produktu 1', '            produktu 1ren deskripzioa        ', 12, 'generikoa.jpg', 5),
(2, 'produktu 2', 'produktu 2ren deskripzioa', 10, 'generikoa.jpg', 7),
(4, 'produktu 3', 'hirugarrena da, etiketa barik', 8, 'producto3.jpg', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` mediumint(9) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `izena` varchar(30) NOT NULL,
  `abizena` varchar(60) NOT NULL,
  `hiria` varchar(60) NOT NULL,
  `lurraldea` varchar(60) NOT NULL,
  `herrialdea` varchar(30) NOT NULL,
  `postakodea` int(5) NOT NULL,
  `telefonoa` int(9) NOT NULL,
  `irudia` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `izena`, `abizena`, `hiria`, `lurraldea`, `herrialdea`, `postakodea`, `telefonoa`, `irudia`) VALUES
(1, 'admin@bdweb', '5f4dcc3b5aa765d61d8327deb882cf99', 'admin', '', 'eibar', 'gipuzkoa', 'euskal herria', 20600, 121212121, 'Soy_Admin.png'),
(2, 'izaskun@uni.eus', '5f4dcc3b5aa765d61d8327deb882cf99', 'izaskun', 'ffff', 'ss', 'ddd', 'España', 48280, 121232343, 'usuario.jpg');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `produktuak`
--
ALTER TABLE `produktuak`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `produktuak`
--
ALTER TABLE `produktuak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` mediumint(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
