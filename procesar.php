<?php
// Acá cargo la conexión y las funciones que también uso en otras partes del sistema.
require_once 'conexion.php';
require_once 'funciones.php';

$mensaje = "";

// Si apreté el botón de reinicio, acá vacío las tablas y vuelvo a cargar el stock inicial.
if (isset($_POST['resetear'])) {
    $pdo->query("TRUNCATE TABLE inventario");
    $pdo->query("TRUNCATE TABLE trazabilidad");
    
    // Primero defino qué frutas y cantidades va a tener el inventario inicial.
    $frutas = [['banana', 12], ['manzana', 8], ['pera', 5]];
    foreach($frutas as $f) {
        // Para cada fruta calculo su huella y la guardo como activa en la base.
        $hash = calcularHash($f[0], $f[1]);
        $stmt = $pdo->prepare("INSERT INTO inventario (fruta, cantidad, eliminado, hash_control) VALUES (?, ?, 0, ?)");
        $stmt->execute([$f[0], $f[1], $hash]);
    }
    
    // También dejo anotado en la trazabilidad que reinicié el sistema.
    registrarLog($pdo, 'INSERCIÓN', 'Datos iniciales cargados: banana(12), manzana(8), pera(5)');
    $mensaje = "✅ Sistema reiniciado correctamente";
}

// Si recibí una acción de agregar o retirar, acá proceso el movimiento.
if (isset($_POST['accion'])) {
    $fruta = $_POST['fruta'];
    // Convierto la cantidad a entero porque trabajo con cajas completas.
    $cantidad = (int)$_POST['cantidad'];
    $accion = $_POST['accion'];
    
    if ($cantidad <= 0) {
        $mensaje = "⚠️ La cantidad debe ser mayor a 0";
    } else {
        // Primero busco cuánto stock hay, siempre que la fruta siga activa.
        $stmt = $pdo->prepare("SELECT cantidad FROM inventario WHERE fruta = ? AND eliminado = 0");
        $stmt->execute([$fruta]);
        $res = $stmt->fetch();
        
        if ($res) {
            $stock_actual = $res['cantidad'];
            // Guardo el stock actual y, por ahora, dejo el nuevo igual hasta validar la acción.
            $nuevo_stock = $stock_actual;
            
            if ($accion == 'agregar') {
                $nuevo_stock = $stock_actual + $cantidad;
                $mensaje = "✅ Agregadas $cantidad cajas de $fruta";
                // Si agrego stock, también anoto en la trazabilidad cuánto cambió.
                registrarLog($pdo, 'UPDATE', "$fruta: $stock_actual → $nuevo_stock (+$cantidad)");
                
            } elseif ($accion == 'retirar') {
                if ($stock_actual >= $cantidad) {
                    $nuevo_stock = $stock_actual - $cantidad;
                    $mensaje = "✅ Retiradas $cantidad cajas de $fruta";
                    // Si el retiro se pudo hacer, también lo anoto en la trazabilidad.
                    registrarLog($pdo, 'UPDATE', "$fruta: $stock_actual → $nuevo_stock (-$cantidad)");
                } else {
                    // Si no hay suficiente stock, no hago el retiro para que no quede negativo.
                    $mensaje = "❌ Stock insuficiente. Disponible: $stock_actual";
                }
            }
            
            // Si cambió la cantidad, actualizo el stock y calculo su huella nueva.
            if ($nuevo_stock != $stock_actual) {
                $hash = calcularHash($fruta, $nuevo_stock);
                $stmt = $pdo->prepare("UPDATE inventario SET cantidad = ?, hash_control = ? WHERE fruta = ?");
                $stmt->execute([$nuevo_stock, $hash, $fruta]);
            }
        }
    }
}
// Si me piden eliminar una fruta, la doy de baja sin borrar su fila de la base.
if (isset($_GET['eliminar'])) {
    $fruta = $_GET['eliminar'];
    
    // Antes reviso que exista y que todavía esté activa.
    $stmt = $pdo->prepare("SELECT cantidad FROM inventario WHERE fruta = ? AND eliminado = 0");
    $stmt->execute([$fruta]);
    $res = $stmt->fetch();
    
    if ($res) {
        $cantidad = $res['cantidad'];
        
        // La marco como eliminada y dejo anotada la baja en la trazabilidad.
        $stmt = $pdo->prepare("UPDATE inventario SET eliminado = 1 WHERE fruta = ?");
        $stmt->execute([$fruta]);
        registrarLog($pdo, 'SOFT-DELETE', "$fruta (Stock: $cantidad unidades)");
        
        $mensaje = "🗑️ '$fruta' eliminada (baja lógica)";
    } else {
        $mensaje = "❌ Fruta no encontrada o ya eliminada";
    }
}

// Para terminar vuelvo a la pantalla principal y le paso el mensaje del resultado.
header("Location: index.php?mensaje=" . urlencode($mensaje));
exit();
?>