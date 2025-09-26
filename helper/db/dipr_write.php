<?php

$read_hostname = '192.168.5.67';
$read_database = 'dipr';
$read_username = 'postgres';
$read_password = 'postgres';
$read_port = 5432;

try {
	$dipr_write_db = new PDO("pgsql:host=$read_hostname;port=$read_port;dbname=$read_database", $read_username, $read_password);
} catch (PDOException $e) {
	die("Coluldn't able to connect to Write Database $read_database because of " . $e->getMessage());
}