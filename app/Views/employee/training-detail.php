<!-- Header -->
<div class="mb-6">
    <a href="<?= $this->url('employee/trainings') ?>" class="text-emerald-600 hover:underline text-sm mb-2 inline-block">← Voltar aos Treinamentos</a>
    <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($playbook['title']) ?></h1>
    <?php if ($assignment['due_date']): ?>
    <p class="text-gray-500 mt-1">Prazo: <?= date('d/m/Y', strtotime($assignment['due_date'])) ?></p>
    <?php endif; ?>
</div>

<?php if ($assignment['status'] === 'completed'): ?>
<!-- Result Card -->
<div class="bg-white rounded-xl shadow-sm p-8 mb-8">
    <div class="text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 <?= $assignment['passed'] ? 'bg-green-100' : 'bg-red-100' ?>">
            <i data-lucide="<?= $assignment['passed'] ? 'check' : 'x' ?>" class="w-10 h-10 <?= $assignment['passed'] ? 'text-green-600' : 'text-red-600' ?>"></i>
        </div>
        <h2 class="text-2xl font-bold <?= $assignment['passed'] ? 'text-green-600' : 'text-red-600' ?>">
            <?= $assignment['passed'] ? 'Aprovado!' : 'Reprovado' ?>
        </h2>
        <p class="text-4xl font-bold text-gray-800 mt-2"><?= number_format($assignment['score'], 1) ?>%</p>
        <p class="text-gray-500 mt-2">Nota mínima: 70%</p>
        
        <?php if (!$assignment['passed']): ?>
        <p class="text-gray-600 mt-4">Você pode tentar novamente. Revise o conteúdo e refaça o questionário.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Content -->
<div class="bg-white rounded-xl shadow-sm p-8 mb-8">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Conteúdo do Treinamento</h2>
    <div class="prose max-w-none">
        <?= $playbook['content_html'] ?>
    </div>
</div>

<!-- Quiz -->
<?php if (!empty($playbook['questions']) && ($assignment['status'] !== 'completed' || !$assignment['passed'])): ?>
<div class="bg-white rounded-xl shadow-sm p-8">
    <h2 class="text-lg font-semibold text-gray-800 mb-6">Questionário</h2>
    
    <form id="quizForm" class="space-y-8">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        
        <?php foreach ($playbook['questions'] as $index => $question): ?>
        <div class="p-6 bg-gray-50 rounded-lg">
            <p class="font-medium text-gray-800 mb-4"><?= $index + 1 ?>. <?= htmlspecialchars($question['question_text']) ?></p>
            
            <div class="space-y-2">
                <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:border-emerald-500 cursor-pointer transition">
                    <input type="radio" name="answers[<?= $question['id'] ?>]" value="<?= $opt ?>" required
                           class="text-emerald-600 focus:ring-emerald-500">
                    <span class="ml-3"><?= $opt ?>) <?= htmlspecialchars($question['option_' . strtolower($opt)]) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <button type="submit" id="submitBtn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-4 rounded-lg font-semibold transition">
            Enviar Respostas
        </button>
    </form>
</div>
<?php endif; ?>

<script>
document.getElementById('quizForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!confirm('Tem certeza que deseja enviar suas respostas?')) return;
    
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch('<?= $this->url("employee/trainings/{$assignment['id']}/submit") ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.error || 'Erro ao enviar');
            btn.disabled = false;
            btn.textContent = 'Enviar Respostas';
        }
    } catch (error) {
        alert('Erro ao processar');
        btn.disabled = false;
        btn.textContent = 'Enviar Respostas';
    }
});
</script>
