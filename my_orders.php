<?php
session_start();
include("conectare.php");

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user'];

$orders = [];
$stmt = $conn->prepare("SELECT id, firstname, lastname, address, total, order_date FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comenzile Mele - Magazin de Haine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>Comenzile Mele</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Acasă</a></li>
                <li><a href="produse.php">Produse</a></li>
                <li><a href="my_orders.php">Comenzile Mele</a></li>
                <li><a href="logout.php">Deconectare</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li><a href="add_products.php">ADMIN</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Comenzile Tale, <?= htmlspecialchars($username) ?></h2>

        <?php if (empty($orders)): ?>
            <p>Nu ai plasat încă nicio comandă.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-summary">
                    <h3>Comanda #<?= htmlspecialchars($order['id']) ?> (Data: <?= htmlspecialchars($order['order_date']) ?>)</h3>
                    <p>Total: <strong><?= number_format($order['total'], 2) ?> RON</strong></p>
                    <p>Adresă de livrare: <?= htmlspecialchars($order['address']) ?></p>

                    <h4>Detalii produse:</h4>
                    <ul class="order-details-list">
                        <?php
                        $order_details = [];
                        $stmt_details = $conn->prepare("SELECT p.name, od.quantity, od.price
                                                        FROM order_details od
                                                        JOIN products p ON od.product_id = p.id
                                                        WHERE od.order_id = ?");
                        $stmt_details->bind_param("i", $order['id']);
                        $stmt_details->execute();
                        $result_details = $stmt_details->get_result();
                        while ($detail = $result_details->fetch_assoc()) {
                            $order_details[] = $detail;
                        }
                        $stmt_details->close();

                        if (empty($order_details)) {
                            echo "<li>Nu există detalii pentru această comandă.</li>";
                        } else {
                            foreach ($order_details as $detail):
                        ?>
                                <li>
                                    <?= htmlspecialchars($detail['name']) ?> -
                                    Cantitate: <?= htmlspecialchars($detail['quantity']) ?> -
                                    Preț unitar: <?= number_format($detail['price'], 2) ?> RON -
                                    Subtotal: <?= number_format($detail['quantity'] * $detail['price'], 2) ?> RON
                                </li>
                        <?php
                            endforeach;
                        }
                        ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>