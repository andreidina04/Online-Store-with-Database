<?php
session_start();
include("conectare.php");

$search_query = "";
$filter_category = "";
$filter_gender = "";
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search_query = $_GET['search'];
    }
    if (isset($_GET['category'])) {
        $filter_category = $_GET['category'];
    }
    if (isset($_GET['gender'])) {
        $filter_gender = $_GET['gender'];
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Produse</title>
    <link rel="stylesheet" href="style.css">
    <style>
        h1, h2, h3 {
            color: black;
            text-align: center;
            margin-bottom: 20px;
        }

        .filter-form {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-form label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }

        .filter-form input[type="text"],
        .filter-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            min-width: 180px;
        }

        .filter-form input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }

        .filter-form input[type="submit"]:hover {
            background-color: #45a049;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            padding: 20px 0;
        }

        .product-item {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .product-item h2 {
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }

        .product-item img {
            max-width: 100%;
            height: 200px;
            object-fit: contain;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .product-item p {
            color: #555;
            margin-bottom: 10px;
            flex-grow: 1;
        }
        .product-item .price {
            font-size: 20px;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 15px;
        }

        .product-item form {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .product-item input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
        }

        .product-item input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .product-item input[type="submit"]:hover {
            background-color: #0056b3;
        }

        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form input[type="text"],
            .filter-form select,
            .filter-form input[type="submit"] {
                width: 100%;
            }
            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
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
        <h2>Produsele noastre</h2>

        <div class="filter-form">
            <form method="get" action="produse.php">
                <div>
                    <label for="search">Căutare produse:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div>
                    <label for="category">Filtrează după categorie:</label>
                    <select id="category" name="category">
                        <option value="">Toate</option>
                        <option value="geci" <?php if ($filter_category == 'geci') echo 'selected'; ?>>Geci</option>
                        <option value="hanorace" <?php if ($filter_category == 'hanorace') echo 'selected'; ?>>Hanorace</option>
                        <option value="bluze" <?php if ($filter_category == 'bluze') echo 'selected'; ?>>Bluze</option>
                        <option value="tricouri" <?php if ($filter_category == 'tricouri') echo 'selected'; ?>>Tricouri</option>
                        <option value="pantaloni" <?php if ($filter_category == 'pantaloni') echo 'selected'; ?>>Pantaloni</option>
                    </select>
                </div>
                <div>
                    <label for="gender">Filtrează după gen:</label>
                    <select id="gender" name="gender">
                        <option value="">Toate</option>
                        <option value="femei" <?php if ($filter_gender == 'femei') echo 'selected'; ?>>Femei</option>
                        <option value="barbati" <?php if ($filter_gender == 'barbati') echo 'selected'; ?>>Bărbați</option>
                    </select>
                </div>
                <div>
                    <input type="submit" value="Caută">
                </div>
            </form>
        </div>

        <div class="product-grid">
            <?php
            $sql = "SELECT * FROM products WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($search_query)) {
                $sql .= " AND name LIKE ?";
                $params[] = '%' . $search_query . '%';
                $types .= "s";
            }
            if (!empty($filter_category)) {
                $sql .= " AND category = ?";
                $params[] = $filter_category;
                $types .= "s";
            }
            if (!empty($filter_gender)) {
                $sql .= " AND gender = ?";
                $params[] = $filter_gender;
                $types .= "s";
            }

            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                echo "Eroare la pregătirea interogării: " . $conn->error;
            } else {
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<div class='product-item'>";
                        echo "<h2>" . htmlspecialchars($row["name"]) . "</h2>";

                        if (isset($row['image_name']) && !empty($row['image_name'])) {
                            echo "<img src='uploads/" . htmlspecialchars($row["image_name"]) . "' alt='" . htmlspecialchars($row["name"]) . "'>";
                        } else {
                            echo "<img src='path/to/placeholder.png' alt='No Image Available'>";
                        }
                        
                        echo "<p>" . htmlspecialchars($row["description"]) . "</p>";
                        echo "<p class='price'>" . number_format($row["price"], 2) . " RON</p>";

                        if (isset($_SESSION['user'])) {
                            echo "<form method='post' action='index.php'>";
                            echo "<input type='hidden' name='product_id' value='" . htmlspecialchars($row["id"]) . "'>";
                            echo "<label for='quantity_" . htmlspecialchars($row["id"]) . "'>Cantitate:</label>";
                            echo "<input type='number' id='quantity_" . htmlspecialchars($row["id"]) . "' name='quantity' value='1' min='1' max='" . htmlspecialchars($row['stock']) . "'><br>";
                            echo "<input type='submit' name='add_to_cart' value='Adaugă în coș'>";
                            echo "</form>";
                        } else {
                            echo "<p><a href='login.php'>Autentifică-te pentru a adăuga în coș</a></p>";
                        }
                        
                        echo "</div>";
                    }
                } else {
                    echo "<p>Nu au fost găsite produse care să corespundă criteriilor de căutare.</p>";
                }
                $stmt->close();
            }
            ?>
        </div>
        
        <div style="height: 50px;"></div>
    </div>

    <footer>
        <p>&copy; 2025 Magazin de Haine</p>
    </footer>
</body>
</html>