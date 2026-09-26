<?php

echo "Opening serial port...\n";
$ser = fopen("/dev/ttyUSB0","r");

if (!$ser) {
    echo "❌ Port error\n";
    exit;
}

echo "✅ Serial port opened.\n";

try {
    $dbh = new PDO('sqlite:/var/www/html/pulseox.db');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Connected to database.\n";

    $query = $dbh->prepare("INSERT INTO pulseox (stampdate, stamptime, spo2, bpm) VALUES (:param0, :param1, :param2, :param3);");

    while (!feof($ser)) {
        $buffer = fgets($ser);
        echo "Received: $buffer\n"; // <-- Add this line

        $din = explode(" ", $buffer);

        if (count($din) >= 5) {
            $query->bindParam(':param0', $din[0]);
            $query->bindParam(':param1', $din[1]);
            $query->bindParam(':param2', $din[3]);
            $query->bindParam(':param3', $din[4]);
            $query->execute();
            echo "✅ Inserted into DB\n";
        }
    }

    $dbh = null;

} catch(PDOException $e) {
    echo '❌ Exception: ' .$e->getMessage()."\n";
}
