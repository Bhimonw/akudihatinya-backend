<?php
$host='127.0.0.1'; $db='akudihatinya_fresh'; $user='root'; $pass='';
$pdo=new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
echo "Connected to $db" . PHP_EOL;
$tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo count($tables)." tables" . PHP_EOL;