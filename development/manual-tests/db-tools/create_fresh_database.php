<?php
$host='127.0.0.1'; $user='root'; $pass=''; $new='akudihatinya_fresh';
$pdo=new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `$new`");
$pdo->exec("CREATE DATABASE `$new` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Created database $new" . PHP_EOL;