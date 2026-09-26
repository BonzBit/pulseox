#!/usr/bin/php
<?php

$ser = fopen("/dev/ttyUSB0","r");

if (!$ser)
{
echo "port error";
exit;
}

try
{
$dbh=new PDO('sqlite:/var/www/html/pulseox.db');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$query=$dbh->prepare("INSERT into pulseox (stampdate, stamptime, spo2, bpm) VALUES (:param0, :param1, :param2, :param3);");

while (!feof($ser))
{
$buffer = fgets($ser);
$din = explode(" ", $buffer);
#echo "date $din[0] time $din[1] sp02 $din[3] bpm $din[4] \n";

$query->bindParam(':param0', $din[0]);
$query->bindParam(':param1', $din[1]);
$query->bindParam(':param2', $din[3]);
$query->bindParam(':param3', $din[4]);
$query->execute();
}

$dbh = null;

}
catch(PDOException $e)
{
print 'Exception: ' .$e->getMessage();
}

?>


