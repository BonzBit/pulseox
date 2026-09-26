# pulseox

Pulse oximetry monitor running on a Raspberry Pi: a PHP serial reader (`poxs.php`) logs SPO2/BPM from a Prolific USB adapter into SQLite, and Apache serves `www/gpulse2.php`.

This repository is a backup of the running source. Live data stays in `/var/www/html/pulseox.db` on the device and is not stored here.
