#!/usr/bin/php
<?php

$debug = false; // Set to true to enable debug output

if ($debug) echo "Opening serial port...\n";

$ser = fopen("/dev/ttyUSB0", "r");

if (!$ser) {
    if ($debug) echo "❌ Failed to open serial port.\n";
    exit;
}

if ($debug) echo "✅ Serial port opened.\n";

try {
    $dbh = new PDO('sqlite:/var/www/html/pulseox.db');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($debug) echo "✅ Connected to database.\n";

    $query = $dbh->prepare("INSERT INTO pulseox (stampdate, stamptime, spo2, bpm) VALUES (:param0, :param1, :param2, :param3);");

    while (!feof($ser)) {
        $buffer = fgets($ser);
        $din = explode(" ", $buffer);

        if (count($din) >= 5) {
            $query->bindParam(':param0', $din[0]);
            $query->bindParam(':param1', $din[1]);
            $query->bindParam(':param2', $din[3]);
            $query->bindParam(':param3', $din[4]);
            $query->execute();

            if ($debug) echo "Inserted: $din[0] $din[1] $din[3] $din[4]\n";
        }
    }

    $dbh = null;
} catch (PDOException $e) {
    error_log("poxs.php error: " . $e->getMessage());
    if ($debug) echo "❌ Exception: " . $e->getMessage() . "\n";
}
