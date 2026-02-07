<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Playbooks</h1>
        <p class="text-gray-600">Gerencie seus treinamentos corporativos</p>
    </div>
    <a href="<?= $this->url('playbooks/create') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
        <i data-lucide="plus" class="w-5 h-5"></i>
        <span>Criar Playbook</span>
    </a>
</div>

<!-- Playbooks Grid -->
<?php if (empty($playbooks)): ?>
<div class="bg-white rounded-xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i data-lucide="book-open" class="w-8 h-8 text-blue-600"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Nenhum playbook ainda</h3>
    <p class="text-gray-600 mb-4">Crie seu primeiro playbook usando IA para treinar sua equipe.</p>
    <a href="<?= $this->url('playbooks/create') ?>" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
        <i data-lucide="plus" class="w-5 h-5"></i>
        Criar Playbook
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($playbooks as $playbook): ?>
    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition">
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i data-lucide="book-open" class="w-5 h-5 text-blue-600"></i>
                </div>
                <span class="px-2 py-1 text-xs rounded-full <?= $playbook['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= $playbook['status'] === 'published' ? 'Publicado' : 'Rascunho' ?>
                </span>
            </div>
            
            <h3 class="font-semibold text-gray-800 mb-2"><?= htmlspecialchars($playbook['title']) ?></h3>
            <p class="text-sm text-gray-500 mb-4 line-clamp-2"><?= htmlspecialchars($playbook['description'] ?? 'Sem descrição') ?></p>
            
            <div class="flex items-center justify-between text-sm text-gray-500">
                <span><?= date('d/m/Y', strtotime($playbook['created_at'])) ?></span>
                <span class="flex items-center gap-1">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <?= $playbook['source_type'] ?>
                </span>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t bg-gray-50 rounded-b-xl flex items-center justify-between">
            <a href="<?= $this->url("playbooks/{$playbook['id']}") ?>" class="text-blue-600 hover:underline text-sm">Ver detalhes</a>
            <a href="<?= $this->url("playbooks/{$playbook['id']}/assign") ?>" class="text-emerald-600 hover:underline text-sm">Atribuir</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
