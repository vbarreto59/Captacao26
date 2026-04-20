<?php
// deletar_imagem.php - Mesma pasta do form.php

session_start();
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    try {
        $stmt = $conn->prepare("SELECT caminho FROM fotos_imoveis WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($img) {
            $arquivo = '../../fotos_imoveis/' . $img['caminho'];
            if (file_exists($arquivo)) {
                unlink($arquivo);
            }

            $stmt = $conn->prepare("DELETE FROM fotos_imoveis WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Imagem não encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir imagem']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
}
?>