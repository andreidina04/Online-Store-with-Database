<?php
session_start();
include("conectare.php");

$login_error = '';
$cart_error = '';
$checkout_error = '';
$checkout_success = '';
$coupon_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role']; 
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Parolă incorectă!";
        }
    } else {
        $login_error = "Utilizatorul nu există!";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user'])) {
        $cart_error = "Trebuie să fii autentificat pentru a adăuga produse în coș!";
    } else {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];

        if ($quantity <= 0) {
            $cart_error = "Cantitatea trebuie să fie cel puțin 1.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            if ($product) {
                $current_cart_quantity = 0;
                if (isset($_SESSION['cart'])) {
                    // Caută produsul în coș pentru a vedea cantitatea deja existentă
                    foreach ($_SESSION['cart'] as $cart_item) {
                        if ($cart_item['id'] == $product_id) {
                            $current_cart_quantity = $cart_item['quantity'];
                            break;
                        }
                    }
                }

                if (($current_cart_quantity + $quantity) > $product['stock']) {
                    $cart_error = "Nu există suficient stoc pentru " . htmlspecialchars($product['name']) . ". Stoc disponibil: " . $product['stock'] . ". Ai deja " . $current_cart_quantity . " în coș.";
                } else {
                    $item = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity
                    ];

                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    $found = false;
                    foreach ($_SESSION['cart'] as &$cart_item) {
                        if ($cart_item['id'] == $product_id) {
                            $cart_item['quantity'] += $quantity;
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $_SESSION['cart'][] = $item;
                    }
                }
            } else {
                $cart_error = "Produsul nu a fost găsit!";
            }
            $stmt->close();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_item'])) {
    $index = (int)$_POST['remove_index'];
    if (isset($_SESSION['cart'][$index])) {
        if ($_SESSION['cart'][$index]['quantity'] > 1) {
            $_SESSION['cart'][$index]['quantity'] -= 1;
        } else {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-indexează array-ul după ștergere
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    unset($_SESSION['discount']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    if (!isset($_SESSION['user'])) {
        $checkout_error = "Trebuie să fii autentificat pentru a finaliza comanda!";
    } elseif (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        $checkout_error = "Coșul de cumpărături este gol!";
    } else {
        $firstname = trim($conn->real_escape_string($_POST['firstname']));
        $lastname = trim($conn->real_escape_string($_POST['lastname']));
        // AICI ESTE CÂMPUL PENTRU ADRESĂ
        $address = trim($conn->real_escape_string($_POST['address'])); 
        // AICI ESTE CÂMPUL PENTRU NUMĂR DE TELEFON
        $phone_number = trim($conn->real_escape_string($_POST['phone_number']));
        $email = trim($conn->real_escape_string($_POST['email']));
        $payment_method = trim($conn->real_escape_string($_POST['payment_method']));

        $user_id = $_SESSION['user_id'];

        if (empty($firstname) || empty($lastname) || empty($address) || empty($phone_number) || empty($email) || empty($payment_method)) {
            $checkout_error = "Toate câmpurile (Nume, Prenume, Adresă, Telefon, Email, Metodă de plată) sunt obligatorii!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $checkout_error = "Adresa de email nu este validă!";
        } elseif (!preg_match("/^[0-9\s\+\-\(\)]{7,20}$/", $phone_number)) { 
            // Validare număr de telefon: 7-20 caractere, poate conține cifre, spații, +, -, ( )
            $checkout_error = "Numărul de telefon este invalid. Acesta trebuie să conțină între 7 și 20 de caractere, format din cifre, spații, '+', '-', '('.";
        } else {
            $conn->begin_transaction();
            try {
                $products_in_cart_details = [];
                foreach ($_SESSION['cart'] as $item) {
                    $stmt_stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
                    if ($stmt_stock_check === false) {
                        throw new Exception("Eroare la pregătirea verificării stocului: " . $conn->error);
                    }
                    $stmt_stock_check->bind_param("i", $item['id']);
                    $stmt_stock_check->execute();
                    $result_stock = $stmt_stock_check->get_result();
                    $product_db = $result_stock->fetch_assoc();

                    if (!$product_db || $product_db['stock'] < $item['quantity']) {
                        throw new Exception("Stoc insuficient pentru produsul " . htmlspecialchars($item['name']) . ". Stoc disponibil: " . ($product_db ? $product_db['stock'] : '0'));
                    }
                    $products_in_cart_details[$item['id']] = $product_db['stock'];
                    $stmt_stock_check->close();
                }

                $total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $total += $item['price'] * $item['quantity'];
                }

                if (isset($_SESSION['discount'])) {
                    $total = $total * (1 - $_SESSION['discount'] / 100);
                }

                // AICI ASIGURĂ-TE CĂ SE POTRIVESC CU NUMELE COLOANELOR DIN DB
                // 'address' și 'phone_number' sunt incluse în interogare
                $stmt_order = $conn->prepare("INSERT INTO orders (user_id, firstname, lastname, address, phone_number, email, payment_method, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt_order === false) {
                    throw new Exception("Eroare la pregătirea interogării pentru comandă: " . $conn->error);
                }
                // Stringul de tipuri: i = int, s = string, d = double
                // idsss s d -> user_id (i), firstname (s), lastname (s), address (s), phone_number (s), email (s), payment_method (s), total (d)
                $stmt_order->bind_param("issssssd", $user_id, $firstname, $lastname, $address, $phone_number, $email, $payment_method, $total);
                
                if (!$stmt_order->execute()) {
                    throw new Exception("Eroare la executarea interogării pentru comandă: " . $stmt_order->error);
                }
                $order_id = $conn->insert_id;
                $stmt_order->close();

                $stmt_details = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                if ($stmt_details === false) {
                    throw new Exception("Eroare la pregătirea interogării detaliilor comenzii: " . $conn->error);
                }

                // AM COMENTAT BLOCUL DE COD DE MAI JOS, DEOARECE TRIGGERUL DIN BAZA DE DATE
                // FACE DEJA ACTUALIZAREA STOCULUI LA INSERAREA IN order_details.
                // Lăsând acest cod activ, stocul se reducea de două ori.
                /*
                $stmt_update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                if ($stmt_update_stock === false) {
                    throw new Exception("Eroare la pregătirea actualizării stocului: " . $conn->error);
                }
                */

                foreach ($_SESSION['cart'] as $item) {
                    $stmt_details->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
                    if (!$stmt_details->execute()) {
                        throw new Exception("Eroare la executarea interogării pentru detalii comandă (produs " . $item['name'] . "): " . $stmt_details->error);
                    }

                    // AICI ERA APELUL PENTRU ACTUALIZAREA STOCULUI, CARE A FOST COMENTAT.
                    // Acum, triggerul din baza de date va face această operațiune automat.
                    /*
                    $stmt_update_stock->bind_param("ii", $item['quantity'], $item['id']);
                    if (!$stmt_update_stock->execute()) {
                        throw new Exception("Eroare la actualizarea stocului pentru produsul " . $item['name'] . ": " . $stmt_update_stock->error);
                    }
                    */
                }
                $stmt_details->close();
                // $stmt_update_stock->close(); // A nu se uita să închizi statement-ul dacă ar fi fost folosit

                $conn->commit();
                $checkout_success = "Comanda a fost trimisă cu succes! Număr comandă: " . $order_id;
                unset($_SESSION['cart']);
                unset($_SESSION['discount']);
            } catch (Exception $e) {
                $conn->rollback();
                $checkout_error = "Eroare la plasarea comenzii: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_coupon'])) {
    $coupon_code = $conn->real_escape_string($_POST['coupon_code']);
    
    $stmt = $conn->prepare("SELECT discount_percentage FROM coupons WHERE code = ?");
    if ($stmt === false) {
        $coupon_message = "Eroare la pregătirea interogării cuponului: " . $conn->error;
    } else {
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $coupon = $result->fetch_assoc();
            $_SESSION['discount'] = $coupon['discount_percentage'];
            $coupon_message = "Cupon aplicat! Reducere de {$_SESSION['discount']}%.";
        } else {
            $coupon_message = "Cod cupon invalid!";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magazin de Haine</title>
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
                    <li><a href="my_orders.php">Comenzile Mele</a></li>
                    <li><a href="logout.php">Deconectare</a></li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="add_products.php">ADMIN</a></li>
                        <li><a href="admin_orders.php">Comenzi Admin</a></li> <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="cart">
        <h3>Coșul de cumpărături</h3>
        <?php if (isset($cart_error) && $cart_error): ?>
            <p class="error"><?= $cart_error ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
            <?php 
            $total_cart_display = 0;
            foreach ($_SESSION['cart'] as $index => $item): 
                $subtotal = $item['price'] * $item['quantity'];
                $total_cart_display += $subtotal;
            ?>
                <div class="cart-item">
                    <p>
                        <?= htmlspecialchars($item['name']) ?> - 
                        Cantitate: <?= $item['quantity'] ?> - 
                        Preț: <?= number_format($item['price'], 2) ?> RON - 
                        Subtotal: <?= number_format($subtotal, 2) ?> RON
                    </p>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="remove_index" value="<?= $index ?>">
                        <button type="submit" name="remove_item">Elimină 1</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php 
            $final_display_total = $total_cart_display;
            if (isset($_SESSION['discount'])): 
            ?>
                <p>Reducere: <?= $_SESSION['discount'] ?>%</p>
                <p>Total inițial: <?= number_format($total_cart_display, 2) ?> RON</p>
                <?php $final_display_total = $total_cart_display * (1 - $_SESSION['discount'] / 100); ?>
            <?php endif; ?>

            <p><strong>Total de plată: <?= number_format($final_display_total, 2) ?> RON</strong></p>

            <form method="post">
                <button type="submit" name="clear_cart">Golește Coșul</button>
            </form>

            <form method="post">
                <h4>Finalizează comanda</h4>
                <input type="text" name="firstname" placeholder="Nume" required value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                <input type="text" name="lastname" placeholder="Prenume" required value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                <textarea name="address" placeholder="Adresă completă de livrare" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                <input type="text" name="phone_number" placeholder="Număr de telefon" pattern="[0-9\s\+\-\(\)]{7,20}" title="Număr de telefon valid (7-20 caractere, poate conține cifre, spații, +, -, ( ) )" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                
                <label for="payment_method">Metodă de plată:</label>
                <select id="payment_method" name="payment_method" required>
                    <option value="ramburs" selected>Plată la livrare (ramburs)</option>
                    </select>

                <input type="text" name="coupon_code" placeholder="Cod cupon">
                <button type="submit" name="apply_coupon">Aplică cupon</button>
                
                <button type="submit" name="checkout">Trimite Comanda</button>
            </form>

            <?php if (isset($coupon_message) && $coupon_message): ?>
                <p class="<?= strpos($coupon_message, 'invalid') !== false ? 'error' : 'success' ?>">
                    <?= $coupon_message ?>
                </p>
            <?php endif; ?>

            <?php if (isset($checkout_error) && $checkout_error): ?>
                <p class="error"><?= $checkout_error ?></p>
            <?php elseif (isset($checkout_success) && $checkout_success): ?>
                <p class="success"><?= $checkout_success ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p>Coșul tău este gol.</p>
        <?php endif; ?>
    </div>

    <div class="container">
        <h2>Bine ați venit!</h2>
        
        <?php if (!isset($_SESSION['user'])): ?>
            <div class="login-form">
                <h3>Autentificare</h3>
                <?php if (isset($login_error) && $login_error): ?>
                    <p class="error"><?= $login_error ?></p>
                <?php endif; ?>
                <form method="post">
                    <input type="text" name="username" placeholder="Nume utilizator" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <input type="password" name="password" placeholder="Parolă" required>
                    <button type="submit" name="login">Autentificare</button>
                </form>
            </div>
        <?php else: ?>
            <p>Bun venit, <?= htmlspecialchars($_SESSION['user']) ?>!</p>
        <?php endif; ?>

        <h3>Produse disponibile</h3>
        <div class="products">
            <?php
            $stmt_products = $conn->prepare("SELECT id, name, price, stock, description, image_name FROM products ORDER BY name ASC");
            $stmt_products->execute();
            $result_products = $stmt_products->get_result();

            if ($result_products->num_rows > 0):
                while($product = $result_products->fetch_assoc()):
            ?>
                        <div class="product">
                            <h4><?= htmlspecialchars($product['name']) ?></h4>
                            <?php if (!empty($product['image_name'])): ?>
                                <img src="uploads/<?= htmlspecialchars($product['image_name']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="200">
                            <?php else: ?>
                                <img src="placeholder.png" alt="No Image" width="200">
                            <?php endif; ?>
                            <p>Preț: <?= number_format($product['price'], 2) ?> RON</p>
                            <p>Stoc: <span style="font-weight: bold; color: <?= $product['stock'] > 0 ? 'green' : 'red'; ?>;"><?= $product['stock'] ?></span></p>
                            <p><?= htmlspecialchars($product['description']) ?></p>
                            
                            <?php if (isset($_SESSION['user'])): ?>
                                <?php if ($product['stock'] > 0): ?>
                                <form method="post">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                                    <button type="submit" name="add_to_cart">Adaugă în coș</button>
                                </form>
                                <?php else: ?>
                                    <p style="color: red; font-weight: bold;">Stoc epuizat!</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p><a href="index.php">Autentifică-te pentru a cumpăra</a></p>
                            <?php endif; ?>
                        </div>
            <?php
                endwhile;
            else:
            ?>
                    <p>Nu sunt produse disponibile momentan.</p>
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