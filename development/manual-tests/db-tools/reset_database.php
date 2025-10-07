<?php
$host='127.0.0.1'; $user='root'; $pass=''; $db='akudihatinya';
$pdo=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
echo "DROPPING database $db" . PHP_EOL; $pdo->exec("DROP DATABASE IF EXISTS `$db`");
echo "Creating database $db" . PHP_EOL; $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Done. Run migrations manually." . PHP_EOL;