<?php
/**
 * Acá calculo una huella de control usando el nombre y la cantidad de la fruta.
 * Después comparo esta huella con la guardada para revisar si cambiaron los datos.
 */
function calcularHash($fruta, $cantidad) {
    return md5($fruta . '-' . $cantidad);
}

/**
 * Con esta función guardo una acción en la tabla de trazabilidad como historial.
 * 
 * @param PDO $pdo Acá recibo la conexión a la base de datos.
 * @param string $tipo_accion Acá recibo el tipo de acción que quiero registrar.
 * @param string $detalles Acá recibo una descripción de lo que pasó.
 */
function registrarLog($pdo, $tipo_accion, $detalles) {
    // Para este ejemplo uso un usuario fijo y así simplifico el registro.
    $usuario = 'admin';

    // Armo la consulta para guardar el usuario, la fecha, el tipo y el detalle.
    $sql = "INSERT INTO trazabilidad (usuario, fecha_hora, tipo_accion, detalles) 
            VALUES (?, NOW(), ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario, $tipo_accion, $detalles]);
}

/**
 * Acá obtengo el stock de las frutas que siguen activas.
 * 
 * @param PDO $pdo Acá recibo la conexión para consultar el inventario.
 * @return array Devuelvo una lista con las frutas que siguen activas.
 */
function obtenerStock($pdo) {
    // Filtro las frutas dadas de baja y devuelvo solamente las activas.
    return $pdo->query("SELECT * FROM inventario WHERE eliminado = 0")->fetchAll();
}

/**
 * Acá reviso la integridad comparando las huellas guardadas con las calculadas.
 * Si encuentro diferencias, las agrego a la lista de alertas.
 * 
 * @param PDO $pdo Acá recibo la conexión para leer el inventario.
 * @return array Devuelvo las alertas encontradas; si no hay problemas, queda vacía.
 */
function verificarIntegridad($pdo) {
    // Primero busco las frutas activas para revisar sus huellas.
    $sql = "SELECT * FROM inventario WHERE eliminado = 0";
    $stmt = $pdo->query($sql);
    $alertas = [];
    
    while($fila = $stmt->fetch()) {
        // Para cada fruta calculo de nuevo la huella con sus datos actuales.
        $hash_calculado = calcularHash($fila['fruta'], $fila['cantidad']);

        // Si la huella no coincide con la guardada, agrego una alerta.
        if ($hash_calculado !== $fila['hash_control']) {
            $alertas[] = "⚠️ ALERTA DE INTEGRIDAD: El stock de " . ucfirst($fila['fruta']) . " fue modificado manualmente";
        }
    }
    
    // Al final devuelvo las alertas; si está todo bien, la lista queda vacía.
    return $alertas;
}
?>