<?php
include("conectare.php");

function display_cart() {
    $total = 0;
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $index => $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
            echo "<p>{$item['name']} - Cantitate: {$item['quantity']} - Preț: {$item['price']} RON - Subtotal: $subtotal RON";
            echo " <form method='post' style='display:inline;' action='cos.php'><input type='hidden' name='remove_index' value='$index'><input type='submit' name='remove_item' value='Elimină'></form></p>";
        }
        echo "<p><strong>Total: $total RON</strong></p>";
        echo "<form method='post' action='cos.php'>";
        echo "<input type='submit' name='clear_cart' value='Golește Coșul'>";
        echo "</form>";
        echo "<form method='post' action='cos.php'>";
        echo "<input type='submit' name='checkout' value='Trimite Comanda'>";
        echo "</form>";
    } else {
        echo "<p>Coșul tău este gol.</p>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['form_sent']) || $_SESSION['form_sent'] !== true) {
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];

        $sql = "SELECT * FROM products WHERE id='$product_id'";
        $result = $conn->query($sql);
        $product = $result->fetch_assoc();

        if ($product) {
            $item = array(
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity
            );

            if (isset($_SESSION['cart'])) {
                $cart = $_SESSION['cart'];
                $found = false;

                foreach ($cart as &$cart_item) {
                    if ($cart_item['id'] == $product_id) {
                        $cart_item['quantity'] += $quantity;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $cart[] = $item;
                }

                $_SESSION['cart'] = $cart;
            } else {
                $_SESSION['cart'] = array($item);
            }
        }
        $_SESSION['form_sent'] = true;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_item'])) {
    $index = $_POST['remove_index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindexare
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    echo "<p>Comanda a fost trimisă cu succes!</p>";
    unset($_SESSION['cart']);
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $_SESSION['form_sent'] = false;
}
?>
