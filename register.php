<?php
session_start();
include("conectare.php");

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $email = $conn->real_escape_string($_POST['email']);
    $role = 'user';

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password_hashed, $email, $role);

        if ($stmt->execute()) {
            $message = "Înregistrare reușită! Acum te poți autentifica.";
            $message_type = "success";
        } else {
            $message = "Eroare la înregistrare: " . $stmt->error;
            $message_type = "error";
        }
    } else {
        $message = "Utilizatorul există deja! Te rugăm să alegi un alt nume de utilizator.";
        $message_type = "error";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Înregistrare Utilizator - Magazin Haine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>Înregistrare Cont</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Acasă</a></li>
                <li><a href="produse.php">Produse</a></li>
                <li><a href="register.php">Înregistrare</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div class="register-form">
            <h2>Creează un cont nou</h2>
            <?php if (!empty($message)): ?>
                <p class="<?= $message_type ?>"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>
            <form method="post" action="register.php">
                <label for="username">Nume Utilizator:</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Parolă:</label>
                <input type="password" id="password" name="password" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <button type="submit" name="register">Înregistrare</button>
            </form>
            <p>Ai deja un cont? <a href="index.php">Autentifică-te aici</a></p>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>