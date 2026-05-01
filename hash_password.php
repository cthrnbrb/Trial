<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get the couple user
$user = App\Models\User::where('role', 'couple')->first();

if ($user) {
    echo "Found couple user: {$user->email}\n";
    echo "Current password (hashed): {$user->password}\n";
    
    // Hash a new password
    $newPassword = 'your_password_here'; // Change this to the desired password
    $hashedPassword = Illuminate\Support\Facades\Hash::make($newPassword);
    
    $user->password = $hashedPassword;
    $user->save();
    
    echo "Password updated successfully!\n";
    echo "New hashed password: {$hashedPassword}\n";
    echo "You can now login with: {$newPassword}\n";
} else {
    echo "No couple user found in the database.\n";
}
