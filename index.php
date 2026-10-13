<?php
// Acá incluyo la conexión a la base de datos y las funciones que voy a reutilizar.
require_once 'conexion.php';
require_once 'funciones.php';

// Después recupero el mensaje que procesar.php me manda al terminar una operación.
$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : '';

// Acá obtengo las frutas activas que voy a mostrar en la pantalla.
$frutas = obtenerStock($pdo);

// También reviso las huellas para saber si tengo que mostrar alguna alerta.
$alertas = verificarIntegridad($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Acá indico que la página está en español, su codificación y el ajuste para celulares. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Stock - Frutas</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="container">
        <!-- En esta parte muestro el título del sistema y el usuario de ejemplo. -->
        <header>
            <h1>📦 Control de Stock de Frutas</h1>
            <p>Usuario: <strong>admin</strong></p>
        </header>

        <!-- Si recibí un mensaje, lo muestro con un estilo según si salió bien o hubo un problema. -->
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo strpos($mensaje, '✅') !== false ? 'exito' : (strpos($mensaje, '⚠️') !== false ? 'alerta' : 'error'); ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Si encontré problemas con las huellas, muestro acá las alertas. -->
        <?php foreach($alertas as $alerta): ?>
            <div class="mensaje alerta-integridad">
                <?php echo $alerta; ?>
            </div>
        <?php endforeach; ?>

        <!-- Recorro las frutas activas y armo una tarjeta para mostrar cada una. -->
        <div class="grid">
            <?php foreach($frutas as $f): ?>
                <div class="card">
                    <div class="icono">
                        <?php 
                        // Acá elijo un emoji según la fruta; si no hay uno definido, uso el genérico.
                        $iconos = ['banana' => '🍌', 'manzana' => '🍎', 'pera' => '🍐'];
                        echo $iconos[$f['fruta']] ?? '🍇';
                        ?>
                    </div>
                    <h3><?php echo ucfirst($f['fruta']); ?></h3>
                    <div class="cantidad"><?php echo $f['cantidad']; ?></div>
                    <a href="procesar.php?eliminar=<?php echo $f['fruta']; ?>" class="btn-eliminar">
                        🗑️ Eliminar
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Desde este formulario mando a procesar.php la fruta, la cantidad y la acción. -->
        <div class="formulario">
            <h2>📝 Registrar Movimiento</h2>
            <form action="procesar.php" method="POST">
                <div class="form-group">
                    <label for="fruta">Seleccionar Fruta:</label>
                    <select name="fruta" id="fruta" required>
                        <!-- Acá cargo en el selector las frutas que siguen activas. -->
                        <?php foreach($frutas as $f): ?>
                            <option value="<?php echo $f['fruta']; ?>">
                                <?php echo ucfirst($f['fruta']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <!-- Acá pido una cantidad y marco que como mínimo se puede ingresar una caja. -->
                    <label for="cantidad">Cantidad de Cajas:</label>
                    <input type="number" name="cantidad" id="cantidad" value="1" min="1" required>
                </div>

                <!-- Según el botón que apriete, después voy a agregar o retirar stock. -->
                <div class="botones">
                    <button type="submit" name="accion" value="agregar" class="btn btn-agregar">
                        ➕ Agregar
                    </button>
                    <button type="submit" name="accion" value="retirar" class="btn btn-retirar">
                        ➖ Retirar
                    </button>
                </div>

                <!-- Con este botón mando la orden de reiniciar el inventario. -->
                <button type="submit" name="resetear" value="1" class="btn btn-reset">
                    🔄 Resetear Sistema
                </button>
            </form>
        </div>

        <!-- Al final muestro automáticamente el año actual. -->
        <footer>
            <p>Sistema con trazabilidad completa - <?php echo date('Y'); ?></p>
        </footer>
    </div>
</body>
</html>