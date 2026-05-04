<?php
// Se a listagem usa este caminho e funciona, o ajax na mesma pasta deve usar também
require_once '../../conn_cap.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    try {
        // Detecta qual variável de conexão está ativa no seu conn_cap.php
        $db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

        if (!$db) {
            throw new Exception("Variável de conexão não encontrada (verifique se é \$pdo ou \$conn)");
        }

        if ($db instanceof PDO) {
            // Lógica para PDO
            $stmt = $db->prepare("SELECT favorito FROM leads WHERE id = ?");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $novoStatus = ($lead && $lead['favorito']) ? 0 : 1;

            $update = $db->prepare("UPDATE leads SET favorito = ? WHERE id = ?");
            $update->execute([$novoStatus, $id]);
        } else {
            // Lógica para MySQLi
            $query = mysqli_query($db, "SELECT favorito FROM leads WHERE id = $id");
            $lead = mysqli_fetch_assoc($query);
            
            $novoStatus = ($lead && $lead['favorito']) ? 0 : 1;

            mysqli_query($db, "UPDATE leads SET favorito = $novoStatus WHERE id = $id");
        }

        echo json_encode(['status' => 'success', 'novo_status' => $novoStatus]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Requisição inválida']);
}