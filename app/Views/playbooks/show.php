<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="<?= $this->url('playbooks') ?>" class="text-blue-600 hover:underline text-sm mb-2 inline-block">← Voltar aos Playbooks</a>
        <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($playbook['title']) ?></h1>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($playbook['status'] === 'draft'): ?>
        <button onclick="publishPlaybook()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
            <i data-lucide="check" class="w-5 h-5"></i>
            Publicar
        </button>
        <?php endif; ?>
        <a href="<?= $this->url("playbooks/{$playbook['id']}/assign") ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
            <i data-lucide="users" class="w-5 h-5"></i>
            Atribuir
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Status Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="px-3 py-1 rounded-full text-sm <?= $playbook['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                        <?= $playbook['status'] === 'published' ? 'Publicado' : 'Rascunho' ?>
                    </span>
                    <span class="text-gray-500 text-sm">Criado em <?= date('d/m/Y', strtotime($playbook['created_at'])) ?></span>
                </div>
                <span class="text-gray-500 text-sm">Fonte: <?= ucfirst($playbook['source_type']) ?></span>
            </div>
        </div>
        
        <!-- Content -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Conteúdo do Treinamento</h2>
            <div class="prose max-w-none">
                <?= $playbook['content_html'] ?>
            </div>
        </div>
        
        <!-- Questions -->
        <?php if (!empty($playbook['questions'])): ?>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Questionário (<?= count($playbook['questions']) ?> questões)</h2>
            <div class="space-y-6">
                <?php foreach ($playbook['questions'] as $index => $question): ?>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="font-medium text-gray-800 mb-3"><?= $index + 1 ?>. <?= htmlspecialchars($question['question_text']) ?></p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                        <div class="p-2 rounded <?= $question['correct_option'] === 'A' ? 'bg-green-100 text-green-700' : 'bg-white' ?>">
                            A) <?= htmlspecialchars($question['option_a']) ?>
                        </div>
                        <div class="p-2 rounded <?= $question['correct_option'] === 'B' ? 'bg-green-100 text-green-700' : 'bg-white' ?>">
                            B) <?= htmlspecialchars($question['option_b']) ?>
                        </div>
                        <div class="p-2 rounded <?= $question['correct_option'] === 'C' ? 'bg-green-100 text-green-700' : 'bg-white' ?>">
                            C) <?= htmlspecialchars($question['option_c']) ?>
                        </div>
                        <div class="p-2 rounded <?= $question['correct_option'] === 'D' ? 'bg-green-100 text-green-700' : 'bg-white' ?>">
                            D) <?= htmlspecialchars($question['option_d']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Stats -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Estatísticas</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Atribuições</span>
                    <span class="font-semibold"><?= count($assignments) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Concluídos</span>
                    <span class="font-semibold"><?= count(array_filter($assignments, fn($a) => $a['status'] === 'completed')) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Questões</span>
                    <span class="font-semibold"><?= count($playbook['questions'] ?? []) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Assignments -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Funcionários Atribuídos</h3>
            <?php if (empty($assignments)): ?>
            <p class="text-gray-500 text-sm">Nenhum funcionário atribuído ainda.</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($assignments, 0, 5) as $assignment): ?>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700"><?= htmlspecialchars($assignment['employee_name']) ?></span>
                    <span class="text-xs px-2 py-1 rounded-full 
                        <?= $assignment['status'] === 'completed' ? 'bg-green-100 text-green-700' : '' ?>
                        <?= $assignment['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' ?>
                        <?= $assignment['status'] === 'pending' ? 'bg-gray-100 text-gray-700' : '' ?>">
                        <?= $assignment['score'] ? number_format($assignment['score'], 0) . '%' : ucfirst($assignment['status']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function publishPlaybook() {
    if (!confirm('Deseja publicar este playbook?')) return;
    
    try {
        const response = await fetch('<?= $this->url("playbooks/{$playbook['id']}/publish") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });
        
        const data = await response.json();
        
        if (data.requires_payment) {
            alert('Este playbook requer pagamento de R$ ' + data.amount.toFixed(2));
            return;
        }
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao publicar');
        }
    } catch (error) {
        alert('Erro ao processar');
    }
}
</script>
