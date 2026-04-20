<?php
// ====================== upload_fotos.php ======================
// Arquivo na mesma pasta do form.php

if (!empty($_FILES['fotos']['name'][0]) && $id > 0) {
    
    global $conn;
    
    $upload_dir = '../../uploads/fotos_imoveis/';   // Mesmo local das imagens existentes

    // Cria a pasta caso não exista
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $total_arquivos = count($_FILES['fotos']['name']);
    $sucessos = 0;

    for ($i = 0; $i < $total_arquivos; $i++) {

        if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $nome_original = $_FILES['fotos']['name'][$i];
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));

        // Validações
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extensao, $tipos_permitidos)) {
            continue;
        }

        if ($_FILES['fotos']['size'][$i] > 10 * 1024 * 1024) { // 10 MB
            continue;
        }

        // Nome único para evitar conflitos
        $novo_nome = "imovel_{$id}_" . date('Ymd_His') . "_" . uniqid() . "." . $extensao;
        $caminho_final = $upload_dir . $novo_nome;

        // Move o arquivo
        if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $caminho_final)) {
            
            // Insere no banco de dados
            $stmt = $conn->prepare("INSERT INTO fotos_imoveis (imovel_id, caminho, created_at) 
                                  VALUES (?, ?, NOW())");
            $stmt->execute([$id, $novo_nome]);
            
            $sucessos++;
        }
    }

    // Opcional: você pode adicionar uma mensagem de sucesso aqui se quiser
    if ($sucessos > 0) {
        // Sucesso - o redirecionamento já é feito no form.php
    }
}
?>