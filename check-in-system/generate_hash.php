<?php
// This will generate a new hash for password 'admin123'
$hash = password_hash('admin123', PASSWORD_BCRYPT);
// Only copy the hash string below:
echo "\nNEW PASSWORD HASH (copy everything after this line):\n";
echo $hash;
?>
