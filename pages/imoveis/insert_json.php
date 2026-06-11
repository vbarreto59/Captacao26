<?php
session_start();
require_once '../../includes/auth.php'; 
require_once '../../conn_cap.php'; 

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['json_data'])) {
    try {
        // Decodifica o JSON vindo do formulário como um array associativo
        $data = json_decode($_POST['json_data'], true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido: " . json_last_error_msg());
        }

        // --- BLINDAGEM AUTOMÁTICA EM TEMPO REAL ---
        // Busca as colunas que REALMENTE existem na tabela imoveis da conexão ativa
        $stmtCheck = $conn->query("DESCRIBE imoveis");
        $colunas_reais_no_banco = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

        // Definição estrita dos campos aceitos no script
        $campos_permitidos = [
            'proprietario_id', 'corretor_id', 'titulo', 'nome_edificio', 'endereco', 'bairro', 'cidade', 
            'estado', 'cep', 'latitude', 'longitude', 'preco', 'quartos', 'suites', 'banheiros', 
            'area', 'andar', 'face', 'vagas_garagem', 'vaga_coberta', 'varanda', 'tipo', 'conservacao', 
            'construtora', 'ano_entrega', 'descricao', 'status', 'aceita_parceria', 'divisao_comissao', 
            'categoria_registro', 'mobiliado', 'aceita_animais', 'gas_encanado', 'tem_piscina', 
            'tem_academia', 'tem_salao_festas', 'tem_espaco_gourmet', 'area_lazer', 'jardim', 
            'tem_playground', 'possui_elevador', 'possui_moveis_planejados', 'agua_inclusa_condominio', 
            'gas_incluso_condominio', 'valor_condominio', 'valor_iptu', 'contato_sindico', 'telefone', 
            'contato_portaria', 'portaria_24h', 'sistema_cameras', 'gerador', 'pilotis', 'portao_eletronico', 
            'link_site', 'resposta_rapida', 'observacoes_gerais', 'rip_marinha', 'regime_marinha', 
            'valor_foro_anual', 'laudemio_pago', 'aceita_financiamento', 'aceita_fgts', 'aceita_permuta', 
            'aceita_consorcio', 'valor_sinal', 'reservado', 'data_reserva', 'data_venda', 'entrega_obra'
        ];

        // Mapeia quais campos precisam de conversão estrita de moeda/número BR para US (ponto decimal)
        $campos_numericos = [
            'preco', 'valor_condominio', 'valor_iptu', 'area', 'valor_foro_anual', 'valor_sinal'
        ];

        // Garante o proprietario_id padrão direto na árvore de dados antes de processar as colunas
        if (!isset($data['proprietario_id']) || $data['proprietario_id'] === '') {
            $data['proprietario_id'] = 9;
        }

        $colunas_insert = [];
        $dados_finais = [];

        foreach ($campos_permitidos as $campo) {
            // CRÍTICO: Só adiciona na Query se o campo existir no JSON E se ele existir REALMENTE na tabela do banco
            if (array_key_exists($campo, $data) && in_array($campo, $colunas_reais_no_banco)) {
                $colunas_insert[] = "`$campo`";
                
                // Tratamento de valores vazios/nulos
                if ($data[$campo] === null || $data[$campo] === '') {
                    $dados_finais[] = in_array($campo, $campos_numericos) ? 0.00 : null;
                } else {
                    // Tratamento específico se o campo for financeiro/numérico com formato brasileiro
                    if (in_array($campo, $campos_numericos)) {
                        $valor = $data[$campo];
                        if (is_string($valor)) {
                            $valor = str_replace('.', '', $valor);
                            $valor = str_replace(',', '.', $valor);
                        }
                        $dados_finais[] = (float)$valor;
                    } else {
                        $dados_finais[] = $data[$campo];
                    }
                }
            }
        }

        if (empty($colunas_insert)) {
            throw new Exception("Nenhum campo válido e existente no banco de dados foi mapeado do JSON enviado.");
        }

        // Montagem da Query usando Prepared Statements com marcadores "?" posicionais correspondentes
        $plds = implode(", ", array_fill(0, count($dados_finais), "?"));
        $sql = "INSERT INTO imoveis (" . implode(', ', $colunas_insert) . ") VALUES ($plds)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($dados_finais);
        
        $id_gerado = $conn->lastInsertId();
        $mensagem = "<div class='alert alert-success shadow-sm'><i class='bi bi-check-circle-fill me-2'></i> ✅ Imóvel importado com sucesso! <strong>ID gerado: $id_gerado</strong></div>";

    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> ❌ Erro ao importar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<?php require_once '../../includes/header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-11">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary fw-bold m-0"><i class="bi bi-box-arrow-in-down"></i> Importador de Estrutura JSON (Versão Blindada)</h2>
                <a href="form_triagem.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Voltar para Triagem</a>
            </div>

            <?= $mensagem; ?>

            <div class="card shadow-sm border-0 mb-5">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0"><i class="bi bi-code-slash"></i> Terminal de Inserção Automatizada</h5>
                    <span class="badge bg-success">Segurança Ativa: Filtro Dinâmico de Colunas</span>
                </div>
                <div class="card-body bg-light">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="json_data" class="form-label fw-bold text-muted">Cole aqui o código JSON copiado do chat:</label>
                            <textarea class="form-control font-monospace text-dark bg-white shadow-inner" id="json_data" name="json_data" rows="18" placeholder='{\n  "proprietario_id": 9,\n  "corretor_id": 1,\n  "titulo": "Edifício Wimbledon...",\n  ...\n}' required><?= isset($_POST['json_data']) ? htmlspecialchars($_POST['json_data']) : '' ?></textarea>
                            <div class="form-text text-muted"><i class="bi bi-info-circle"></i> O script agora cruza os dados enviados com a estrutura real da tabela através de um comando `DESCRIBE` interno automático.</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-outline-secondary me-md-2 fw-bold" onclick="document.getElementById('json_data').value=''">Limpar Área</button>
                            <button type="submit" class="btn btn-success px-5 fw-bold shadow">PROCESSAR E INSERIR NO BANCO</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>