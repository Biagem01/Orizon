<?php
try {
    $pdo = new PDO("mysql:host=localhost;port=8889;dbname=orizon", "root", "root");
    echo "✅ Connessione riuscita!";
} catch (PDOException $e) {
    echo "❌ Errore di connessione: " . $e->getMessage();
}
