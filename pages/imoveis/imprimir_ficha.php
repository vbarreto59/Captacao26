<?php
// Não precisa de conexão com banco, pois é uma ficha para preenchimento manual
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Captação Manual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #fff; font-size: 13px; color: #000; }
        .ficha-container { padding: 20px; margin: auto; max-width: 900px; border: 1px solid #000; }
        .header-ficha { border-bottom: 2px solid #000; margin-bottom: 15px; padding-bottom: 10px; }
        .section-title { background: #eee; border: 1px solid #000; color: #000; padding: 3px 10px; font-weight: bold; text-transform: uppercase; margin-top: 15px; margin-bottom: 10px; }
        
        .field { margin-bottom: 12px; }
        .label-data { font-weight: bold; margin-right: 5px; text-transform: uppercase; font-size: 11px; }
        .line { border-bottom: 1px solid #000; display: inline-block; flex-grow: 1; height: 18px; }
        .box { border: 1px solid #000; width: 15px; height: 15px; display: inline-block; vertical-align: middle; margin-right: 5px; }
        
        @media print {
            .no-print { display: none !important; }
            .ficha-container { border: none; padding: 0; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="container no-print text-center my-4">
    <button onclick="window.print()" class="btn btn-dark btn-lg">
        <i class="bi bi-printer"></i> IMPRIMIR FICHA PARA RUA
    </button>
</div>

<div class="ficha-container">
    <div class="header-ficha d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">FICHA DE VISITA E CAPTAÇÃO</h3>
            <p class="mb-0">Data da Visita: ____/____/202__ | Captador: __________________________</p>
        </div>
        <div class="text-end">
            <strong>CÓDIGO: ________</strong>
        </div>
    </div>

    <div class="section-title">Dados do Proprietário</div>
    <div class="row field">
        <div class="col-7 d-flex"><span class="label-data">Nome:</span><div class="line"></div></div>
        <div class="col-5 d-flex"><span class="label-data">Telefone:</span><div class="line"></div></div>
    </div>
    <div class="row field">
        <div class="col-12 d-flex"><span class="label-data">E-mail:</span><div class="line"></div></div>
    </div>

    <div class="section-title">Localização do Imóvel</div>
    <div class="row field">
        <div class="col-9 d-flex"><span class="label-data">Endereço:</span><div class="line"></div></div>
        <div class="col-3 d-flex"><span class="label-data">Nº:</span><div class="line"></div></div>
    </div>
    <div class="row field">
        <div class="col-4 d-flex"><span class="label-data">Bairro:</span><div class="line"></div></div>
        <div class="col-4 d-flex"><span class="label-data">Cidade:</span><div class="line"></div></div>
        <div class="col-2 d-flex"><span class="label-data">Andar:</span><div class="line"></div></div>
        <div class="col-2 d-flex"><span class="label-data">Apto:</span><div class="line"></div></div>
    </div>

    <div class="section-title">Características Técnicas</div>
    <div class="row field text-center">
        <div class="col-2"><span class="label-data">Dormitórios</span><br> ( &nbsp; )</div>
        <div class="col-2"><span class="label-data">Suítes</span><br> ( &nbsp; )</div>
        <div class="col-2"><span class="label-data">Vagas</span><br> ( &nbsp; )</div>
        <div class="col-2"><span class="label-data">Área M²</span><br> ________</div>
        <div class="col-2"><span class="label-data">Face Sol</span><br> ________</div>
        <div class="col-2"><span class="label-data">Ano Const.</span><br> ________</div>
    </div>

    <div class="section-title">Itens de Lazer / Estrutura</div>
    <div class="row mb-2">
        <div class="col-3"><div class="box"></div> Piscina</div>
        <div class="col-3"><div class="box"></div> Academia</div>
        <div class="col-3"><div class="box"></div> Salão Festas</div>
        <div class="col-3"><div class="box"></div> Playground</div>
        <div class="col-3"><div class="box"></div> Espaço Gourmet</div>
        <div class="col-3"><div class="box"></div> Churrasqueira</div>
        <div class="col-3"><div class="box"></div> Elevador</div>
        <div class="col-3"><div class="box"></div> Gás Encanado</div>
        <div class="col-3"><div class="box"></div> Portaria 24h</div>
        <div class="col-3"><div class="box"></div> Mobiliado</div>
        <div class="col-3"><div class="box"></div> Planejados</div>
        <div class="col-3"><div class="box"></div> Varanda/Sacada</div>
    </div>

    <div class="section-title">Valores (Expectativa do Cliente)</div>
    <div class="row field">
        <div class="col-4 d-flex"><span class="label-data">Valor Venda:</span> R$ <div class="line"></div></div>
        <div class="col-4 d-flex"><span class="label-data">Condomínio:</span> R$ <div class="line"></div></div>
        <div class="col-4 d-flex"><span class="label-data">IPTU:</span> R$ <div class="line"></div></div>
    </div>

    <div class="section-title">Despesas e Manutenções Necessárias</div>
    <div class="row field">
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
    </div>

    <div class="section-title">Descrição e Observações</div>
    <div class="row field">
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
        <div class="col-12"><div class="line" style="width: 100%; margin-bottom: 8px;"></div></div>
    </div>

    <div class="mt-5 pt-4">
        <div class="row text-center">
            <div class="col-6">
                _______________________________________<br>
                <small>Assinatura do Proprietário (Autorização)</small>
            </div>
            <div class="col-6">
                _______________________________________<br>
                <small>Carimbo / Assinatura do Corretor</small>
            </div>
        </div>
    </div>
</div>

<footer class="text-center mt-3 no-print">
    <p class="text-muted small">Dica: Ao imprimir, selecione "Remover cabeçalhos e rodapés" nas configurações do navegador.</p>
</footer>

</body>
</html>