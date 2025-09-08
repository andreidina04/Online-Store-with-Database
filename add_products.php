<?php
session_start();
include("conectare.php");

$message = '';
$message_type = '';
$delete_message = '';
$delete_message_type = '';
$form_data = [
    'name' => '',
    'price' => '',
    'stock' => '',
    'description' => '',
    'category' => 'geci',
    'gender' => 'femei'
];

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['user'];
$stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['role'] !== 'admin') {
    echo "Acces interzis. Această pagină este accesibilă doar administratorilor. Redirecționare...";
    header("Refresh: 3; URL=index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_product'])) {
        $form_data['name'] = trim($_POST['name'] ?? '');
        $form_data['price'] = trim($_POST['price'] ?? '');
        $form_data['stock'] = trim($_POST['stock'] ?? '');
        $form_data['description'] = trim($_POST['description'] ?? '');
        $form_data['category'] = $_POST['category'] ?? 'geci';
        $form_data['gender'] = $_POST['gender'] ?? 'femei';
        $is_active = isset($_POST['is_active']) ? 1 : 0; // Added is_active checkbox

        if (empty($form_data['name']) || empty($form_data['price']) || empty($form_data['stock']) || empty($form_data['description'])) {
            $message = "Toate câmpurile (Nume, Preț, Stoc, Descriere) sunt obligatorii!";
            $message_type = 'error';
        } elseif (!is_numeric($form_data['price']) || $form_data['price'] <= 0) {
            $message = "Prețul trebuie să fie un număr pozitiv.";
            $message_type = 'error';
        } elseif (!filter_var($form_data['stock'], FILTER_VALIDATE_INT) || $form_data['stock'] < 0) {
            $message = "Stocul trebuie să fie un număr întreg non-negativ.";
            $message_type = 'error';
        } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $message = "Vă rugăm să selectați o imagine validă.";
            $message_type = 'error';
        } else {
            $image_name = basename($_FILES['image']['name']);
            $image_tmp_name = $_FILES['image']['tmp_name'];
            $image_folder = 'uploads/' . $image_name;

            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
            if (!in_array($image_ext, $allowed_types)) {
                $message = "Tip de fișier nepermis. Vă rugăm să încărcați o imagine JPG, JPEG, PNG sau GIF.";
                $message_type = 'error';
            } elseif (file_exists($image_folder)) {
                $message = "O imagine cu acest nume există deja. Vă rugăm să redenumiți imaginea sau să alegeți alta.";
                $message_type = 'error';
            } elseif (!move_uploaded_file($image_tmp_name, $image_folder)) {
                $message = "Eroare la încărcarea imaginii. Verificați permisiunile directorului 'uploads'.";
                $message_type = 'error';
            } else {
                $stmt_check_product = $conn->prepare("SELECT id FROM products WHERE name = ?");
                $stmt_check_product->bind_param("s", $form_data['name']);
                $stmt_check_product->execute();
                $result_check_product = $stmt_check_product->get_result();

                if ($result_check_product->num_rows > 0) {
                    $message = "Un produs cu acest nume există deja în baza de date!";
                    $message_type = 'error';
                    unlink($image_folder);
                } else {
                    $stmt_insert_product = $conn->prepare("INSERT INTO products (name, price, stock, description, category, gender, is_active, image_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert_product->bind_param("sdssssis", $form_data['name'], $form_data['price'], $form_data['stock'], $form_data['description'], $form_data['category'], $form_data['gender'], $is_active, $image_name);

                    if ($stmt_insert_product->execute()) {
                        $message = "Produsul a fost adăugat cu succes!";
                        $message_type = 'success';
                        $form_data = [
                            'name' => '', 'price' => '', 'stock' => '', 'description' => '',
                            'category' => 'geci', 'gender' => 'femei'
                        ];
                    } else {
                        $message = "Eroare la adăugarea produsului: " . $stmt_insert_product->error;
                        $message_type = 'error';
                        unlink($image_folder);
                    }
                    $stmt_insert_product->close();
                }
                $stmt_check_product->close();
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $product_id_to_delete = (int)$_POST['product_id_to_delete'];

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id_to_delete);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $delete_message = "Produsul a fost șters cu succes!";
                    $delete_message_type = "success";
                } else {
                    $delete_message = "Produsul nu a fost găsit sau nu a putut fi șters.";
                    $delete_message_type = "error";
                }
            } else {
                if ($stmt->errno == 1451 || $stmt->errno == 1644) {
                    $delete_message = "Eroare: Produsul nu poate fi șters deoarece există comenzi care îl conțin.";
                    $delete_message_type = "error";
                } else {
                    $delete_message = "Eroare la ștergerea produsului: " . $stmt->error;
                    $delete_message_type = "error";
                }
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1644) {
                $delete_message = $e->getMessage();
                $delete_message_type = "error";
            } else {
                $delete_message = "Eroare neașteptată la ștergere: " . $e->getMessage();
                $delete_message_type = "error";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adaugă Produs - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>Magazin de Haine - Admin</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Acasă</a></li>
                <li><a href="produse.php">Produse Clienți</a></li>
                <li><a href="add_products.php">Administrare Produse</a></li>
                <li><a href="admin_orders.php">Administrare Comenzi</a></li> <li><a href="logout.php">Deconectare</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Adaugă un nou produs</h2>
        <?php if ($message): ?>
            <p class="<?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="post" action="add_products.php" enctype="multipart/form-data">
            <input type="hidden" name="add_product" value="1">
            <label for="name">Nume:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($form_data['name']) ?>" required>

            <label for="price">Preț:</label>
            <input type="text" id="price" name="price" value="<?= htmlspecialchars($form_data['price']) ?>" required>

            <label for="stock">Stoc:</label>
            <input type="number" id="stock" name="stock" value="<?= htmlspecialchars($form_data['stock']) ?>" min="0" required>

            <label for="description">Descriere:</label>
            <textarea id="description" name="description" required><?= htmlspecialchars($form_data['description']) ?></textarea>

            <label for="category">Categorie:</label>
            <select id="category" name="category" required>
                <option value="geci" <?= ($form_data['category'] == 'geci') ? 'selected' : '' ?>>Geci</option>
                <option value="hanorace" <?= ($form_data['category'] == 'hanorace') ? 'selected' : '' ?>>Hanorace</option>
                <option value="bluze" <?= ($form_data['category'] == 'bluze') ? 'selected' : '' ?>>Bluze</option>
                <option value="tricouri" <?= ($form_data['category'] == 'tricouri') ? 'selected' : '' ?>>Tricouri</option>
                <option value="pantaloni" <?= ($form_data['category'] == 'pantaloni') ? 'selected' : '' ?>>Pantaloni</option>
            </select>

            <label for="gender">Gen:</label>
            <select id="gender" name="gender" required>
                <option value="femei" <?= ($form_data['gender'] == 'femei') ? 'selected' : '' ?>>Femei</option>
                <option value="barbati" <?= ($form_data['gender'] == 'barbati') ? 'selected' : '' ?>>Bărbați</option>
                <option value="unisex" <?= ($form_data['gender'] == 'unisex') ? 'selected' : '' ?>>Unisex</option>
            </select>

            <label for="is_active">Activ:</label>
            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
            <br><br>

            <label for="image">Imagine:</label>
            <input type="file" id="image" name="image" required>

            <button type="submit">Adaugă Produs</button>
        </form>

        <hr style="margin: 40px 0;">

        <h2>Produse Existente (Ștergere)</h2>
        <?php if (!empty($delete_message)): ?>
            <p class="<?= htmlspecialchars($delete_message_type) ?>"><?= htmlspecialchars($delete_message) ?></p>
        <?php endif; ?>

        <div class="products-admin">
            <?php
            $stmt_products = $conn->prepare("SELECT id, name, price, stock, image_name FROM products ORDER BY name ASC");
            $stmt_products->execute();
            $result_products = $stmt_products->get_result();

            if ($result_products->num_rows > 0):
                while($product = $result_products->fetch_assoc()):
            ?>
                    <div class="product-admin-item">
                        <div class="product-info">
                            <?php if (!empty($product['image_name'])): ?>
                                <img src="uploads/<?= htmlspecialchars($product['image_name']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="80">
                            <?php else: ?>
                                <img src="placeholder.png" alt="No Image" width="80">
                            <?php endif; ?>
                            <div>
                                <h4><?= htmlspecialchars($product['name']) ?></h4>
                                <p>Preț: <?= number_format($product['price'], 2) ?> RON | Stoc: <?= $product['stock'] ?></p>
                            </div>
                        </div>
                        <div class="product-actions">
                            <form method="post" style="display: inline-block;">
                                <input type="hidden" name="product_id_to_delete" value="<?= $product['id'] ?>">
                                <button type="submit" name="delete_product" onclick="return confirm('Ești sigur că vrei să ștergi produsul &quot;<?= htmlspecialchars($product['name']) ?>&quot;? Această acțiune este ireversibilă dacă produsul nu are comenzi asociate.');" class="delete-btn">Șterge</button>
                            </form>
                        </div>
                    </div>
            <?php
                endwhile;
            else:
            ?>
                    <p>Nu sunt produse în baza de date.</p>
            <?php
            endif;
            $stmt_products->close();
            ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>