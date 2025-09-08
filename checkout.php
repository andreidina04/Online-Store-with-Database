<?php
session_start();
include("conectare.php");  // asigură-te că aici ai conexiunea $conn

// Datele venite din formular (verifică să fie trimise corect)
$firstname = $_POST['firstname'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$address = $_POST['address'] ?? '';
$total = 0;

// Calculează totalul din coș (asumând structura $_SESSION['cart'])
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} else {
    echo "Coșul este gol.";
    exit;
}

// Începe tranzacția
$conn->begin_transaction();

try {
    // Pregătește interogarea pentru inserare în orders
    $stmt = $conn->prepare("INSERT INTO orders (firstname, lastname, address, total) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Eroare pregătire comandă: " . $conn->error);
    }

    $stmt->bind_param("sssd", $firstname, $lastname, $address, $total);
    $stmt->execute();

    $order_id = $conn->insert_id; // id-ul comenzii adăugate

    // Pregătește interogarea pentru detaliile comenzii
    $stmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Eroare pregătire detalii comandă: " . $conn->error);
    }

    foreach ($_SESSION['cart'] as $item) {
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price = $item['price'];

        $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
        $stmt->execute();
    }

    // Confirmă tranzacția
    $conn->commit();

    // Golește coșul după plasarea comenzii
    unset($_SESSION['cart']);

    echo "Comanda a fost plasată cu succes!";

} catch (Exception $e) {
    $conn->rollback();
    echo "Eroare la plasarea comenzii: " . $e->getMessage();
}
?>
