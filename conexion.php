<?php
// Acá defino los datos que necesito para conectarme a la base de datos.
$host = 'localhost';
$dbname = 'frutas_db';
$usuario = 'root';
$password = '';

try {
    // En este bloque creo la conexión con PDO y le indico que voy a usar UTF-8.
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $usuario, $password);

    // Después le indico a PDO que lance una excepción si ocurre algún error.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Si falla la conexión, acá detengo el programa y muestro el error.
    die("❌ Error de conexión: " . $e->getMessage());
}
?>