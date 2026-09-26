#!/usr/bin/php
<?php

$debug = false;

$serial = '/dev/serial/by-id/usb-Prolific_Technology_Inc._USB-Serial_Controller-if00-port0';
if (!file_exists($serial)) {
    $serial = '/dev/ttyUSB0';
}

if ($debug) echo "Opening serial port $serial...\n";

$ser = @fopen($serial, "r");
if (!$ser) {
    error_log("poxs.php: failed to open serial port $serial");
    exit(1);
}

stream_set_blocking($ser, true);

if ($debug) echo "Serial port opened.\n";

try {
    $dbh = new PDO('sqlite:/var/www/html/pulseox.db');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($debug) echo "Connected to database.\n";

    $query = $dbh->prepare("INSERT INTO pulseox (stampdate, stamptime, spo2, bpm) VALUES (:param0, :param1, :param2, :param3);");

    while (true) {
        $read = array($ser);
        $write = null;
        $except = null;
        $n = @stream_select($read, $write, $except, 10);
        if ($n === false) {
            error_log("poxs.php: stream_select failed on $serial");
            exit(1);
        }
        if ($n === 0) {
            error_log("poxs.php: serial read timed out on $serial");
            exit(1);
        }

        $buffer = fgets($ser);
        if ($buffer === false) {
            error_log("poxs.php: serial read failed on $serial");
            exit(1);
        }

        $din = explode(" ", $buffer);
        if (count($din) >= 5) {
            $query->bindParam(':param0', $din[0]);
            $query->bindParam(':param1', $din[1]);
            $query->bindParam(':param2', $din[3]);
            $query->bindParam(':param3', $din[4]);
            $query->execute();

            file_put_contents('/home/sam/last_update.txt', (string)time());
        }
    }
} catch (PDOException $e) {
    error_log("poxs.php error: " . $e->getMessage());
    exit(1);
}
