<?php
define('ROOT_PATH', dirname(__DIR__));
require_once(ROOT_PATH . '/Visao_do_Professor/templates/header_professor.php');

$page_title = 'Gráfico de Evolução';
$page_icon = 'fas fa-chart-line';
$breadcrumb = 'Portal do Professor > Relatórios > Gráfico de Evolução';

// Busca as turmas
$turmas = [];
$stmt_turmas = $conexao->prepare("SELECT id_turma, nome_turma FROM turmas WHERE id_professor = ? ORDER BY nome_turma");
$stmt_turmas->bind_param("i", $id_professor_logado);
$stmt_turmas->execute();
$result_turmas = $stmt_turmas->get_result();
if($result_turmas) {
    $turmas = $result_turmas->fetch_all(MYSQLI_ASSOC);
}
$stmt_turmas->close();
?>

<div class="card">
    <div class="card-header">
        <h3 class="section-title">Análise de Desenvolvimento (Radar)</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>Selecione a Turma</label>
                <select id="select_turma" onchange="carregarAlunos(this.value)">
                    <option value="">Selecione...</option>
                    <?php foreach($turmas as $turma): ?>
                        <option value="<?php echo $turma['id_turma']; ?>"><?php echo htmlspecialchars($turma['nome_turma']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Selecione a Criança</label>
                <select id="select_aluno" disabled onchange="carregarGrafico()">
                    <option value="">Aguardando turma...</option>
                </select>
            </div>
        </div>

        <div id="grafico-container" style="max-width: 600px; margin: 0 auto; display: none;">
            <canvas id="evolucaoChart"></canvas>
        </div>
        
        <div id="sem-dados" class="alert alert-info" style="display: none; text-align: center; margin-top: 20px;">
            Selecione um aluno para visualizar o gráfico.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$extra_js = '<script>
    let myChart = null;

    function carregarAlunos(turmaId) {
        const alunoSelect = document.getElementById("select_aluno");
        alunoSelect.innerHTML = \'<option value="">A carregar...</option>\';
        alunoSelect.disabled = true;
        document.getElementById("grafico-container").style.display = "none";

        if (!turmaId) {
            alunoSelect.innerHTML = \'<option value="">Aguardando turma...</option>\';
            return;
        }

        fetch("api_get_alunos.php?turma_id=" + turmaId)
            .then(response => response.json())
            .then(data => {
                alunoSelect.innerHTML = \'<option value="">Selecione uma criança</option>\';
                if (data.length > 0) {
                    data.forEach(aluno => {
                        const option = document.createElement("option");
                        option.value = aluno.id_aluno;
                        option.textContent = aluno.nome_completo;
                        alunoSelect.appendChild(option);
                    });
                    alunoSelect.disabled = false;
                } else {
                    alunoSelect.innerHTML = \'<option value="">Nenhuma criança nesta turma</option>\';
                }
            });
    }

    function carregarGrafico() {
        const alunoId = document.getElementById("select_aluno").value;
        if(!alunoId) return;

        fetch("api_get_evolucao.php?aluno_id=" + alunoId)
            .then(response => response.json())
            .then(data => {
                document.getElementById("grafico-container").style.display = "block";
                
                const ctx = document.getElementById("evolucaoChart").getContext("2d");
                
                // Se já existe gráfico, destroi para criar novo
                if(myChart) {
                    myChart.destroy();
                }

                myChart = new Chart(ctx, {
                    type: "radar",
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: "Quantidade de Observações / Atividades",
                            data: data.data,
                            backgroundColor: "rgba(68, 137, 184, 0.2)", // Cor do seu tema
                            borderColor: "rgba(68, 137, 184, 1)",
                            pointBackgroundColor: "rgba(68, 137, 184, 1)",
                            borderWidth: 2
                        }]
                    },
                    options: {
                        scales: {
                            r: {
                                angleLines: { display: true },
                                suggestedMin: 0,
                                suggestedMax: 5 // Escala sugerida
                            }
                        },
                        plugins: {
                            legend: { position: "top" }
                        }
                    }
                });
            });
    }
</script>';

require_once 'templates/footer_professor.php';
?>