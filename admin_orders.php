
<?php
session_start();
include("conectare.php");

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_order'])) {
    $order_id_to_cancel = (int)$_POST['order_id_to_cancel'];

    $conn->begin_transaction();

    try {
        $stmt_details = $conn->prepare("SELECT product_id, quantity FROM order_details WHERE order_id = ?");
        $stmt_details->bind_param("i", $order_id_to_cancel);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();

        while ($detail = $result_details->fetch_assoc()) {
        }
        $stmt_details->close();

        $stmt_delete_details = $conn->prepare("DELETE FROM order_details WHERE order_id = ?");
        $stmt_delete_details->bind_param("i", $order_id_to_cancel);
        $stmt_delete_details->execute();
        $stmt_delete_details->close();

        $stmt_delete_order = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt_delete_order->bind_param("i", $order_id_to_cancel);
        $stmt_delete_order->execute();
        $stmt_delete_order->close();

        $conn->commit();
        $cancel_message = "Comanda #$order_id_to_cancel a fost anulată cu succes.";
    } catch (Exception $e) {
        $conn->rollback();
        $cancel_error = "Eroare la anularea comenzii: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrare Comenzi</title>
    <style>
        .order-admin-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 20px;
        }
        .order-admin-item h3 {
            margin-top: 0;
        }
        .order-actions-admin {
            margin-top: 10px;
        }
        .cancel-btn {
            background-color: #f44336;
            color: white;
            padding: 8px 12px;
            border: none;
            cursor: pointer;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1>Panou de Administrare</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Acasă</a></li>
                <li><a href="add_products.php">Adaugă Produse</a></li>
                <li><a href="admin_orders.php">Administrare Comenzi</a></li>
                <li><a href="logout.php">Deconectare</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h2>Administrare Comenzi</h2>

        <?php if (isset($cancel_message)): ?>
            <p class="success"><?= $cancel_message ?></p>
        <?php endif; ?>
        <?php if (isset($cancel_error)): ?>
            <p class="error"><?= $cancel_error ?></p>
        <?php endif; ?>

        <?php
        $stmt_orders = $conn->prepare("
            SELECT
                o.id AS order_id,
                o.user_id,
                u.username AS customer_username,
                o.order_date,
                o.total AS order_total,
                o.address,
                o.phone_number,
                o.firstname,
                o.lastname
            FROM orders o
            JOIN users u ON o.user_id = u.id
            ORDER BY o.order_date DESC
        ");

        if ($stmt_orders === false) {
            echo "<p class='error'>Eroare critică la pregătirea interogării comenzilor: " . $conn->error . "</p>";
        } else {
            $stmt_orders->execute();
            $result_orders = $stmt_orders->get_result();

            if ($result_orders->num_rows > 0):
                while($order = $result_orders->fetch_assoc()):
        ?>
                        <div class="order-admin-item">
                            <h3>Comanda #<?= htmlspecialchars($order['order_id']) ?></h3>
                            <p><strong>Client:</strong> <?= htmlspecialchars($order['firstname'] . ' ' . $order['lastname']) ?> (Utilizator: <?= htmlspecialchars($order['customer_username']) ?>)</p>
                            <p><strong>Telefon:</strong> <?= htmlspecialchars($order['phone_number'] ?? 'N/A') ?></p>
                            <p><strong>Adresă Livrare:</strong> <?= htmlspecialchars($order['address'] ?? 'N/A') ?></p>
                            <p><strong>Data Comenzi:</strong> <?= htmlspecialchars($order['order_date']) ?></p>
                            <p><strong>Total Comandă:</strong> <?= number_format($order['order_total'], 2) ?> RON</p>
                            <?php if (!empty($order['notes'])): ?>
                                <p><strong>Note:</strong> <?= htmlspecialchars($order['notes']) ?></p>
                            <?php endif; ?>

                            <h4>Produse comandate:</h4>
                            <?php
                            $stmt_details = $conn->prepare("
                                SELECT
                                    od.product_id,
                                    p.name AS product_name,
                                    od.quantity,
                                    od.price
                                FROM order_details od
                                JOIN products p ON od.product_id = p.id
                                WHERE od.order_id = ?
                            ");
                            $stmt_details->bind_param("i", $order['order_id']);
                            $stmt_details->execute();
                            $result_details = $stmt_details->get_result();

                            if ($result_details->num_rows > 0):
                                while ($detail = $result_details->fetch_assoc()):
                            ?>
                                        <p>
                                            <?= htmlspecialchars($detail['product_name']) ?> -
                                            Cantitate: <?= htmlspecialchars($detail['quantity']) ?> -
                                            Preț unitar: <?= number_format($detail['price'], 2) ?> RON -
                                            Subtotal: <?= number_format($detail['quantity'] * $detail['price'], 2) ?> RON
                                        </p>
                            <?php
                                endwhile;
                            else:
                            ?>
                                    <p>Nu există produse comandate.</p>
                            <?php
                            endif;
                            $stmt_details->close();
                            ?>
                            
                            <div class="order-actions-admin">
                                <form method="post" onsubmit="return confirm('Ești sigur că vrei să anulezi comanda #<?= htmlspecialchars($order['order_id']) ?>? Această acțiune va restaura stocul și va șterge comanda.');">
                                    <input type="hidden" name="order_id_to_cancel" value="<?= htmlspecialchars($order['order_id']) ?>">
                                    <button type="submit" name="cancel_order" class="cancel-btn">Anulează Comanda</button>
                                </form>
                            </div>
                        </div>
        <?php
                endwhile;
            else:
        ?>
                <p>Nu există comenzi plasate.</p>
        <?php
            endif;
            $stmt_orders->close();
        } 
        ?>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>
```