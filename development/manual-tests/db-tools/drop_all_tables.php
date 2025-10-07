<?php
$host='127.0.0.1'; $user='root'; $pass=''; $db='akudihatinya';
$pdo=new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t){ echo "Dropping $t\n"; $pdo->exec("DROP TABLE IF EXISTS `$t`"); }
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "All tables dropped." . PHP_EOL;