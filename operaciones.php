<?php
// Ojo: este archivo tiene otra versión del procesamiento del inventario.
// La pantalla index.php que uso ahora manda los formularios a procesar.php.
require_once 'conexion.php';

// Uso estas variables para guardar el mensaje, su estilo y el usuario de esta versión.
$mensaje = "";
$tipo_mensaje = "";
$usuario_operador = "admin_frutas"; 

// En esta versión calculo la huella con SHA-256 e incluyo si la fruta está dada de baja.
// Es diferente de la función de funciones.php, que usa MD5 y no incluye ese estado.
// Además, SHA-256 genera 64 caracteres y en setup.sql el campo está definido para 32.
function calcularHash($fruta, $cantidad, $eliminado) {
    // Uno los datos con guiones y calculo su huella con el algoritmo SHA-256.
    return hash('sha256', $fruta . '-' . $cantidad . '-' . $eliminado);
}

// Si la solicitud viene por POST, acá proceso el reinicio o el movimiento de stock.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Primero reviso si el formulario pidió reiniciar el inventario.
    if (isset($_POST['resetear'])) {
        // Si pidieron reiniciar, borro los datos actuales y cargo los valores iniciales.
        $pdo->query("TRUNCATE TABLE inventario");
        $pdo->query("TRUNCATE TABLE trazabilidad");
        
        // Armo una lista con las frutas y las cantidades iniciales.
        $frutas_inicio = [['banana', 12], ['manzana', 8], ['pera', 5]];
        foreach($frutas_inicio as $f) {
            // Por cada fruta calculo su huella y la guardo en el inventario.
            $h = calcularHash($f[0], $f[1], 0);
            // Preparo el INSERT para guardar la fruta como activa junto con su huella.
            $stmt = $pdo->prepare("INSERT INTO inventario (fruta, cantidad, eliminado, hash_control) VALUES (?, ?, 0, ?)");
            // Reemplazo los signos de pregunta por los datos de esta fruta y ejecuto la consulta.
            $stmt->execute([$f[0], $f[1], $h]);
        }
        
        // También guardo en trazabilidad que reinicié el inventario.
        $stmtLog = $pdo->prepare("INSERT INTO trazabilidad (usuario, fecha_hora, tipo_accion, detalles) VALUES (?, NOW(), 'INSERCION', 'Reinicio completo del inventario de fábrica')");
        $stmtLog->execute([$usuario_operador]);

        // Preparo el mensaje que verá el usuario y la clase visual que le corresponde.
        $mensaje = "🔄 Base de datos reiniciada y limpia.";
        $tipo_mensaje = "success";
    } 
    else {
        // Si no es un reinicio, leo la fruta, la cantidad y la acción del formulario.
        $fruta = $_POST['fruta'];
        // Convierto la cantidad recibida a número entero para operar con unidades completas.
        $cantidad = (int)$_POST['cantidad'];
        $accion = $_POST['accion'];

        // No permito procesar cantidades iguales a cero o negativas.
        if ($cantidad <= 0) {
            $mensaje = "⚠️ Cantidad no válida.";
            $tipo_mensaje = "danger";
        } else {
            // Primero busco cuánto stock hay para esa fruta activa.
            $stmt = $pdo->prepare("SELECT cantidad FROM inventario WHERE fruta = ? AND eliminado = 0");
            $stmt->execute([$fruta]);
            $resultado = $stmt->fetch();

            // Solo continúo si encontré una fruta activa en la base de datos.
            if ($resultado) {
                $stock_actual = $resultado['cantidad'];
                // Guardo el valor actual y preparo una variable para calcular el nuevo stock.
                $nuevo_stock = $stock_actual;

                // Acá sumo o resto stock; antes de retirar, reviso que haya suficiente.
                if ($accion === 'agregar') {
                    $nuevo_stock = $stock_actual + $cantidad;
                    // Armo el mensaje de confirmación para la operación de agregar.
                    $mensaje = "✅ Se agregaron $cantidad cajas de " . ucfirst($fruta) . "s.";
                    $tipo_mensaje = "success";
                } 
                elseif ($accion === 'retirar') {
                    if ($stock_actual >= $cantidad) {
                        $nuevo_stock = $stock_actual - $cantidad;
                        // Si hay suficiente stock, descuento la cantidad y preparo la confirmación.
                        $mensaje = "📉 Se retiraron $cantidad cajas de " . ucfirst($fruta) . "s.";
                        $tipo_mensaje = "success";
                    } else {
                        // Si no alcanza el stock, aviso del error y marco la acción como inválida.
                        $mensaje = "❌ Stock insuficiente.";
                        $tipo_mensaje = "danger";
                        $accion = "error";
                    }
                }

                // Solo guardo cambios si no marqué la operación como inválida.
                if ($accion !== "error") {
                    // Si la operación fue válida, guardo el stock, la huella y el movimiento.
                    $nuevo_hash = calcularHash($fruta, $nuevo_stock, 0);
                    // Actualizo en la base la cantidad y la huella de esta fruta.
                    $updateStmt = $pdo->prepare("UPDATE inventario SET cantidad = ?, hash_control = ? WHERE fruta = ?");
                    $updateStmt->execute([$nuevo_stock, $nuevo_hash, $fruta]);

                    // Describo el cambio para que quede claro en la trazabilidad.
                    $detalles_cambio = "Cambio de stock de $stock_actual a $nuevo_stock unidades.";
                    // Preparo y guardo el registro del movimiento en el historial.
                    $logStmt = $pdo->prepare("INSERT INTO trazabilidad (usuario, fecha_hora, tipo_accion, detalles) VALUES (?, NOW(), 'UPDATE', ?)");
                    $logStmt->execute([$usuario_operador, $detalles_cambio]);
                }
            }
        }
    }
}

// Si llega delete en la dirección, doy de baja la fruta sin borrar su fila.
if (isset($_GET['delete'])) {
    $fruta_del = $_GET['delete'];
    // Primero compruebo que la fruta todavía esté activa.
    $stmt = $pdo->prepare("SELECT cantidad FROM inventario WHERE fruta = ? AND eliminado = 0");
    // Paso el nombre recibido por la URL como parámetro de la consulta.
    $stmt->execute([$fruta_del]);
    $res = $stmt->fetch();
    
    if($res) {
        // Después marco la baja, calculo la huella nueva y anoto la operación.
        $nuevo_hash = calcularHash($fruta_del, $res['cantidad'], 1);
        // Actualizo la marca de baja y la huella sin borrar el registro de inventario.
        $delStmt = $pdo->prepare("UPDATE inventario SET eliminado = 1, hash_control = ? WHERE fruta = ?");
        $delStmt->execute([$nuevo_hash, $fruta_del]);
        
        // Guardo en trazabilidad qué fruta di de baja.
        $logStmt = $pdo->prepare("INSERT INTO trazabilidad (usuario, fecha_hora, tipo_accion, detalles) VALUES (?, NOW(), 'SOFT-DELETE', ?)");
        $logStmt->execute([$usuario_operador, "Baja lógica de la fruta: $fruta_del"]);
        
        // Dejo listo el mensaje de confirmación y su estilo.
        $mensaje = "🗑️ Fruta '" . ucfirst($fruta_del) . "' dada de baja mediante Soft-Delete.";
        $tipo_mensaje = "success";
    }
}
?>