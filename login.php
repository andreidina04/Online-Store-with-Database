<?php
session_start();
include("conectare.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = trim($_POST['password']);

    // Debug: Afișează inputurile primite
    error_log("Încercare login cu username: $username");

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        error_log("Utilizator găsit: " . print_r($user, true));
        
        // Debug: Afiseaza parola hash-uita din baza de date
        error_log("Parolă hash din DB: " . $user['password']);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit();
        } else {
            error_log("Verificarea parolei a eșuat");
        }
    } else {
        error_log("Utilizatorul nu există");
    }
    
    // Mesaj general de eroare (pentru securitate)
    echo "Date de autentificare incorecte!";
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Autentificare</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>Autentificare</h2>
    <form method="post" action="login.php">
        <div class="form-group">
            <label for="username">Nume utilizator:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Parolă:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" name="login">Autentificare</button>
    </form>
    
    <p>Nu ai cont? <a href="register.php">Înregistrează-te aici</a></p>
</body>
</html>
