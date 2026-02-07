<div class="space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Gerenciar: <?= htmlspecialchars($course['title']) ?></h1>
            <p class="text-gray-600 text-sm max-w-2xl mt-1">
                <?= htmlspecialchars($course['description'] ?? '') ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-medium <?= $course['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                <?= $course['status'] === 'published' ? 'Publicado' : 'Rascunho' ?>
            </span>
            <a href="<?= $this->url('courses') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-50 transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Voltar
            </a>
            <a href="<?= $this->url("courses/{$course['id']}") ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-800 text-sm hover:bg-gray-50 transition">
                <i data-lucide="eye" class="w-4 h-4"></i>
                Ver detalhes
            </a>
            <a href="<?= $this->url("courses/{$course['id']}/preview") ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-black transition">
                <i data-lucide="play" class="w-4 h-4"></i>
                Preview do curso
            </a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Módulos</p>
            <p class="text-3xl font-bold text-gray-800"><?= (int)($course['total_modules'] ?? count($course['modules'] ?? [])) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Aulas</p>
            <p class="text-3xl font-bold text-gray-800"><?= (int)($course['total_lessons'] ?? 0) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Status</p>
            <p class="text-sm font-semibold text-gray-800">
                <?= $course['status'] === 'published' ? 'Publicado (visível para funcionários matriculados)' : 'Rascunho (ainda não publicado)' ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Estrutura do curso -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i data-lucide="layers" class="w-5 h-5 text-purple-600"></i>
                    Estrutura do Curso
                </h2>

                <?php if (empty($course['modules'])): ?>
                    <p class="text-gray-500 text-sm">Nenhum módulo encontrado para este curso.</p>
                <?php else: ?>
                    <p class="text-xs text-gray-500 mb-3">Clique em uma aula para configurar o conteúdo completo e o vídeo da aula.</p>
                    <div class="space-y-4">
                        <?php foreach ($course['modules'] as $module): ?>
                            <div class="border border-gray-200 rounded-lg">
                                <div class="px-4 py-3 bg-gray-50 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">
                                            Módulo <?= (int)$module['order_number'] ?>: <?= htmlspecialchars($module['title']) ?>
                                        </p>
                                        <?php if (!empty($module['description'])): ?>
                                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($module['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-gray-400">
                                        <?= count($module['lessons'] ?? []) ?> aulas
                                    </span>
                                </div>
                                <?php if (!empty($module['lessons'])): ?>
                                    <div class="px-4 py-3 space-y-2">
                                        <?php foreach ($module['lessons'] as $lesson): ?>
                                            <a href="<?= $this->url("courses/lessons/{$lesson['id']}") ?>" class="block">
                                                <div class="flex items-start gap-3 hover:bg-purple-50 rounded-lg px-2 py-2 transition">
                                                    <div class="mt-1">
                                                        <i data-lucide="play-circle" class="w-4 h-4 text-purple-500"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-800">
                                                            Aula <?= (int)$lesson['order_number'] ?>: <?= htmlspecialchars($lesson['title']) ?>
                                                        </p>
                                                        <?php if (!empty($lesson['content_html'])): ?>
                                                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">Conteúdo configurado</p>
                                                        <?php else: ?>
                                                            <p class="text-xs text-red-500 mt-1">Conteúdo ainda não configurado</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ações rápidas -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i data-lucide="zap" class="w-5 h-5 text-amber-500"></i>
                    Ações rápidas
                </h2>
                <p class="text-sm text-gray-600 mb-3">Use estas ações para preparar o curso antes de publicá-lo.</p>
                <div class="space-y-3">
                    <form method="POST" action="<?= $this->url("courses/{$course['id']}/regenerate") ?>" onsubmit="return confirm('Isso irá apagar e recriar todos os módulos e aulas deste curso com base no título e descrição. Deseja continuar?');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700 transition">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                            <span>Regerar conteúdo com IA</span>
                        </button>
                    </form>
                    <a href="<?= $this->url("courses/{$course['id']}/preview") ?>" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-800 hover:bg-gray-50 transition">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        <span>Ver como o aluno verá o curso</span>
                    </a>
                    <button
                        type="button"
                        onclick="deleteCourse(<?= (int)$course['id'] ?>)"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-50 text-red-700 text-sm hover:bg-red-100 transition">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        <span>Excluir curso</span>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5 text-emerald-600"></i>
                    Matrículas
                </h2>
                <p class="text-sm text-gray-600 mb-3">Gerencie quais funcionários estão matriculados neste curso.</p>
                <a href="<?= $this->url("courses/{$course['id']}") ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700 transition">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Gerenciar matrículas
                </a>
            </div>
        </div>
    </div>
</div>

<script>
async function deleteCourse(courseId) {
    if (!confirm('Tem certeza que deseja excluir este curso? Esta ação não pode ser desfeita.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('_method', 'DELETE');

        const response = await fetch('<?= $this->url('courses') ?>/' + courseId, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '<?= $this->url('courses') ?>';
        } else {
            alert(data.error || 'Erro ao excluir curso.');
        }
    } catch (e) {
        alert('Erro ao excluir curso.');
    }
}
</script>
