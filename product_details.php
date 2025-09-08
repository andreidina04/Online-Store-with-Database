<?php
session_start();
include("conectare.php");

$product_id = $_GET['id'];

// Gestionare adăugare recenzie
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_review'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "Trebuie să fii autentificat pentru a adăuga o recenzie.";
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $review_text = $_POST['review_text'];

    $sql_insert_review = "INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES ('$product_id', '$user_id', '$rating', '$review_text')";
    if ($conn->query($sql_insert_review) === TRUE) {
        echo "Recenzia a fost adăgată cu succes!";
    } else {
        echo "Eroare: " . $sql_insert_review . "<br>" . $conn->error;
    }
}

// Obținerea detaliilor produsului
$sql = "SELECT * FROM products WHERE id='$product_id'";
$result = $conn->query($sql);
$product = $result->fetch_assoc();

// Obținerea recenziilor produsului
$sql_reviews = "SELECT r.rating, r.review_text, r.review_date, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id='$product_id'";
$result_reviews = $conn->query($sql_reviews);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Detalii Produs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .review {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px 0;
            background: #f9f9f9;
        }
        .review form input[type="submit"] {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1>Magazin de Haine</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Acasă</a></li>
                <li><a href="produse.php">Produse</a></li>
                <?php if (!isset($_SESSION['user'])): ?>
                    <li><a href="register.php">Înregistrare</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user'])): ?>
                    <li><a href="logout.php">Deconectare</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2><?php echo $product['name']; ?></h2>
        <img src="product_images/<?php echo $product['image_name']; ?>" alt="<?php echo $product['name']; ?>" style="max-width: 200px;"><br>
        <p>Preț: <?php echo $product['price']; ?> RON</p>
        <p><?php echo $product['description']; ?></p>

        <h3>Recenzii</h3>
        <?php if ($result_reviews->num_rows > 0): ?>
            <?php while ($review = $result_reviews->fetch_assoc()): ?>
                <div class="review">
                    <p><strong><?php echo $review['username']; ?></strong> - <?php echo $review['rating']; ?>/5</p>
                    <p><?php echo $review['review_text']; ?></p>
                    <p><small><?php echo $review['review_date']; ?></small></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nu există recenzii pentru acest produs.</p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user'])): ?>
            <h3>Adaugă o recenzie</h3>
            <form method="post" action="product_details.php?id=<?php echo $product_id; ?>">
                <label for="rating">Evaluare:</label><br>
                <select id="rating" name="rating" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select><br><br>

                <label for="review_text">Recenzie:</label><br>
                <textarea id="review_text" name="review_text" required></textarea><br><br>

                <input type="submit" name="add_review" value="Adaugă recenzia">
            </form>
        <?php else: ?>
            <p><a href="login.php">Autentifică-te pentru a adăuga o recenzie</a></p>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>
